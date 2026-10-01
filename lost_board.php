<?php
require_once __DIR__ . '/functions.php';

$page_title = '迷子・発見掲示板';
$page_heading = '迷子・発見掲示板';
$page_description = '写真つきで報告された迷子・発見情報を確認し、管理者は対応状況を更新できます。';
$pdo = get_pdo();
$user = current_user();
$status_options = [
    '未確認' => '未確認（ペット未特定）',
    '未対応' => '未対応',
    '写真候補あり' => '写真候補あり',
    '対応中' => '対応中',
    '迷子' => '迷子',
    '解決済み' => '解決済み',
    'found' => 'found',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    require_role('admin');

    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        http_response_code(400);
        exit('CSRF token mismatch');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'update_status') {
        $lost_id = filter_var($_POST['lost_id'] ?? null, FILTER_VALIDATE_INT);
        $new_status = $_POST['status'] ?? null;

        if ($lost_id && $lost_id > 0 && in_array($new_status, array_keys($status_options), true)) {
            $stmt = $pdo->prepare('UPDATE lost_reports SET status = ? WHERE lost_id = ?');
            $stmt->execute([$new_status, $lost_id]);
            add_audit_log('lost_report_status_updated', 'lost_report', $lost_id, '状態を「' . $status_options[$new_status] . '」に変更しました');
            $_SESSION['flash_message'] = '報告の状態を「' . $status_options[$new_status] . '」に更新しました。';
            $_SESSION['flash_type'] = 'notice';
        } else {
            $_SESSION['flash_message'] = '報告IDまたは状態が正しくありません。';
            $_SESSION['flash_type'] = 'alert';
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
if ($status !== '' && !array_key_exists($status, $status_options)) {
    $status = '';
}
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
$flash_message = $_SESSION['flash_message'] ?? null;
$flash_type = $_SESSION['flash_type'] ?? 'notice';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>

<?php include __DIR__ . '/templates/header.php'; ?>

<div class="card">
  <?php if ($flash_message): ?>
    <div class="<?php echo $flash_type === 'alert' ? 'alert' : 'notice'; ?>"><?php echo e($flash_message); ?></div>
  <?php endif; ?>
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
        <?php foreach ($status_options as $status_value => $status_label): ?>
          <option value="<?php echo e($status_value); ?>" <?php echo $status === $status_value ? 'selected' : ''; ?>>
            <?php echo e($status_label); ?>
          </option>
        <?php endforeach; ?>
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
      <div class="card-light" id="lost-report-<?php echo e($report['lost_id']); ?>">
        <div class="section-title">
          <h3>
            <?php echo e($report['pet_id'] === null ? '未特定の報告' : ($report['pet_name'] ?: '名前未登録')); ?>
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

        <p>種類: <?php echo e($report['pet_id'] === null ? '対象ペット未特定' : ($report['species'] ?: '未登録')); ?></p>
        <p>コード: <?php echo e($report['code'] ?? '未特定'); ?></p>
        <p>場所: <?php echo e($report['location'] ?? '未登録'); ?></p>
        <p>報告日: <?php echo e($report['report_date'] ?? '未登録'); ?></p>
        <?php if (has_role('admin')): ?>
          <p>発見者: <?php echo e($report['reporter_name'] ?? '未登録'); ?></p>
          <p>連絡先: <?php echo e($report['reporter_phone'] ?? '未登録'); ?></p>
        <?php else: ?>
          <p>発見者情報: 管理者のみ確認できます。</p>
        <?php endif; ?>

        <?php if (has_role('admin')): ?>
          <form method="post" class="form-grid" style="margin-top: 12px;">
            <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="lost_id" value="<?php echo e($report['lost_id']); ?>">

            <div class="form-group">
              <label>状態を変更</label>
              <select name="status">
                <?php foreach ($status_options as $status_value => $status_label): ?>
                  <option value="<?php echo e($status_value); ?>" <?php echo $report['status'] === $status_value ? 'selected' : ''; ?>>
                    <?php echo e($status_label); ?>
                  </option>
                <?php endforeach; ?>
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
            <?php if (has_role('admin')): ?>
              <p><?php echo nl2br(e($report['notes'])); ?></p>
            <?php else: ?>
              <p><?php echo nl2br(e(public_lost_report_notes($report['notes']))); ?></p>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($report['code'])): ?>
            <?php if (has_role('admin')): ?>
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