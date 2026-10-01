<?php
require_once __DIR__ . '/../functions.php';
require_login();
require_role('admin');

$page_title = '迷子報告一覧';
$page_heading = '迷子報告一覧';
$page_description = '登録された迷子・発見報告の一覧です。';

$pdo = get_pdo();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        http_response_code(400);
        exit('不正な操作が検出されました。画面を再読み込みしてください。');
    }

    $lost_id = filter_var($_POST['lost_id'] ?? null, FILTER_VALIDATE_INT);
    $action = $_POST['action'] ?? '';

    if (!$lost_id || $lost_id < 1) {
        $error = '報告IDが正しくありません。';
    } else {
        $reportStmt = $pdo->prepare('SELECT lost_id, pet_id, notes FROM lost_reports WHERE lost_id = ? LIMIT 1');
        $reportStmt->execute([$lost_id]);
        $report = $reportStmt->fetch();

        if (!$report) {
            $error = '対象の報告が見つかりません。';
        } elseif ($action === 'link_pet') {
            $pet_id = filter_var($_POST['pet_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$pet_id || $pet_id < 1) {
                $error = '紐付けるペットを選択してください。';
            } else {
                $petStmt = $pdo->prepare('SELECT pet_id, name, code FROM pets WHERE pet_id = ? LIMIT 1');
                $petStmt->execute([$pet_id]);
                $pet = $petStmt->fetch();

                if (!$pet) {
                    $error = '選択したペットが見つかりません。';
                } else {
                    $actor = current_user()['username'] ?? 'admin';
                    $entry = "\n\n【管理者による紐付け】{$actor} が " . date('Y-m-d H:i:s')
                        . " に確認し、{$pet['name']}（コード: {$pet['code']}）へ紐付けました。";
                    $stmt = $pdo->prepare("UPDATE lost_reports SET pet_id = ?, notes = CONCAT(COALESCE(notes, ''), ?) WHERE lost_id = ?");
                    $stmt->execute([$pet_id, $entry, $lost_id]);
                    add_audit_log('lost_report_linked', 'lost_report', (int)$lost_id, 'ペットコード: ' . $pet['code']);
                    $_SESSION['flash_message'] = '確認したペットに報告を紐付けました。';
                    redirect(BASE_URL . '/admin/lost_reports.php');
                }
            }
        } elseif ($action === 'unlink_pet') {
            if ($report['pet_id'] === null) {
                $error = 'この報告はすでに未特定です。';
            } else {
                $actor = current_user()['username'] ?? 'admin';
                $entry = "\n\n【管理者による紐付け解除】{$actor} が " . date('Y-m-d H:i:s')
                    . " にペットとの紐付けを解除しました。";
                $stmt = $pdo->prepare("UPDATE lost_reports SET pet_id = NULL, notes = CONCAT(COALESCE(notes, ''), ?) WHERE lost_id = ?");
                $stmt->execute([$entry, $lost_id]);
                add_audit_log('lost_report_unlinked', 'lost_report', (int)$lost_id, 'ペットとの紐付けを解除しました');
                $_SESSION['flash_message'] = '報告を未特定に戻しました。';
                redirect(BASE_URL . '/admin/lost_reports.php');
            }
        } else {
            $error = '操作を認識できませんでした。';
        }
    }
}

$reports = $pdo->query('SELECT lr.*, p.name as pet_name, p.code as pet_code, o.name as owner_name FROM lost_reports lr LEFT JOIN pets p ON lr.pet_id = p.pet_id LEFT JOIN owners o ON p.owner_id = o.owner_id ORDER BY lr.created_at DESC')->fetchAll();
$pets = $pdo->query('SELECT pet_id, name, code, species FROM pets ORDER BY name, code')->fetchAll();
$flash_message = $_SESSION['flash_message'] ?? null;
unset($_SESSION['flash_message']);
?>
<?php include __DIR__ . '/../templates/header.php'; ?>
<div class="card">
  <p><a class="button-link" href="<?php echo BASE_URL; ?>/admin/dashboard.php">管理トップへ</a></p>
  <?php if ($flash_message): ?>
    <div class="notice"><?php echo e($flash_message); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert"><?php echo e($error); ?></div>
  <?php endif; ?>
  <?php if (count($reports) === 0): ?>
    <p>迷子報告はまだありません。</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th>日付</th>
            <th>ペット</th>
            <th>飼い主</th>
            <th>場所</th>
            <th>状態</th>
            <th>発見者</th>
            <th>メモ</th>
            <th>ペット紐付け（管理者確認後）</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($reports as $report): ?>
          <tr>
            <td><?php echo e($report['report_date']); ?></td>
            <td>
              <?php if ($report['pet_id'] !== null): ?>
                <?php echo e($report['pet_name'] ?? '名前未登録'); ?><br>
                コード: <?php echo e($report['pet_code'] ?? ''); ?>
              <?php else: ?>
                未特定（候補はメモを確認）
              <?php endif; ?>
            </td>
            <td><?php echo e($report['owner_name'] ?? '—'); ?></td>
            <td><?php echo e($report['location']); ?></td>
            <td><?php echo e($report['status']); ?></td>
            <td><?php echo e($report['reporter_name'] ?? '—'); ?></td>
            <td><?php echo nl2br(e($report['notes'] ?? '')); ?></td>
            <td>
              <form method="post" class="form-grid" onsubmit="return confirm('選択したペットを報告対象として確定します。報告内容と写真を確認しましたか？');">
                <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                <input type="hidden" name="action" value="link_pet">
                <input type="hidden" name="lost_id" value="<?php echo e($report['lost_id']); ?>">
                <div class="form-group">
                  <label for="pet-<?php echo e($report['lost_id']); ?>">報告対象のペット</label>
                  <select id="pet-<?php echo e($report['lost_id']); ?>" name="pet_id" required>
                    <option value="">選択してください</option>
                    <?php foreach ($pets as $pet): ?>
                      <option value="<?php echo e($pet['pet_id']); ?>" <?php echo (string)$report['pet_id'] === (string)$pet['pet_id'] ? 'selected' : ''; ?>>
                        <?php echo e($pet['name'] . ' / ' . $pet['code'] . ' / ' . ($pet['species'] ?? '種類未登録')); ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <button type="submit" class="button">確認して紐付け</button>
              </form>
              <?php if ($report['pet_id'] !== null): ?>
                <form method="post" style="margin-top: 8px;" onsubmit="return confirm('この報告を未特定に戻しますか？');">
                  <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
                  <input type="hidden" name="action" value="unlink_pet">
                  <input type="hidden" name="lost_id" value="<?php echo e($report['lost_id']); ?>">
                  <button type="submit" class="button">紐付けを解除</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>
