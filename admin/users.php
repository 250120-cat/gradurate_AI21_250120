<?php
require_once __DIR__ . '/../functions.php';
require_login();
require_role('admin');

$page_title = 'ユーザー管理';
$page_description = '登録ユーザー、通知設定、飼い主アカウントの仮パスワード再発行を管理します。';

$pdo = get_pdo();
$message = '';
$message_type = 'notice';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        $message = '不正なリクエストです。';
        $message_type = 'alert';
    } else {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $action = $_POST['action'] ?? 'update_user';

        try {
            if ($action === 'reset_owner_password') {
                $accountStmt = $pdo->prepare(
                    "SELECT username
                     FROM users
                     WHERE user_id = ? AND role = 'owner'
                     LIMIT 1"
                );
                $accountStmt->execute([$user_id]);
                $username = $accountStmt->fetchColumn();
                if (!$username) {
                    throw new InvalidArgumentException('飼い主アカウントが見つかりません。');
                }

                $temporary_password = bin2hex(random_bytes(8));
                $resetStmt = $pdo->prepare(
                    "UPDATE users
                     SET password_hash = ?, password_change_required = 1
                     WHERE user_id = ? AND role = 'owner'"
                );
                $resetStmt->execute([
                    password_hash($temporary_password, PASSWORD_DEFAULT),
                    $user_id,
                ]);
                if ($resetStmt->rowCount() !== 1) {
                    throw new RuntimeException('パスワードを更新できませんでした。');
                }
                add_audit_log(
                    'owner_password_reset',
                    'user',
                    $user_id,
                    '管理者が飼い主アカウントの仮パスワードを再発行しました'
                );
                $_SESSION['admin_owner_password_reset'] = [
                    'username' => $username,
                    'temporary_password' => $temporary_password,
                ];
                redirect(BASE_URL . '/admin/users.php?password_reset=1');
            }

            $email = trim($_POST['email'] ?? '');
            $notify_lost = isset($_POST['notify_lost']) ? 1 : 0;
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('メールアドレスの形式を確認してください。');
            }
            $stmt = $pdo->prepare('UPDATE users SET email = ?, notify_lost = ? WHERE user_id = ?');
            $stmt->execute([$email !== '' ? $email : null, $notify_lost, $user_id]);
            $_SESSION['flash_message'] = 'ユーザー情報を更新しました。';
            redirect(BASE_URL . '/admin/users.php');
        } catch (Throwable $error) {
            if ($error instanceof InvalidArgumentException || $error instanceof RuntimeException) {
                $message = $error->getMessage();
            } else {
                error_log($error->getMessage());
                $message = $action === 'reset_owner_password'
                    ? '仮パスワードの再発行に失敗しました。'
                    : '更新に失敗しました。メールアドレスが重複していないか確認してください。';
            }
            $message_type = 'alert';
        }
    }
}

$reset_account = null;
if (isset($_GET['password_reset']) && $_GET['password_reset'] === '1') {
    $reset_account = $_SESSION['admin_owner_password_reset'] ?? null;
    unset($_SESSION['admin_owner_password_reset']);
}
$flash_message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);

$users = [];
try {
    $users = $pdo->query(
        "SELECT u.user_id, u.username, u.email, u.role, u.notify_lost, u.created_at, o.name AS owner_name
         FROM users u
         LEFT JOIN owners o ON u.owner_id = o.owner_id
         ORDER BY u.user_id"
    )->fetchAll();
} catch (Exception $e) {
    $message = 'ユーザーまたは飼い主テーブルの必要なカラムがありません。データベース移行を確認してください。';
    $message_type = 'alert';
}
?>
<?php include __DIR__ . '/../templates/header.php'; ?>

<div class="card">
  <h2>🐾 登録ユーザー一覧</h2>
  <p>迷子報告は管理者と「迷子通知ON」のユーザーのサイト内受信箱に届きます。現在、外部メールは送信されません。</p>

  <?php if ($message): ?>
    <div class="<?php echo $message_type === 'alert' ? 'alert' : 'notice'; ?>">
      <?php echo e($message); ?>
    </div>
  <?php endif; ?>
  <?php if ($flash_message): ?>
    <div class="notice"><?php echo e($flash_message); ?></div>
  <?php endif; ?>
  <?php if ($reset_account): ?>
    <div class="notice">
      <h2>飼い主の仮パスワードを再発行しました</h2>
      <p>この仮パスワードは画面を離れると再表示されません。飼い主本人へ安全な方法で伝えてください。</p>
      <p>ログインID: <strong><?php echo e($reset_account['username']); ?></strong></p>
      <p>新しい仮パスワード: <strong><?php echo e($reset_account['temporary_password']); ?></strong></p>
      <p>次回ログイン時に、飼い主本人によるパスワード変更が必要です。</p>
    </div>
  <?php endif; ?>

  <?php if (empty($users)): ?>
    <p>ユーザーが見つかりません。開発用のサンプルデータ登録は、ブラウザーからは実行できません。</p>
  <?php else: ?>
    <p class="table-note">メールアドレスはユーザー情報として保存されます。迷子報告の通知先は「迷子通知ON」の設定で切り替えます。</p>
    <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>ユーザー名</th>
            <th>役割</th>
            <th>紐付く飼い主</th>
            <th>メールアドレス</th>
            <th>迷子通知</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td><?php echo e($u['user_id']); ?></td>
            <td><strong><?php echo e($u['username']); ?></strong></td>
            <td><span class="badge"><?php echo e($u['role']); ?></span></td>
            <td><?php echo e($u['owner_name'] ?? '—'); ?></td>
            <td>
              <form id="user-form-<?php echo e($u['user_id']); ?>" method="post" class="user-row-form">
                <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="user_id" value="<?php echo e($u['user_id']); ?>">
                <input type="email" name="email" value="<?php echo e($u['email'] ?? ''); ?>" placeholder="user@example.com">
              </form>
            </td>
            <td>
              <label class="checkbox-label">
                <input type="checkbox" name="notify_lost" value="1" form="user-form-<?php echo e($u['user_id']); ?>" <?php echo ($u['notify_lost'] ?? 1) ? 'checked' : ''; ?> >
                迷子通知ON
              </label>
            </td>
            <td>
              <button type="submit" form="user-form-<?php echo e($u['user_id']); ?>" class="button">保存</button>
              <?php if ($u['role'] === 'owner'): ?>
                <form method="post" onsubmit="return confirm('この飼い主の現在のパスワードを使えなくし、仮パスワードを再発行します。よろしいですか？');">
                  <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                  <input type="hidden" name="user_id" value="<?php echo e($u['user_id']); ?>">
                  <input type="hidden" name="action" value="reset_owner_password">
                  <button type="submit" class="button-outline">仮パスワード再発行</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <p style="margin-top:20px">
    <a class="button-link" href="<?php echo BASE_URL; ?>/admin/dashboard.php">管理トップへ</a>
  </p>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
