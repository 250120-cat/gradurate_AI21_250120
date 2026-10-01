<?php
require_once __DIR__ . '/../functions.php';

require_login();
require_role('shop');

$current = current_user();
$shop_id = (int)($current['shop_id'] ?? 0);
$page_title = 'ショップ管理';
$page_heading = 'ショップ管理';
$page_description = '自店のペットを管理し、飼い主のログインID発行や仮パスワードの再発行ができます。';
$pdo = get_pdo();
$message = null;
$message_type = 'notice';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        http_response_code(400);
        exit('不正な操作が検出されました。画面を再読み込みしてください。');
    }

    $pet_id = filter_var($_POST['pet_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';
    $pet_name = trim($_POST['pet_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $owner_name = trim($_POST['owner_name'] ?? '');
    $owner_phone = trim($_POST['owner_phone'] ?? '');
    $owner_email = trim($_POST['owner_email'] ?? '');

    if ($action === 'reset_owner_password') {
        if (!$pet_id || $pet_id < 1 || $shop_id < 1) {
            $message = 'ペットの指定が正しくありません。';
            $message_type = 'alert';
        } else {
            try {
                $pdo->beginTransaction();
                $accountStmt = $pdo->prepare(
                    "SELECT u.user_id, u.username
                     FROM pets p
                     INNER JOIN users u ON u.owner_id = p.owner_id AND u.role = 'owner'
                     WHERE p.pet_id = ? AND p.shop_id = ?
                     FOR UPDATE"
                );
                $accountStmt->execute([$pet_id, $shop_id]);
                $account = $accountStmt->fetch();
                if (!$account) {
                    throw new RuntimeException('このペットに紐づく飼い主ログインが見つかりません。');
                }

                $temporary_password = bin2hex(random_bytes(8));
                $updateStmt = $pdo->prepare(
                    'UPDATE users
                     SET password_hash = ?, password_change_required = 1
                     WHERE user_id = ? AND role = ?'
                );
                $updateStmt->execute([
                    password_hash($temporary_password, PASSWORD_DEFAULT),
                    (int)$account['user_id'],
                    'owner',
                ]);
                $pdo->commit();

                add_audit_log(
                    'owner_password_reset',
                    'user',
                    (int)$account['user_id'],
                    'ショップが飼い主アカウントの仮パスワードを再発行しました'
                );
                $_SESSION['shop_owner_password_reset'] = [
                    'username' => $account['username'],
                    'temporary_password' => $temporary_password,
                ];
                redirect(BASE_URL . '/shop/dashboard.php?password_reset=1');
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($error instanceof RuntimeException) {
                    $message = $error->getMessage();
                } else {
                    error_log($error->getMessage());
                    $message = '仮パスワードの再発行に失敗しました。';
                }
                $message_type = 'alert';
            }
        }
    } elseif ($action === 'update_pet_name') {
        if (!$pet_id || $pet_id < 1) {
            $message = 'ペットの指定が正しくありません。';
            $message_type = 'alert';
        } elseif (strlen($pet_name) > 255) {
            $message = 'ペット名は255文字以内で入力してください。';
            $message_type = 'alert';
        } else {
            $stmt = $pdo->prepare('UPDATE pets SET name = ? WHERE pet_id = ? AND shop_id = ?');
            $stmt->execute([$pet_name !== '' ? $pet_name : null, $pet_id, $shop_id]);
            if ($stmt->rowCount() > 0) {
                add_audit_log('pet_name_updated', 'pet', $pet_id, 'ショップ管理からペット名を変更しました');
                $_SESSION['flash_message'] = 'ペット名を更新しました。';
                $_SESSION['flash_type'] = 'notice';
                redirect(BASE_URL . '/shop/dashboard.php');
            }

            $check = $pdo->prepare('SELECT pet_id FROM pets WHERE pet_id = ? AND shop_id = ?');
            $check->execute([$pet_id, $shop_id]);
            if ($check->fetch()) {
                $_SESSION['flash_message'] = 'ペット名は変更されていません。';
                $_SESSION['flash_type'] = 'notice';
                redirect(BASE_URL . '/shop/dashboard.php');
            }
            $message = 'このペットは現在のショップに登録されていません。';
            $message_type = 'alert';
        }
    } elseif (!$pet_id || $pet_id < 1) {
        $message = 'ペットの指定が正しくありません。';
        $message_type = 'alert';
    } elseif ($username === '' || strlen($username) > 100) {
        $message = 'ログインIDは1〜100文字で入力してください。';
        $message_type = 'alert';
    } elseif ($pet_name === '' || strlen($pet_name) > 255) {
        $message = '飼い主が決めたペット名を1〜255文字で入力してください。';
        $message_type = 'alert';
    } elseif ($owner_phone !== '' && !preg_match('/^[0-9\-\+\s]+$/', $owner_phone)) {
        $message = '電話番号は数字、ハイフン、プラス記号、スペースで入力してください。';
        $message_type = 'alert';
    } elseif ($owner_email !== '' && !filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
        $message = 'メールアドレスの形式が正しくありません。';
        $message_type = 'alert';
    } else {
        try {
            $pdo->beginTransaction();

            $petStmt = $pdo->prepare(
                'SELECT pet_id, owner_id
                 FROM pets
                 WHERE pet_id = ? AND shop_id = ?
                 FOR UPDATE'
            );
            $petStmt->execute([$pet_id, $shop_id]);
            $pet = $petStmt->fetch();
            if (!$pet) {
                throw new RuntimeException('このペットは現在のショップに登録されていません。');
            }

            $nameStmt = $pdo->prepare('UPDATE pets SET name = ? WHERE pet_id = ? AND shop_id = ?');
            $nameStmt->execute([$pet_name, $pet_id, $shop_id]);

            $owner_id = $pet['owner_id'] ? (int)$pet['owner_id'] : null;
            if ($owner_id === null) {
                if ($owner_name === '' || $owner_phone === '') {
                    throw new RuntimeException('飼い主が未登録のペットには、飼い主名と電話番号を入力してください。');
                }

                $ownerStmt = $pdo->prepare('INSERT INTO owners (name, phone, email) VALUES (?, ?, ?)');
                $ownerStmt->execute([
                    $owner_name,
                    $owner_phone,
                    $owner_email !== '' ? $owner_email : null,
                ]);
                $owner_id = (int)$pdo->lastInsertId();

                $linkStmt = $pdo->prepare('UPDATE pets SET owner_id = ? WHERE pet_id = ? AND shop_id = ?');
                $linkStmt->execute([$owner_id, $pet_id, $shop_id]);
            }

            $existingAccount = $pdo->prepare('SELECT user_id FROM users WHERE owner_id = ? LIMIT 1');
            $existingAccount->execute([$owner_id]);
            if ($existingAccount->fetch()) {
                throw new RuntimeException('この飼い主にはすでにログインアカウントがあります。');
            }

            $temporary_password = bin2hex(random_bytes(8));
            $insert = $pdo->prepare(
                "INSERT INTO users
                    (username, email, password_hash, role, notify_lost, owner_id, password_change_required)
                 VALUES (?, ?, ?, 'owner', 1, ?, 1)"
            );
            $insert->execute([
                $username,
                $owner_email !== '' ? $owner_email : null,
                password_hash($temporary_password, PASSWORD_DEFAULT),
                $owner_id,
            ]);
            $user_id = (int)$pdo->lastInsertId();
            $pdo->commit();

            add_audit_log(
                'owner_account_created',
                'user',
                $user_id,
                'ショップがペットID ' . $pet_id . ' の飼い主アカウントを発行しました'
            );
            $_SESSION['shop_owner_account_issued'] = [
                'username' => $username,
                'temporary_password' => $temporary_password,
            ];
            redirect(BASE_URL . '/shop/dashboard.php?issued=1');
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error instanceof RuntimeException) {
                $message = $error->getMessage();
            } elseif ($error instanceof PDOException && $error->getCode() === '23000') {
                $message = 'ログインIDがすでに使われているか、この飼い主にアカウントが発行済みです。';
            } else {
                error_log($error->getMessage());
                $message = 'ログインIDの発行に失敗しました。データベース設定を確認してください。';
            }
            $message_type = 'alert';
        }
    }
}

$issued_account = null;
if (isset($_GET['issued']) && $_GET['issued'] === '1') {
    $issued_account = $_SESSION['shop_owner_account_issued'] ?? null;
    unset($_SESSION['shop_owner_account_issued']);
}
$reset_account = null;
if (isset($_GET['password_reset']) && $_GET['password_reset'] === '1') {
    $reset_account = $_SESSION['shop_owner_password_reset'] ?? null;
    unset($_SESSION['shop_owner_password_reset']);
}
$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'notice';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$pets = [];
if ($shop_id > 0) {
    $stmt = $pdo->prepare(
        "SELECT p.pet_id, p.name, p.species, p.code, p.photo_path,
                p.owner_id, o.name AS owner_name, o.phone AS owner_phone,
                o.email AS owner_email, u.username AS owner_username
         FROM pets p
         LEFT JOIN owners o ON p.owner_id = o.owner_id
         LEFT JOIN users u ON u.owner_id = o.owner_id AND u.role = 'owner'
         WHERE p.shop_id = ?
         ORDER BY p.pet_id DESC"
    );
    $stmt->execute([$shop_id]);
    $pets = $stmt->fetchAll();
}

include __DIR__ . '/../templates/header.php';
?>

<div class="card">
  <p><a class="button-link" href="<?php echo BASE_URL; ?>/shop/register_pet.php">新しいペットを登録</a></p>
  <p>お迎え前は種類・写真などを登録し、名前は空欄でも構いません。飼い主が決まったら、ペットごとの「飼い主決定・ログインID発行」から名前・飼い主情報・ログインIDを登録してください。</p>

  <?php if ($message): ?>
    <div class="<?php echo $message_type === 'alert' ? 'alert' : 'notice'; ?>"><?php echo e($message); ?></div>
  <?php endif; ?>
  <?php if ($flash_message): ?>
    <div class="<?php echo $flash_type === 'alert' ? 'alert' : 'notice'; ?>"><?php echo e($flash_message); ?></div>
  <?php endif; ?>

  <?php if ($issued_account): ?>
    <div class="notice">
      <h2>飼い主ログインを発行しました</h2>
      <p>仮パスワードはこの画面を離れると再表示されません。飼い主本人へ安全な方法で伝えてください。</p>
      <p>ログインID: <strong><?php echo e($issued_account['username']); ?></strong></p>
      <p>仮パスワード: <strong><?php echo e($issued_account['temporary_password']); ?></strong></p>
      <p>初回ログイン後、本人によるパスワード変更が必要です。</p>
    </div>
  <?php endif; ?>
  <?php if ($reset_account): ?>
    <div class="notice">
      <h2>仮パスワードを再発行しました</h2>
      <p>この仮パスワードは画面を離れると再表示されません。飼い主本人へ安全な方法で伝えてください。</p>
      <p>ログインID: <strong><?php echo e($reset_account['username']); ?></strong></p>
      <p>新しい仮パスワード: <strong><?php echo e($reset_account['temporary_password']); ?></strong></p>
      <p>次回ログイン時に、飼い主本人によるパスワード変更が必要です。</p>
    </div>
  <?php endif; ?>

  <h2>自店登録ペット</h2>
  <?php if ($shop_id < 1): ?>
    <p class="alert">このショップアカウントにショップIDが設定されていません。管理者へ設定を依頼してください。</p>
  <?php elseif (count($pets) === 0): ?>
    <p>登録済みペットはありません。</p>
  <?php else: ?>
    <div class="card-list">
      <?php foreach ($pets as $pet): ?>
        <div class="card-light">
          <h3><?php echo e($pet['name'] ?: '名前未設定'); ?></h3>
          <p>種類: <?php echo e($pet['species'] ?: '未登録'); ?></p>
          <p>コード: <?php echo e($pet['code']); ?></p>
          <?php if (!empty($pet['photo_path'])): ?>
            <img
              src="<?php echo BASE_URL . '/' . e($pet['photo_path']); ?>"
              alt="<?php echo e($pet['name'] ?: 'ペット写真'); ?>"
              style="width:100%;max-width:220px;height:180px;object-fit:cover;border-radius:12px;margin-bottom:12px;"
            >
          <?php endif; ?>
          <p>飼い主: <?php echo e($pet['owner_name'] ?: '未登録'); ?></p>
          <p><a class="button-link" href="<?php echo BASE_URL; ?>/pet/view.php?code=<?php echo urlencode($pet['code']); ?>">ペット詳細</a></p>

          <details>
            <summary>ペット名を変更</summary>
            <form method="post" class="form-grid" style="margin-top:12px;">
              <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
              <input type="hidden" name="action" value="update_pet_name">
              <input type="hidden" name="pet_id" value="<?php echo e($pet['pet_id']); ?>">
              <div class="form-group">
                <label>ペット名</label>
                <input type="text" name="pet_name" maxlength="255" value="<?php echo e($pet['name'] ?? ''); ?>" placeholder="飼い主が決めた名前">
              </div>
              <button type="submit" class="button">名前を保存</button>
            </form>
          </details>

          <?php if ($pet['owner_username']): ?>
            <p>飼い主ログインID: <strong><?php echo e($pet['owner_username']); ?></strong></p>
            <form method="post" class="form-grid" onsubmit="return confirm('この飼い主の現在のパスワードを使えなくし、仮パスワードを再発行します。よろしいですか？');">
              <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
              <input type="hidden" name="action" value="reset_owner_password">
              <input type="hidden" name="pet_id" value="<?php echo e($pet['pet_id']); ?>">
              <button type="submit" class="button-outline">飼い主の仮パスワードを再発行</button>
            </form>
          <?php else: ?>
            <details>
              <summary>飼い主決定・ログインID発行</summary>
              <form method="post" class="form-grid" style="margin-top:12px;">
                <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="pet_id" value="<?php echo e($pet['pet_id']); ?>">
                <input type="hidden" name="action" value="issue_owner_login">
                <div class="form-group">
                  <label>飼い主が決めたペット名</label>
                  <input type="text" name="pet_name" maxlength="255" value="<?php echo e($pet['name'] ?? ''); ?>" required>
                </div>
                <?php if (!$pet['owner_id']): ?>
                  <div class="form-group">
                    <label>飼い主名</label>
                    <input type="text" name="owner_name" required>
                  </div>
                  <div class="form-group">
                    <label>電話番号</label>
                    <input type="text" name="owner_phone" required>
                  </div>
                  <div class="form-group">
                    <label>メールアドレス（任意）</label>
                    <input type="email" name="owner_email">
                  </div>
                <?php else: ?>
                  <p>登録済み飼い主: <?php echo e($pet['owner_name']); ?> / <?php echo e($pet['owner_phone']); ?></p>
                  <input type="hidden" name="owner_email" value="<?php echo e($pet['owner_email'] ?? ''); ?>">
                <?php endif; ?>
                <div class="form-group">
                  <label>飼い主ログインID</label>
                  <input type="text" name="username" maxlength="100" autocomplete="off" required>
                </div>
                <p class="table-note">仮パスワードは自動発行され、発行後に一度だけ表示されます。初回ログイン時に飼い主が変更します。</p>
                <button type="submit" class="button">この飼い主のログインを発行</button>
              </form>
            </details>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
