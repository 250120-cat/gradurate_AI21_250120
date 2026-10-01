<?php
require_once __DIR__ . '/../functions.php';

require_login();
require_role('admin');

$page_title = 'ペット管理';
$page_heading = 'ペット管理';
$page_description = '登録済みペットの情報を確認し、必要に応じて編集できます。';

$pdo = get_pdo();
$search = trim($_GET['search'] ?? '');
$species_filter = trim($_GET['species'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$sort_options = [
    'newest' => 'p.pet_id DESC',
    'name' => 'p.name ASC, p.pet_id DESC',
    'code' => 'p.code ASC',
];
if (!isset($sort_options[$sort])) {
    $sort = 'newest';
}

$species = $pdo->query(
    "SELECT DISTINCT species FROM pets
     WHERE species IS NOT NULL AND TRIM(species) <> ''
     ORDER BY species"
)->fetchAll(PDO::FETCH_COLUMN);

$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(p.code LIKE ? OR p.name LIKE ? OR p.species LIKE ? OR o.name LIKE ? OR s.name LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($species_filter !== '') {
    $conditions[] = 'p.species = ?';
    $params[] = $species_filter;
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$count_stmt = $pdo->prepare(
    'SELECT COUNT(*) FROM pets p
     LEFT JOIN shops s ON p.shop_id = s.shop_id
     LEFT JOIN owners o ON p.owner_id = o.owner_id' . $where
);
$count_stmt->execute($params);
$total_pets = (int)$count_stmt->fetchColumn();

$per_page = 20;
$total_pages = max(1, (int)ceil($total_pets / $per_page));
$requested_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = $requested_page && $requested_page > 0 ? min($requested_page, $total_pages) : 1;
$offset = ($page - 1) * $per_page;

$sql = '
    SELECT p.pet_id, p.code, p.name, p.species, p.birthday,
           p.photo_path, s.name AS shop_name, o.name AS owner_name
    FROM pets p
    LEFT JOIN shops s ON p.shop_id = s.shop_id
    LEFT JOIN owners o ON p.owner_id = o.owner_id
    ' . $where . '
    ORDER BY ' . $sort_options[$sort] . '
    LIMIT ? OFFSET ?';
$stmt = $pdo->prepare($sql);
foreach ($params as $index => $value) {
    $stmt->bindValue($index + 1, $value, PDO::PARAM_STR);
}
$stmt->bindValue(count($params) + 1, $per_page, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$pets = $stmt->fetchAll();

$pagination_query = [
    'search' => $search,
    'species' => $species_filter,
    'sort' => $sort,
];
$deleted = !empty($_SESSION['pet_deleted']);
$photo_cleanup_warning = !empty($_SESSION['pet_delete_photo_warning']);
unset($_SESSION['pet_deleted'], $_SESSION['pet_delete_photo_warning']);
?>
<?php include __DIR__ . '/../templates/header.php'; ?>

<?php if ($deleted): ?>
  <div class="notice">ペットと関連記録を削除しました。</div>
  <?php if ($photo_cleanup_warning): ?>
    <div class="alert">一部の写真ファイルを削除できませんでした。uploads フォルダーに残っている可能性があります。</div>
  <?php endif; ?>
<?php endif; ?>

<div class="card">
  <form method="get" class="form-grid">
    <div class="form-group">
      <label for="pet-search">名前・個体コード・種類・飼い主・販売店で検索</label>
      <input id="pet-search" type="search" name="search" value="<?php echo e($search); ?>" placeholder="キーワードを入力">
    </div>
    <div class="form-group">
      <label for="pet-species">種類</label>
      <select id="pet-species" name="species">
        <option value="">すべて</option>
        <?php foreach ($species as $item): ?>
          <option value="<?php echo e($item); ?>" <?php echo $species_filter === $item ? 'selected' : ''; ?>>
            <?php echo e($item); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label for="pet-sort">並び順</label>
      <select id="pet-sort" name="sort">
        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>新しく登録した順</option>
        <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>名前順</option>
        <option value="code" <?php echo $sort === 'code' ? 'selected' : ''; ?>>個体コード順</option>
      </select>
    </div>
    <button type="submit" class="button">検索</button>
    <a class="button-link" href="<?php echo BASE_URL; ?>/admin/pets.php">条件をクリア</a>
  </form>
</div>

<?php if (empty($pets)): ?>
  <div class="card"><p>該当するペットはありません。</p></div>
<?php else: ?>
  <div class="card">
    <p><?php echo $total_pets; ?>件中 <?php echo $offset + 1; ?>〜<?php echo $offset + count($pets); ?>件を表示</p>
    <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>写真</th>
            <th>個体コード</th>
            <th>名前</th>
            <th>種類</th>
            <th>誕生日</th>
            <th>飼い主</th>
            <th>販売店</th>
            <th>操作</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pets as $pet): ?>
            <tr>
              <td>
                <?php if (!empty($pet['photo_path'])): ?>
                  <img src="<?php echo BASE_URL . '/' . e($pet['photo_path']); ?>" alt="" width="52" height="52" style="object-fit:cover;border-radius:10px;">
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
              <td><?php echo e($pet['code']); ?></td>
              <td><?php echo e($pet['name'] ?? '名前未登録'); ?></td>
              <td><?php echo e($pet['species'] ?? '未登録'); ?></td>
              <td><?php echo e($pet['birthday'] ?? '未登録'); ?></td>
              <td><?php echo e($pet['owner_name'] ?? '未登録'); ?></td>
              <td><?php echo e($pet['shop_name'] ?? '未登録'); ?></td>
              <td>
                <a class="button-link" href="<?php echo BASE_URL; ?>/admin/edit_pet.php?id=<?php echo e($pet['pet_id']); ?>">編集</a>
                <a href="<?php echo BASE_URL; ?>/pet/view.php?code=<?php echo urlencode($pet['code']); ?>">詳細</a>
                <a href="<?php echo BASE_URL; ?>/admin/delete_pet.php?id=<?php echo e($pet['pet_id']); ?>">削除</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($total_pages > 1): ?>
      <nav class="pet-pagination" aria-label="ペット一覧のページ">
        <?php if ($page > 1): ?>
          <?php $pagination_query['page'] = $page - 1; ?>
          <a class="button-link" href="<?php echo BASE_URL; ?>/admin/pets.php?<?php echo e(http_build_query($pagination_query)); ?>">前へ</a>
        <?php endif; ?>
        <span><?php echo $page; ?> / <?php echo $total_pages; ?>ページ</span>
        <?php if ($page < $total_pages): ?>
          <?php $pagination_query['page'] = $page + 1; ?>
          <a class="button-link" href="<?php echo BASE_URL; ?>/admin/pets.php?<?php echo e(http_build_query($pagination_query)); ?>">次へ</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>
