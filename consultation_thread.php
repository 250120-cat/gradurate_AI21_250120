<?php
require_once __DIR__ . '/functions.php';
require_login();

$page_title = '相談詳細';
$page_description = '相談トピックの詳細とコメントを表示します。';
$pdo = get_pdo();

$thread_id = $_GET['id'] ?? null;
if (!$thread_id) {
    http_response_code(400);
    exit('トピックIDが指定されていません。');
}

$stmt = $pdo->prepare('SELECT ct.*, p.code AS pet_code, p.name AS pet_name, u.username AS creator_username FROM consultation_threads ct LEFT JOIN pets p ON ct.pet_id = p.pet_id LEFT JOIN users u ON ct.creator_id = u.user_id WHERE ct.thread_id = ? LIMIT 1');
$stmt->execute([$thread_id]);
$thread = $stmt->fetch();
if (!$thread) {
    http_response_code(404);
    exit('相談トピックが見つかりません。');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        http_response_code(400);
        exit('CSRF token mismatch');
    }

    $action = $_POST['action'] ?? '';
    if ($action === 'post_reply') {
        if ($thread['status'] === 'closed') {
            $error = 'この相談はクローズされています。コメントを追加できません。';
        } else {
            $message = trim($_POST['message'] ?? '');
            $pdf_path = null;
            if ($message === '') {
                $error = 'コメントを入力してください。';
            } else {
                if (!empty($_FILES['reply_pdf']['tmp_name'])) {
                    $pdf_path = save_uploaded_pdf($_FILES['reply_pdf'] ?? [], 'consultation');
                    if (!$pdf_path) {
                        $error = 'PDFアップロードに失敗しました。';
                    }
                }

                if (empty($error)) {
                    $current = current_user();
                    $stmt = $pdo->prepare('INSERT INTO consultation_posts (thread_id, user_id, author_name, author_email, message, pdf_path) VALUES (?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $thread_id,
                        $current['id'] ?? null,
                        $current['username'] ?? null,
                        null,
                        $message,
                        $pdf_path
                    ]);
                    header('Location: consultation_thread.php?id=' . urlencode($thread_id));
                    exit;
                }
            }
        }
    }

    if ($action === 'update_status' && has_role('admin')) {
        $new_status = $_POST['status'] ?? '';
        $allowed = ['open', 'closed'];
        if (in_array($new_status, $allowed, true)) {
            $stmt = $pdo->prepare('UPDATE consultation_threads SET status = ? WHERE thread_id = ?');
            $stmt->execute([$new_status, $thread_id]);
            header('Location: consultation_thread.php?id=' . urlencode($thread_id));
            exit;
        }
    }

    if ($action === 'delete_thread' && has_role('admin')) {
        $stmt = $pdo->prepare('DELETE FROM consultation_threads WHERE thread_id = ?');
        $stmt->execute([$thread_id]);
        header('Location: consultation.php');
        exit;
    }
}

$stmt = $pdo->prepare('SELECT cp.*, u.username AS author_username FROM consultation_posts cp LEFT JOIN users u ON cp.user_id = u.user_id WHERE cp.thread_id = ? ORDER BY cp.created_at ASC');
$stmt->execute([$thread_id]);
$posts = $stmt->fetchAll();
?>
<?php include __DIR__ . '/templates/header.php'; ?>
<div class="card">
  <h2><?php echo e($thread['title']); ?></h2>
  <p>投稿者: <?php echo e($thread['creator_name'] ?? $thread['creator_username'] ?? '匿名'); ?></p>
  <p>ジャンル: <?php echo e($thread['genre'] ?? '一般'); ?></p>
  <p>状態: <?php echo e($thread['status']); ?></p>
  <p>関連ペット: <?php echo e($thread['pet_name'] ? $thread['pet_name'] . ' (' . $thread['pet_code'] . ')' : 'なし'); ?> <?php if ($thread['pet_id']): ?><a href="<?php echo BASE_URL; ?>/pet/view.php?code=<?php echo urlencode($thread['pet_code']); ?>">ペット詳細へ</a><?php endif; ?></p>
  <p><?php echo nl2br(e($thread['description'])); ?></p>
  <?php if ($thread['pdf_path']): ?>
    <p><a class="button-link" href="<?php echo BASE_URL . '/' . e($thread['pdf_path']); ?>" target="_blank">相談添付PDFを見る</a></p>
  <?php endif; ?>
  <p><a class="button-link" href="<?php echo BASE_URL; ?>/consultation.php?pet_code=<?php echo urlencode($thread['pet_code']); ?>">このペットで新しい相談を作成</a></p>
</div>

<div class="card">
  <h2>コメント</h2>
  <?php if (count($posts) === 0): ?>
    <p>まだコメントはありません。</p>
  <?php else: ?>
    <?php foreach ($posts as $post): ?>
      <div class="card-light" style="margin-bottom: 14px;">
        <p><strong><?php echo e($post['author_name'] ?? $post['author_username'] ?? '匿名'); ?></strong> - <?php echo e($post['created_at']); ?></p>
        <p><?php echo nl2br(e($post['message'])); ?></p>
        <?php if ($post['pdf_path']): ?>
          <p><a class="button-link" href="<?php echo BASE_URL . '/' . e($post['pdf_path']); ?>" target="_blank">添付PDFを見る</a></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card">
  <h2>コメントを投稿</h2>
  <?php if ($thread['status'] === 'closed'): ?>
    <div class="notice">この相談はクローズされています。コメントの追加はできません。</div>
  <?php else: ?>
    <?php if (!empty($error)): ?><div class="errors"><ul><li><?php echo e($error); ?></li></ul></div><?php endif; ?>
    <form method="post" class="form-grid" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="post_reply">
      <div class="form-group"><label>コメント</label><textarea name="message" rows="5" required><?php echo e($_POST['message'] ?? ''); ?></textarea></div>
      <div class="form-group"><label>PDF添付 (任意)</label><input type="file" name="reply_pdf" accept="application/pdf"></div>
      <button type="submit" class="button">コメントを投稿</button>
    </form>
  <?php endif; ?>
</div>
<?php if (has_role('admin')): ?>
  <div class="card">
    <h2>管理操作</h2>
    <form method="post" class="form-grid" style="margin-bottom: 14px;">
      <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="update_status">
      <div class="form-group"><label>ステータス</label><select name="status"><option value="open" <?php echo $thread['status'] === 'open' ? 'selected' : ''; ?>>open</option><option value="closed" <?php echo $thread['status'] === 'closed' ? 'selected' : ''; ?>>closed</option></select></div>
      <button type="submit" class="button">ステータスを更新</button>
    </form>
    <form method="post" onsubmit="return confirm('本当にこの相談を削除しますか？');">
      <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
      <input type="hidden" name="action" value="delete_thread">
      <button type="submit" class="button" style="background: #dc2626;">相談を削除</button>
    </form>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/templates/footer.php'; ?>
