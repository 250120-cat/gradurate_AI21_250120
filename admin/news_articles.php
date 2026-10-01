<?php
require_once __DIR__ . '/../functions.php';

require_login();
require_role('admin');

$page_title = '動物ニュース管理';
$page_heading = '動物ニュース管理';
$page_description = '独自に作成した要約と出典リンクを登録し、ニュースを公開できます。';

$pdo = get_pdo();
$table_check = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'editorial_news'"
);
if (!$table_check->fetchColumn()) {
    include __DIR__ . '/../templates/header.php';
    echo '<div class="card"><div class="alert">ニュース記事用のテーブルがありません。phpMyAdminで migrate_add_editorial_news.sql を実行してください。</div></div>';
    include __DIR__ . '/../templates/footer.php';
    exit;
}
$image_column_check = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = 'editorial_news' AND column_name = 'image_path'"
);
if (!$image_column_check->fetchColumn()) {
    include __DIR__ . '/../templates/header.php';
    echo '<div class="card"><div class="alert">ニュース画像用の列がありません。phpMyAdminで migrate_add_editorial_news_image.sql を実行してください。</div></div>';
    include __DIR__ . '/../templates/footer.php';
    exit;
}

$categories = [
    '保護・譲渡' => '保護・譲渡',
    '健康・医療' => '健康・医療',
    '飼育・暮らし' => '飼育・暮らし',
    '制度・地域' => '制度・地域',
    'その他' => 'その他',
];
$statuses = ['draft' => '下書き', 'published' => '公開'];
$errors = [];
$message = null;
$form = [
    'article_id' => 0,
    'title' => '',
    'summary' => '',
    'image_path' => null,
    'source_name' => '',
    'source_url' => '',
    'category' => 'その他',
    'status' => 'draft',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        http_response_code(400);
        exit('CSRF token mismatch');
    }

    $action = $_POST['action'] ?? '';
    $article_id = filter_input(INPUT_POST, 'article_id', FILTER_VALIDATE_INT);

    if ($action === 'delete') {
        if (!$article_id || $article_id < 1) {
            $errors[] = '削除する記事が指定されていません。';
        } else {
            $image_stmt = $pdo->prepare('SELECT image_path FROM editorial_news WHERE article_id = ? LIMIT 1');
            $image_stmt->execute([$article_id]);
            $image_path = $image_stmt->fetchColumn();
            $stmt = $pdo->prepare('DELETE FROM editorial_news WHERE article_id = ?');
            $stmt->execute([$article_id]);
            if ($stmt->rowCount() !== 1) {
                $errors[] = '削除する記事が見つかりません。';
            } else {
                if ($image_path && !delete_uploaded_file($image_path, 'news')) {
                    $_SESSION['news_image_cleanup_warning'] = true;
                }
                header('Location: ' . BASE_URL . '/admin/news_articles.php?deleted=1');
                exit;
            }
        }
    } elseif ($action === 'save') {
        $form = [
            'article_id' => $article_id ?: 0,
            'title' => trim($_POST['title'] ?? ''),
            'summary' => trim($_POST['summary'] ?? ''),
            'image_path' => null,
            'source_name' => trim($_POST['source_name'] ?? ''),
            'source_url' => trim($_POST['source_url'] ?? ''),
            'category' => $_POST['category'] ?? '',
            'status' => $_POST['status'] ?? '',
        ];

        $source_url = filter_var($form['source_url'], FILTER_VALIDATE_URL);
        $source_scheme = $source_url ? strtolower((string)parse_url($source_url, PHP_URL_SCHEME)) : '';

        if ($form['title'] === '' || mb_strlen($form['title']) > 255) {
            $errors[] = 'タイトルを入力してください（255文字以内）。';
        }
        if ($form['summary'] === '') {
            $errors[] = '独自に作成した要約を入力してください。';
        }
        if ($form['source_name'] === '' || mb_strlen($form['source_name']) > 255) {
            $errors[] = '出典名を入力してください（255文字以内）。';
        }
        if (!$source_url || !in_array($source_scheme, ['http', 'https'], true)) {
            $errors[] = '出典URLには http または https のURLを入力してください。';
        } elseif (mb_strlen($form['source_url']) > 2048) {
            $errors[] = '出典URLは2048文字以内で入力してください。';
        }
        if (!isset($categories[$form['category']])) {
            $errors[] = 'カテゴリを選択してください。';
        }
        if (!isset($statuses[$form['status']])) {
            $errors[] = '公開状態を選択してください。';
        }
        if ($form['article_id'] && $form['article_id'] < 1) {
            $errors[] = '記事IDが正しくありません。';
        }

        $existing_article = null;
        $existing_image_path = null;
        if ($form['article_id'] > 0) {
            $existing_stmt = $pdo->prepare('SELECT published_at, image_path FROM editorial_news WHERE article_id = ? LIMIT 1');
            $existing_stmt->execute([$form['article_id']]);
            $existing_article = $existing_stmt->fetch();
            if (!$existing_article) {
                $errors[] = '編集する記事が見つかりません。';
            } else {
                $existing_image_path = $existing_article['image_path'];
                $form['image_path'] = $existing_image_path;
            }
        }

        if (!$errors) {
            $published_at = null;
            if ($form['article_id']) {
                if ($form['status'] === 'published') {
                    $published_at = $existing_article['published_at'] ?: date('Y-m-d H:i:s');
                }
            } elseif ($form['status'] === 'published') {
                $published_at = date('Y-m-d H:i:s');
            }

            $image_path = $existing_image_path;
            $remove_image = isset($_POST['remove_image']);
            $uploaded_image = null;
            $image_file = $_FILES['article_image'] ?? null;
            $has_new_image = $image_file && ($image_file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

            if (!$errors && $has_new_image) {
                if (($image_file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    $errors[] = '画像のアップロードに失敗しました。';
                } else {
                    $uploaded_image = save_uploaded_image($image_file, 'news');
                    if (!$uploaded_image) {
                        $errors[] = 'JPEG、PNG、WebP形式で5MB以下の画像を選んでください。';
                    } else {
                        $image_path = $uploaded_image;
                    }
                }
            } elseif (!$errors && $remove_image) {
                $image_path = null;
            }

            if (!$errors && $form['article_id']) {
                $stmt = $pdo->prepare(
                    'UPDATE editorial_news
                     SET title = ?, summary = ?, image_path = ?, source_name = ?, source_url = ?,
                         category = ?, status = ?, published_at = ?
                     WHERE article_id = ?'
                );
                $stmt->execute([
                    $form['title'],
                    $form['summary'],
                    $image_path,
                    $form['source_name'],
                    $form['source_url'],
                    $form['category'],
                    $form['status'],
                    $published_at,
                    $form['article_id'],
                ]);
                $saved_id = $form['article_id'];
            } elseif (!$errors) {
                $stmt = $pdo->prepare(
                    'INSERT INTO editorial_news
                     (title, summary, image_path, source_name, source_url, category, status, published_at, author_user_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $form['title'],
                    $form['summary'],
                    $image_path,
                    $form['source_name'],
                    $form['source_url'],
                    $form['category'],
                    $form['status'],
                    $published_at,
                    current_user()['id'] ?? null,
                ]);
                $saved_id = (int)$pdo->lastInsertId();
            }

            if (!$errors) {
                if ($existing_image_path && $existing_image_path !== $image_path) {
                    if (!delete_uploaded_file($existing_image_path, 'news')) {
                        $_SESSION['news_image_cleanup_warning'] = true;
                    }
                }

                $form['image_path'] = $image_path;
                if (function_exists('add_audit_log')) {
                    add_audit_log('editorial_news_saved', 'editorial_news', $saved_id, '動物ニュースを保存しました');
                }
                header('Location: ' . BASE_URL . '/admin/news_articles.php?saved=1');
                exit;
            }
        }
    } else {
        $errors[] = '不明な操作です。';
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if (!$edit_id || $edit_id < 1) {
        http_response_code(400);
        exit('記事IDが正しくありません。');
    }

    $stmt = $pdo->prepare('SELECT * FROM editorial_news WHERE article_id = ? LIMIT 1');
    $stmt->execute([$edit_id]);
    $article = $stmt->fetch();
    if (!$article) {
        http_response_code(404);
        exit('記事が見つかりません。');
    }
    $form = array_merge($form, $article);
}

$articles = $pdo->query(
    'SELECT article_id, title, image_path, source_name, category, status, published_at, updated_at
     FROM editorial_news ORDER BY updated_at DESC, article_id DESC'
)->fetchAll();
$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
$deleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';
$image_cleanup_warning = !empty($_SESSION['news_image_cleanup_warning']);
unset($_SESSION['news_image_cleanup_warning']);
?>
<?php include __DIR__ . '/../templates/header.php'; ?>

<div class="card">
  <h2><?php echo $form['article_id'] ? 'ニュースを編集' : 'ニュースを作成'; ?></h2>
  <p>記事の文章は自分の言葉で要約し、元記事の出典名とリンクを明記してください。記事本文や長い抜粋の転載は避けてください。</p>
  <?php if ($saved): ?><div class="notice">ニュースを保存しました。</div><?php endif; ?>
  <?php if ($deleted): ?><div class="notice">ニュースを削除しました。</div><?php endif; ?>
  <?php if ($image_cleanup_warning): ?><div class="alert">記事は保存しましたが、古い画像ファイルを削除できませんでした。</div><?php endif; ?>
  <?php if ($errors): ?>
    <div class="errors"><ul><?php foreach ($errors as $error): ?><li><?php echo e($error); ?></li><?php endforeach; ?></ul></div>
  <?php endif; ?>

  <form method="post" class="form-grid" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="article_id" value="<?php echo e($form['article_id']); ?>">
    <div class="form-group">
      <label for="article-title">タイトル</label>
      <input id="article-title" type="text" name="title" maxlength="255" value="<?php echo e($form['title']); ?>" required>
    </div>
    <div class="form-group">
      <label for="article-summary">独自に作成した要約・紹介文</label>
      <textarea id="article-summary" name="summary" rows="5" required><?php echo e($form['summary']); ?></textarea>
    </div>
    <?php if (!empty($form['image_path'])): ?>
      <div class="form-group">
        <label>現在の画像</label>
        <img class="editorial-news-image-preview" src="<?php echo BASE_URL . '/' . e($form['image_path']); ?>" alt="">
        <label class="checkbox-label">
          <input type="checkbox" name="remove_image" value="1">
          現在の画像を削除する
        </label>
      </div>
    <?php endif; ?>
    <div class="form-group">
      <label for="article-image">ニュース画像（任意）</label>
      <input id="article-image" type="file" name="article_image" accept="image/jpeg,image/png,image/webp">
      <small>JPEG、PNG、WebP形式、5MB以下。新しい画像を選ぶと現在の画像と差し替わります。</small>
    </div>
    <div class="form-group">
      <label for="article-category">カテゴリ</label>
      <select id="article-category" name="category">
        <?php foreach ($categories as $value => $label): ?>
          <option value="<?php echo e($value); ?>" <?php echo $form['category'] === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="article-source-name">出典名</label>
      <input id="article-source-name" type="text" name="source_name" maxlength="255" value="<?php echo e($form['source_name']); ?>" required>
    </div>
    <div class="form-group">
      <label for="article-source-url">出典URL</label>
      <input id="article-source-url" type="url" name="source_url" maxlength="2048" value="<?php echo e($form['source_url']); ?>" placeholder="https://example.com/article" required>
    </div>
    <div class="form-group">
      <label for="article-status">公開状態</label>
      <select id="article-status" name="status">
        <?php foreach ($statuses as $value => $label): ?>
          <option value="<?php echo e($value); ?>" <?php echo $form['status'] === $value ? 'selected' : ''; ?>><?php echo e($label); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="button">保存</button>
    <?php if ($form['article_id']): ?>
      <a class="button-link" href="<?php echo BASE_URL; ?>/admin/news_articles.php">新規作成へ</a>
    <?php endif; ?>
  </form>
</div>

<div class="card">
  <h2>登録済みニュース</h2>
  <?php if (!$articles): ?>
    <p>ニュースはまだ登録されていません。</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="data-table">
        <thead><tr><th>タイトル</th><th>カテゴリ</th><th>出典</th><th>状態</th><th>更新日時</th><th>操作</th></tr></thead>
        <tbody>
          <?php foreach ($articles as $article): ?>
            <tr>
              <td>
                <?php if (!empty($article['image_path'])): ?>
                  <img class="editorial-news-image-preview" src="<?php echo BASE_URL . '/' . e($article['image_path']); ?>" alt="">
                <?php endif; ?>
                <?php echo e($article['title']); ?>
              </td>
              <td><?php echo e($article['category']); ?></td>
              <td><?php echo e($article['source_name']); ?></td>
              <td><?php echo e($statuses[$article['status']] ?? $article['status']); ?></td>
              <td><?php echo e($article['updated_at']); ?></td>
              <td>
                <a class="button-link" href="<?php echo BASE_URL; ?>/admin/news_articles.php?edit=<?php echo e($article['article_id']); ?>">編集</a>
                <form method="post" style="display:inline" onsubmit="return confirm('この記事を削除しますか？');">
                  <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="article_id" value="<?php echo e($article['article_id']); ?>">
                  <button type="submit" class="button">削除</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
