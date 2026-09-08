<?php
require_once __DIR__ . '/../functions.php';
require_login();
require_role('admin');

$page_title = 'ユーザー管理';
$page_description = '登録ユーザーのメールアドレスと迷子通知設定を管理します。';

$pdo = get_pdo();
$message = '';
$message_type = 'notice';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        $message = '不正なリクエストです。';
        $message_type = 'alert';
    } else {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $email = trim($_POST['email'] ?? '');
        $notify_lost = isset($_POST['notify_lost']) ? 1 : 0;

        try {
            $stmt = $pdo->prepare('UPDATE users SET email = ?, notify_lost = ? WHERE user_id = ?');
            $stmt->execute([$email !== '' ? $email : null, $notify_lost, $user_id]);
            $message = 'ユーザー情報を更新しました。';
        } catch (Exception $e) {
            $message = '更新に失敗しました。メールアドレスが重複していないか確認してください。';
            $message_type = 'alert';
        }
    }
}

$users = [];
try {
    $users = $pdo->query('SELECT user_id, username, email, role, notify_lost, created_at FROM users ORDER BY user_id')->fetchAll();
} catch (Exception $e) {
    $message = 'users テーブルに email カラムがありません。migrate_add_user_email.sql を phpMyAdmin で実行してください。';
    $message_type = 'alert';
}
?>
<?php include __DIR__ . '/../templates/header.php'; ?>

<div class="card">
  <h2>🐾 登録ユーザー一覧</h2>
  <p>迷子報告があったとき、メールアドレスが登録されていて「迷子通知ON」のユーザー全員にメールが送信されます。</p>

  <?php if ($message): ?>
    <div class="<?php echo $message_type === 'alert' ? 'alert' : 'notice'; ?>">
      <?php echo e($message); ?>
    </div>
  <?php endif; ?>

  <?php if (empty($users)): ?>
    <p>ユーザーが見つかりません。<a href="<?php echo BASE_URL; ?>/seed.php">サンプルデータ</a>を投入してください。</p>
  <?php else: ?>
    <p class="table-note">この画面では、迷子通知を受け取るユーザーのメールアドレスと ON/OFF 設定を変更できます。</p>
    <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>ユーザー名</th>
            <th>役割</th>
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
