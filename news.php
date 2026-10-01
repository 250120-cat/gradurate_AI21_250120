<?php
require_once __DIR__ . '/functions.php';

$page_title = '動物ニュース';
$page_heading = '動物ニュース';
$page_description = '独自の紹介文と出典リンクで、動物に関する情報をお届けします。';

$pdo = get_pdo();
$table_check = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'editorial_news'"
);
if (!$table_check->fetchColumn()) {
    include __DIR__ . '/templates/header.php';
    echo '<div class="card"><p>ニュース記事の準備中です。</p></div>';
    include __DIR__ . '/templates/footer.php';
    exit;
}
$image_column_check = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'editorial_news' AND column_name = 'image_path'"
);
if (!$image_column_check->fetchColumn()) {
    include __DIR__ . '/templates/header.php';
    echo '<div class="card"><p>ニュース記事の準備中です。</p></div>';
    include __DIR__ . '/templates/footer.php';
    exit;
}

$categories = ['保護・譲渡', '健康・医療', '飼育・暮らし', '制度・地域', 'その他'];
$category = trim($_GET['category'] ?? '');
$search = trim($_GET['q'] ?? '');
$conditions = ["status = 'published'", 'published_at IS NOT NULL', 'published_at <= CURRENT_TIMESTAMP'];
$params = [];

if ($category !== '' && in_array($category, $categories, true)) {
    $conditions[] = 'category = ?';
    $params[] = $category;
} else {
    $category = '';
}
if ($search !== '') {
    $conditions[] = '(title LIKE ? OR summary LIKE ? OR source_name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$stmt = $pdo->prepare(
    'SELECT article_id, title, summary, image_path, source_name, source_url, category, published_at
     FROM editorial_news
     WHERE ' . implode(' AND ', $conditions) . '
     ORDER BY published_at DESC, article_id DESC'
);
$stmt->execute($params);
$articles = $stmt->fetchAll();

include __DIR__ . '/templates/header.php';
?>

<div class="card">
  <form id="news-filter-form" method="get" class="form-grid">
    <div class="form-group">
      <label for="news-search">キーワード</label>
      <input id="news-search" type="search" name="q" value="<?php echo e($search); ?>" placeholder="タイトル・紹介文・出典">
    </div>
    <div class="form-group">
      <label for="news-category">カテゴリ</label>
      <select id="news-category" name="category">
        <option value="">すべて</option>
        <?php foreach ($categories as $item): ?>
          <option value="<?php echo e($item); ?>" <?php echo $category === $item ? 'selected' : ''; ?>><?php echo e($item); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="button">検索</button>
    <?php if ($search !== '' || $category !== ''): ?>
      <a class="button-link" href="<?php echo BASE_URL; ?>/news.php">条件をクリア</a>
    <?php endif; ?>
  </form>
</div>

<?php if (!$articles): ?>
  <div class="card">
    <p><?php echo ($search !== '' || $category !== '') ? '条件に合うニュースはありません。' : '公開中のニュースはまだありません。'; ?></p>
  </div>
<?php else: ?>
  <p><?php echo count($articles); ?>件のニュース</p>
  <div class="card-list">
    <?php foreach ($articles as $article): ?>
      <article class="card-light">
        <?php if (!empty($article['image_path'])): ?>
          <img class="editorial-news-image" src="<?php echo BASE_URL . '/' . e($article['image_path']); ?>" alt="">
        <?php endif; ?>
        <div class="section-title">
          <h2><?php echo e($article['title']); ?></h2>
          <span class="badge"><?php echo e($article['category']); ?></span>
        </div>
        <p><?php echo nl2br(e($article['summary'])); ?></p>
        <p class="news-meta">
          <?php echo e(date('Y-m-d', strtotime($article['published_at']))); ?>
          ・出典: <?php echo e($article['source_name']); ?>
        </p>
        <p><a href="<?php echo e($article['source_url']); ?>" target="_blank" rel="noopener noreferrer">出典元の記事を読む</a></p>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/templates/footer.php'; ?>
