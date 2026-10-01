<?php
require_once __DIR__ . '/functions.php';

$page_title = '相談掲示板';
$page_description = 'ペットと迷子に関する相談を投稿し、PDFを添付して共有できます。';
$pdo = get_pdo();

$errors = [];
$success = null;
$pet_code_filter = trim($_GET['pet_code'] ?? '');
$default_pet_code = $pet_code_filter;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        $errors[] = '不正な操作です。';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $pet_code = trim($_POST['pet_code'] ?? '');
        $genre = trim($_POST['genre'] ?? 'general');
        $pdf_path = null;

        if ($title === '') {
            $errors[] = 'タイトルは必須です。';
        }
        if ($description === '') {
            $errors[] = '相談内容を入力してください。';
        }

        if (!empty($_FILES['attachment_pdf']['tmp_name'])) {
            $pdf_path = save_uploaded_pdf($_FILES['attachment_pdf'] ?? [], 'consultation');
            if (!$pdf_path) {
                $errors[] = 'PDFのアップロードに失敗しました。PDFファイルを確認してください。';
            }
        }

        $pet_id = null;
        if ($pet_code !== '') {
            $stmt = $pdo->prepare('SELECT pet_id FROM pets WHERE code = ? LIMIT 1');
            $stmt->execute([$pet_code]);
            $found = $stmt->fetch();
            if ($found) {
                $pet_id = $found['pet_id'];
            } else {
                $errors[] = '指定したペットコードのペットが見つかりません。';
            }
        }

        if (empty($errors)) {
            $current = current_user();
          $stmt = $pdo->prepare('INSERT INTO consultation_threads (pet_id, creator_id, creator_name, creator_email, title, description, status, pdf_path, genre) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
          $stmt->execute([
            $pet_id,
            $current['id'] ?? null,
            $current['username'] ?? 'ゲスト',
            null,
            $title,
            $description,
            'open',
            $pdf_path,
            $genre
          ]);
            header('Location: consultation_thread.php?id=' . $pdo->lastInsertId());
            exit;
        }
    }
}

$status = $_GET['status'] ?? '';
$search = trim($_GET['search'] ?? '');
$genre_filter = trim($_GET['genre'] ?? '');

$sql = 'SELECT ct.*, p.code AS pet_code, p.name AS pet_name, u.username AS creator_username FROM consultation_threads ct LEFT JOIN pets p ON ct.pet_id = p.pet_id LEFT JOIN users u ON ct.creator_id = u.user_id WHERE 1=1';
$params = [];

if ($status !== '') {
    $sql .= ' AND ct.status = ?';
    $params[] = $status;
}
if ($genre_filter !== '') {
  $sql .= ' AND ct.genre = ?';
  $params[] = $genre_filter;
}
if ($pet_code_filter !== '') {
    $sql .= ' AND p.code = ?';
    $params[] = $pet_code_filter;
}
if ($search !== '') {
    $sql .= ' AND (ct.title LIKE ? OR ct.description LIKE ? OR p.name LIKE ? OR p.code LIKE ? OR ct.creator_name LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like]);
}
$sql .= ' ORDER BY ct.updated_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$threads = $stmt->fetchAll();

