<?php
require_once __DIR__ . '/../functions.php';
require_login(); require_role('admin');
$pdo = get_pdo();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (!verify_csrf($_POST['_csrf'] ?? '')){ $error = '不正な操作'; }
    else{
        $url = trim($_POST['url'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $enabled = isset($_POST['enabled']) ? 1 : 0;
        if ($url === ''){ $error = 'URLを入力してください'; }
        else{
            $stmt = $pdo->prepare('INSERT INTO news_feeds (url, title, enabled) VALUES (?, ?, ?)');
            $stmt->execute([$url, $title, $enabled]);
            header('Location: ' . BASE_URL . '/admin/news_feeds.php'); exit;
        }
    }
}

if (isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM news_feeds WHERE feed_id = ?');
    $stmt->execute([$id]);
    header('Location: ' . BASE_URL . '/admin/news_feeds.php'); exit;
}

$feeds = $pdo->query('SELECT nf.*, COUNT(nc.item_id) AS cached_count FROM news_feeds nf LEFT JOIN news_cache nc ON nf.feed_id = nc.feed_id GROUP BY nf.feed_id ORDER BY nf.feed_id DESC')->fetchAll();
include __DIR__ . '/../templates/header.php';
?>
<div class="card">
  <h2>ニュースフィード管理</h2>
  <p><a class="button-link" href="<?php echo BASE_URL; ?>/admin/fetch_news.php">全フィードを今すぐ取得</a></p>
  <?php if ($error): ?><div class="errors"><ul><li><?php echo e($error); ?></li></ul></div><?php endif; ?>
  <form method="post" class="form-grid">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
    <div class="form-group"><label>フィードURL</label><input type="text" name="url" value=""></div>
    <div class="form-group"><label>タイトル（任意）</label><input type="text" name="title" value=""></div>
    <div class="form-group"><label>有効</label><input type="checkbox" name="enabled" checked></div>
    <button class="button" type="submit">登録</button>
  </form>
</div>
<div class="card">
  <h2>登録済みフィード</h2>
  <?php if (count($feeds)===0): ?><p>登録されていません。</p><?php else: ?>
    <table>
      <thead><tr><th>ID</th><th>URL</th><th>タイトル</th><th>有効</th><th>最終取得</th><th>取得記事</th><th>操作</th></tr></thead>
      <tbody>
        <?php foreach ($feeds as $f): ?>
        <tr>
            <td><?php echo e($f['feed_id']); ?></td>
            <td style="max-width:320px;word-break:break-all"><?php echo e($f['url']); ?></td>
            <td><?php echo e($f['title'] ?: '未設定'); ?></td>
            <td><?php echo $f['enabled'] ? 'yes' : 'no'; ?></td>
            <td><?php echo e($f['last_fetched'] ?? '未'); ?></td>
            <td><?php echo e($f['cached_count']); ?></td>
            <td style="max-width:240px;word-break:break-word;color:#b91c1c">
              <?php $err = $f['last_error'] ?? ''; ?>
              <?php if ($err === null || $err === ''): ?>
                —
              <?php else: ?>
                <span class="err-snippet"><?php echo e(mb_strimwidth($err, 0, 120, '...')); ?></span>
                <a href="#" class="err-toggle" data-feed="<?php echo e($f['feed_id']); ?>" style="margin-left:8px;">詳細</a>
                <div class="err-full" id="err-full-<?php echo e($f['feed_id']); ?>" style="display:none;white-space:pre-wrap;margin-top:6px;background:#fff4f4;padding:8px;border-radius:6px;border:1px solid #ffd6d6;color:#6b0000;">
                  <?php echo nl2br(e($err)); ?>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <a class="button-link" href="<?php echo BASE_URL; ?>/admin/news_feeds.php?delete=<?php echo e($f['feed_id']); ?>" onclick="return confirm('削除しますか？');">削除</a>
              <a class="button-link" href="<?php echo BASE_URL; ?>/admin/fetch_news.php?feed=<?php echo e($f['feed_id']); ?>" onclick="return confirm('このフィードを今すぐ取得しますか？');">今すぐ取得</a>
            </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<script>
  (function(){
    document.addEventListener('click', function(e){
      var t = e.target;
      if (t && t.classList && t.classList.contains('err-toggle')){
        e.preventDefault();
        var feed = t.getAttribute('data-feed');
        var el = document.getElementById('err-full-' + feed);
        if (!el) return;
        if (el.style.display === 'none' || el.style.display === ''){
          el.style.display = 'block';
          t.textContent = '閉じる';
        } else {
          el.style.display = 'none';
          t.textContent = '詳細';
        }
      }
    });
  })();
</script>
<?php include __DIR__ . '/../templates/footer.php'; ?>
