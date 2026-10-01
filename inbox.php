<?php
require_once __DIR__ . '/functions.php';

require_login();

$page_title = '受信箱';
$page_heading = '受信箱';
$page_description = '迷子・発見報告など、このサイト内で受け取った通知を確認できます。';
$user = current_user();
$pdo = get_pdo();
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        http_response_code(400);
        exit('CSRF token mismatch');
    }

    $notification_id = (int)($_POST['notification_id'] ?? 0);
    if ($notification_id > 0 && !empty($user['id'])) {
        $stmt = $pdo->prepare(
            'UPDATE app_notifications SET read_at = CURRENT_TIMESTAMP
             WHERE notification_id = ? AND user_id = ? AND read_at IS NULL'
        );
        $stmt->execute([$notification_id, (int)$user['id']]);
    }

    header('Location: ' . BASE_URL . '/inbox.php');
    exit;
}

$notifications = [];
if (!empty($user['id'])) {
    $stmt = $pdo->prepare(
        'SELECT notification_id, title, body, link_path, created_at, read_at
         FROM app_notifications
         WHERE user_id = ?
         ORDER BY created_at DESC, notification_id DESC'
    );
    $stmt->execute([(int)$user['id']]);
    $notifications = $stmt->fetchAll();
} else {
    $message = 'このログインでは受信箱を利用できません。ユーザー管理に登録されたアカウントでログインしてください。';
}
?>
<?php include __DIR__ . '/templates/header.php'; ?>

<div class="card">
  <?php if ($message): ?>
    <div class="alert"><?php echo e($message); ?></div>
  <?php elseif (empty($notifications)): ?>
    <p>通知はまだありません。</p>
  <?php else: ?>
    <div class="card-list">
      <?php foreach ($notifications as $notification): ?>
        <article class="card-light">
          <div class="section-title">
            <h2><?php echo e($notification['title']); ?></h2>
            <span class="badge"><?php echo $notification['read_at'] ? '確認済み' : '未読'; ?></span>
          </div>
          <p><?php echo e($notification['created_at']); ?></p>
          <p><?php echo nl2br(e($notification['body'])); ?></p>
          <p><a class="button-link" href="<?php echo BASE_URL; ?>/<?php echo e($notification['link_path']); ?>">迷子掲示板を見る</a></p>
          <?php if (!$notification['read_at']): ?>
            <form method="post">
              <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
              <input type="hidden" name="notification_id" value="<?php echo e($notification['notification_id']); ?>">
              <button type="submit" class="button">確認済みにする</button>
            </form>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/templates/footer.php'; ?>