// load genres from DB for form
try{
  $genreRows = $pdo->query('SELECT slug, name FROM consultation_genres ORDER BY name')->fetchAll();
} catch (Exception $e){
  $genreRows = [];
}
?>
<?php include __DIR__ . '/templates/header.php'; ?>
<div class="card">
  <h2>相談掲示板</h2>
  <p>相談を投稿して、PDF資料を添付しながら情報共有できます。</p>
  <?php if ($success): ?>
    <div class="notice"><?php echo e($success); ?></div>
  <?php endif; ?>
  <?php if ($errors): ?>
    <div class="errors"><ul><?php foreach ($errors as $error): ?><li><?php echo e($error); ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>
  <form method="post" class="form-grid" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
    <div class="form-group"><label>関連ペットコード</label><input type="text" name="pet_code" value="<?php echo e($_POST['pet_code'] ?? $default_pet_code ?? ''); ?>" placeholder="任意"></div>
    <div class="form-group"><label>ジャンル</label>
      <select name="genre">
        <?php if (empty($genreRows)): ?>
          <option value="general">一般</option>
          <option value="health">健康相談</option>
          <option value="lost">迷子・捜索</option>
          <option value="behavior">行動・しつけ</option>
          <option value="other">その他</option>
        <?php else: ?>
          <?php foreach ($genreRows as $gr): ?>
            <option value="<?php echo e($gr['slug']); ?>" <?php echo (($_POST['genre'] ?? '') === $gr['slug']) ? 'selected' : ''; ?>><?php echo e($gr['name']); ?></option>
          <?php endforeach; ?>
        <?php endif; ?>
      </select>
    </div>
    <div class="form-group"><label>タイトル</label><input type="text" name="title" value="<?php echo e($_POST['title'] ?? ''); ?>" required></div>
    <div class="form-group"><label>相談内容</label><textarea name="description" rows="6" required><?php echo e($_POST['description'] ?? ''); ?></textarea></div>
    <div class="form-group"><label>PDF添付 (任意)</label><input type="file" name="attachment_pdf" accept="application/pdf"></div>
    <button type="submit" class="button">相談を投稿</button>
  </form>
</div>

<div class="card">
  <h2>相談一覧</h2>
  <form method="get" class="form-grid">
    <div class="form-group"><label>検索</label><input type="text" name="search" value="<?php echo e($search); ?>" placeholder="タイトル、本文、コードなど"></div>
    <div class="form-group"><label>関連ペットコード</label><input type="text" name="pet_code" value="<?php echo e($pet_code_filter); ?>" placeholder="例: P1234567890"></div>
    <div class="form-group"><label>ジャンル</label>
      <select name="genre">
        <option value="">すべて</option>
        <option value="general" <?php echo $genre_filter==='general' ? 'selected' : ''; ?>>一般</option>
        <option value="health" <?php echo $genre_filter==='health' ? 'selected' : ''; ?>>健康相談</option>
        <option value="lost" <?php echo $genre_filter==='lost' ? 'selected' : ''; ?>>迷子・捜索</option>
        <option value="behavior" <?php echo $genre_filter==='behavior' ? 'selected' : ''; ?>>行動・しつけ</option>
        <option value="other" <?php echo $genre_filter==='other' ? 'selected' : ''; ?>>その他</option>
      </select>
    </div>
    <div class="form-group"><label>状態</label><select name="status"><option value="">すべて</option><option value="open" <?php echo $status==='open' ? 'selected' : ''; ?>>open</option><option value="closed" <?php echo $status==='closed' ? 'selected' : ''; ?>>closed</option></select></div>
    <button type="submit" class="button">絞り込み</button>
  </form>
</div>

<?php if (count($threads) === 0): ?>
  <div class="card"><p>相談はまだ投稿されていません。</p></div>
<?php else: ?>
  <div class="card-list">
    <?php foreach ($threads as $thread): ?>
      <div class="card-light">
        <div class="section-title"><h3><?php echo e($thread['title']); ?></h3><span class="badge"><?php echo e($thread['status']); ?></span></div>
        <p>投稿者: <?php echo e($thread['creator_name'] ?? $thread['creator_username'] ?? '匿名'); ?></p>
        <p>関連ペット: <?php echo e($thread['pet_name'] ? $thread['pet_name'] . ' (' . $thread['pet_code'] . ')' : 'なし'); ?></p>
        <p>最終更新: <?php echo e($thread['updated_at']); ?></p>
        <p><?php echo nl2br(e(mb_strimwidth($thread['description'], 0, 240, '...'))); ?></p>
        <?php if ($thread['pdf_path']): ?>
          <p><a class="button-link" href="<?php echo BASE_URL . '/' . e($thread['pdf_path']); ?>" target="_blank">PDFを見る</a></p>
        <?php endif; ?>
        <p><a class="button-link" href="consultation_thread.php?id=<?php echo e($thread['thread_id']); ?>">相談を見る</a></p>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/templates/footer.php'; ?>
