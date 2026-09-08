<?php
require_once __DIR__ . '/../functions.php';
require_login();
require_role('admin');
$pdo = get_pdo();
$error = null;

// Add or update
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
    if (!verify_csrf($_POST['_csrf'] ?? '')){ $error = '不正な操作'; }
    else {
        $name = trim($_POST['name'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $id = $_POST['id'] ?? null;
        if ($name === '' || $slug === '') { $error = '名前とスラッグは必須です。'; }
        else {
            if ($id){
                $stmt = $pdo->prepare('UPDATE consultation_genres SET name = ?, slug = ? WHERE genre_id = ?');
                $stmt->execute([$name, $slug, $id]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO consultation_genres (name, slug) VALUES (?, ?)');
                $stmt->execute([$name, $slug]);
            }
            header('Location: genres.php'); exit;
        }
    }
}

// delete
if (isset($_GET['delete'])){
    $id = (int)$_GET['delete'];
    $stmt = $pdo->prepare('DELETE FROM consultation_genres WHERE genre_id = ?');
    $stmt->execute([$id]);
    header('Location: genres.php'); exit;
}

$genres = $pdo->query('SELECT * FROM consultation_genres ORDER BY name')->fetchAll();
include __DIR__ . '/../templates/header.php';
?>
<div class="card">
  <h2>相談ジャンル管理</h2>
  <?php if ($error): ?><div class="errors"><ul><li><?php echo e($error); ?></li></ul></div><?php endif; ?>
  <form method="post" class="form-grid">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
    <input type="hidden" name="id" value="<?php echo e($_GET['edit'] ?? ''); ?>">
    <div class="form-group"><label>名前</label><input type="text" name="name" value="<?php echo e($_GET['name'] ?? ''); ?>"></div>
    <div class="form-group"><label>slug（英字）</label><input type="text" name="slug" value="<?php echo e($_GET['slug'] ?? ''); ?>"></div>
    <button class="button" type="submit">保存</button>
  </form>
</div>
<div class="card">
  <h2>既存ジャンル</h2>
  <?php if (count($genres)===0): ?><p>登録されていません。</p><?php else: ?>
    <table>
      <thead><tr><th>ID</th><th>名前</th><th>スラッグ</th><th>操作</th></tr></thead>
      <tbody>
        <?php foreach ($genres as $g): ?>
          <tr>
            <td><?php echo e($g['genre_id']); ?></td>
            <td><?php echo e($g['name']); ?></td>
            <td><?php echo e($g['slug']); ?></td>
            <td>
              <a class="button-link" href="genres.php?edit=<?php echo e($g['genre_id']); ?>&name=<?php echo urlencode($g['name']); ?>&slug=<?php echo urlencode($g['slug']); ?>">編集</a>
              <a class="button-link" href="genres.php?delete=<?php echo e($g['genre_id']); ?>" onclick="return confirm('削除しますか？');">削除</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
