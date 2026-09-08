<?php
require_once __DIR__ . '/functions.php';

$page_title = '迷子・発見掲示板';
$page_heading = '迷子・発見掲示板';
$page_description = '写真つきで報告された迷子・発見情報を一覧で確認できます。';
$pdo = get_pdo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();

    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        http_response_code(400);
        exit('CSRF token mismatch');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $lost_id = $_POST['lost_id'] ?? null;
        $new_status = $_POST['status'] ?? null;

        $allowed_statuses = ['未対応', '写真候補あり', '迷子', '解決済み', 'found'];

        if ($lost_id && in_array($new_status, $allowed_statuses, true)) {
            $stmt = $pdo->prepare('UPDATE lost_reports SET status = ? WHERE lost_id = ?');
            $stmt->execute([$new_status, $lost_id]);
        }
    }

    if ($action === 'delete_report') {
        $lost_id = $_POST['lost_id'] ?? null;

        if ($lost_id) {
            $stmt = $pdo->prepare('DELETE FROM lost_reports WHERE lost_id = ?');
            $stmt->execute([$lost_id]);
        }
    }

    header('Location: lost_board.php');
    exit;
}

$status = $_GET['status'] ?? '';
$keyword = trim($_GET['keyword'] ?? '');

$sql = '
    SELECT
        lr.*,
        p.code,
        p.name AS pet_name,
        p.species,
        p.photo_path AS pet_photo_path,
        o.name AS owner_name
    FROM lost_reports lr
    LEFT JOIN pets p ON lr.pet_id = p.pet_id
    LEFT JOIN owners o ON p.owner_id = o.owner_id
    WHERE 1 = 1
';

$params = [];

if ($status !== '') {
    $sql .= ' AND lr.status = ?';
    $params[] = $status;
}

if ($keyword !== '') {
    $sql .= ' AND (
        p.name LIKE ?
        OR p.code LIKE ?
        OR p.species LIKE ?
        OR lr.location LIKE ?
        OR lr.notes LIKE ?
        OR lr.reporter_name LIKE ?
    )';

    $like = '%' . $keyword . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= ' ORDER BY lr.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();
?>

<?php include __DIR__ . '/templates/header.php'; ?>

<div class="card">
  <form method="get" class="form-grid">
    <div class="form-group">
      <label>キーワード</label>
      <input
        type="text"
        name="keyword"
        value="<?php echo e($keyword); ?>"
        placeholder="名前、コード、種類、場所など"
      >
    </div>

    <div class="form-group">
      <label>状態</label>
      <select name="status">
        <option value="">すべて</option>
        <option value="未対応" <?php echo $status === '未対応' ? 'selected' : ''; ?>>未対応</option>
        <option value="写真候補あり" <?php echo $status === '写真候補あり' ? 'selected' : ''; ?>>写真候補あり</option>
        <option value="迷子" <?php echo $status === '迷子' ? 'selected' : ''; ?>>迷子</option>
        <option value="解決済み" <?php echo $status === '解決済み' ? 'selected' : ''; ?>>解決済み</option>
      </select>
    </div>

    <button type="submit" class="button">検索</button>
  </form>
</div>

<?php if (count($reports) === 0): ?>
  <div class="card">
    <p>該当する迷子報告はありません。</p>
  </div>
<?php else: ?>
  <div class="card-list">
    <?php foreach ($reports as $report): ?>
      <div class="card-light">
        <div class="section-title">
          <h3>
            <?php echo e($report['pet_name'] ?? '名前未登録'); ?>
          </h3>
          <span class="badge"><?php echo e($report['status'] ?? '未確認'); ?></span>
        </div>

        <?php
          $image_path = $report['photo_path'] ?: $report['pet_photo_path'];
        ?>

        <?php if (!empty($image_path)): ?>
          <div style="margin: 12px 0;">
            <img
              src="<?php echo BASE_URL . '/' . e($image_path); ?>"
              alt="迷子報告写真"
              style="max-width: 100%; width: 260px; border-radius: 12px; display: block;"
            >
          </div>
        <?php else: ?>
          <div style="margin: 12px 0; padding: 24px; background: #f3f4f6; border-radius: 12px; color: #666;">
            写真は登録されていません。
          </div>
        <?php endif; ?>

        <p>種類: <?php echo e($report['species'] ?? '未登録'); ?></p>
        <p>コード: <?php echo e($report['code'] ?? '不明'); ?></p>
        <p>場所: <?php echo e($report['location'] ?? '未登録'); ?></p>
        <p>報告日: <?php echo e($report['report_date'] ?? '未登録'); ?></p>
        <?php if ($user): ?>
          <p>発見者: <?php echo e($report['reporter_name'] ?? '未登録'); ?></p>
          <p>連絡先: <?php echo e($report['reporter_phone'] ?? '未登録'); ?></p>
        <?php else: ?>
          <p>発見者情報: 管理者のみ確認できます。</p>
        <?php endif; ?>

        <?php if ($user): ?>
          <form method="post" class="form-grid" style="margin-top: 12px;">
            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="lost_id" value="<?php echo e($report['lost_id']); ?>">

            <div class="form-group">
              <label>状態を変更</label>
              <select name="status">
                <option value="未対応" <?php echo $report['status'] === '未対応' ? 'selected' : ''; ?>>未対応</option>
                <option value="写真候補あり" <?php echo $report['status'] === '写真候補あり' ? 'selected' : ''; ?>>写真候補あり</option>
                <option value="迷子" <?php echo $report['status'] === '迷子' ? 'selected' : ''; ?>>迷子</option>
                <option value="解決済み" <?php echo $report['status'] === '解決済み' ? 'selected' : ''; ?>>解決済み</option>
                <option value="found" <?php echo $report['status'] === 'found' ? 'selected' : ''; ?>>found</option>
              </select>
            </div>

            <button type="submit" class="button">更新</button>
          </form>

          <form method="post" style="margin-top: 8px;" onsubmit="return confirm('この迷子報告を削除しますか？');">
            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="delete_report">
            <input type="hidden" name="lost_id" value="<?php echo e($report['lost_id']); ?>">
            <button type="submit" class="button" style="background: #dc2626;">削除</button>
          </form>
        <?php endif; ?>
        
        <?php if (!empty($report['notes'])): ?>
          <div style="margin-top: 10px;">
            <strong>メモ</strong>
            <p><?php echo nl2br(e($report['notes'])); ?></p>
          </div>
        <?php endif; ?>

        <?php if (!empty($report['code'])): ?>
            <p>個体コード: <strong><?php echo e($report['code']); ?></strong></p>

            <?php if ($user): ?>
                <p>
                <a class="button-link" href="<?php echo BASE_URL; ?>/pet/view.php?code=<?php echo urlencode($report['code']); ?>">
                    管理用に個体ページを見る
                </a>
                </p>
            <?php endif; ?>
            <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/templates/footer.php'; ?>