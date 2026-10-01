<?php
require_once __DIR__ . '/../functions.php';
require_login(); require_role('admin');
$pdo = get_pdo();
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (!verify_csrf($_POST['_csrf'] ?? '')){ $error = '不正な操作'; }
    else {
        if (isset($_POST['add_keyword'])){
            $kw = trim($_POST['keyword'] ?? '');
            $type = $_POST['type'] === 'block' ? 'block' : 'allow';
            if ($kw !== ''){
                $stmt = $pdo->prepare('INSERT INTO news_keywords (keyword,type) VALUES (?,?)');
                $stmt->execute([$kw, $type]);
            }
        } elseif (isset($_POST['update_threshold'])){
            $t = (int)$_POST['threshold'];
            $pdo->prepare("INSERT INTO news_settings (`key`,`value`) VALUES ('animal_score_threshold',?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)")->execute([(string)$t]);
        }
    }
    header('Location: ' . BASE_URL . '/admin/news_keywords.php'); exit;
}
if (isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $pdo->prepare('DELETE FROM news_keywords WHERE id = ?')->execute([$id]);
    header('Location: ' . BASE_URL . '/admin/news_keywords.php'); exit;
}
$keywords = $pdo->query('SELECT * FROM news_keywords ORDER BY type, keyword')->fetchAll(PDO::FETCH_ASSOC);
$threshold = $pdo->query("SELECT `value` FROM news_settings WHERE `key` = 'animal_score_threshold' LIMIT 1")->fetchColumn();
include __DIR__ . '/../templates/header.php';
?>
<div class="card">
  <h2>ニュース キーワード管理</h2>
  <?php if ($error): ?><div class="errors"><ul><li><?php echo e($error); ?></li></ul></div><?php endif; ?>
  <form method="post" class="form-grid">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
    <div class="form-group"><label>キーワード</label><input type="text" name="keyword" value=""></div>
    <div class="form-group"><label>タイプ</label>
      <select name="type"><option value="allow">許可 (関連語)</option><option value="block">除外</option></select>
    </div>
    <button class="button" type="submit" name="add_keyword">追加</button>
  </form>
</div>
<div class="card">
  <h2>設定</h2>
  <form method="post">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
    <label>動物スコア閾値: <input type="number" name="threshold" value="<?php echo e($threshold ?: 1); ?>" style="width:80px;"></label>
    <button class="button" type="submit" name="update_threshold">保存</button>
  </form>
</div>
<div class="card">
  <h2>登録済キーワード</h2>
  <?php if (count($keywords) === 0): ?><p>なし</p><?php else: ?>
    <table>
      <thead><tr><th>ID</th><th>キーワード</th><th>タイプ</th><th>作成</th><th>操作</th></tr></thead>
      <tbody>
        <?php foreach ($keywords as $k): ?>
          <tr>
            <td><?php echo e($k['id']); ?></td>
            <td><?php echo e($k['keyword']); ?></td>
            <td><?php echo e($k['type']); ?></td>
            <td><?php echo e($k['created_at']); ?></td>
            <td><a class="button-link" href="<?php echo BASE_URL; ?>/admin/news_keywords.php?delete=<?php echo e($k['id']); ?>" onclick="return confirm('削除しますか？');">削除</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
