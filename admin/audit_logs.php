<?php
require_once __DIR__ . '/../functions.php';

require_login();
require_role('admin');

$page_title = '操作履歴';
$page_heading = '操作履歴';
$page_description = '管理者によるペット情報やニュースの変更履歴を確認できます。';

$pdo = get_pdo();
$table_check = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name = 'audit_logs'"
);
if (!(int)$table_check->fetchColumn()) {
    include __DIR__ . '/../templates/header.php';
    echo '<div class="card"><p>操作履歴テーブルがありません。</p></div>';
    include __DIR__ . '/../templates/footer.php';
    exit;
}

$action_filter = trim($_GET['action'] ?? '');
$allowed_actions = [
    'pet_created' => 'ペット登録',
    'pet_updated' => 'ペット編集',
    'pet_deleted' => 'ペット削除',
    'editorial_news_saved' => 'ニュース保存',
];
if ($action_filter !== '' && !isset($allowed_actions[$action_filter])) {
    $action_filter = '';
}

$conditions = [];
$params = [];
if ($action_filter !== '') {
    $conditions[] = 'action = ?';
    $params[] = $action_filter;
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
$export = ($_GET['export'] ?? '') === 'csv';
if ($export) {
    $stmt = $pdo->prepare(
        'SELECT log_id, user_name, action, target_type, target_id, details, created_at
         FROM audit_logs' . $where . '
         ORDER BY created_at DESC, log_id DESC'
    );
    $stmt->execute($params);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="audit-logs-' . date('Ymd-His') . '.csv"');
    $output = fopen('php://output', 'wb');
    if ($output === false) {
        throw new RuntimeException('CSV出力を開始できませんでした。');
    }

    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['日時', 'ユーザー', '操作', '対象種別', '対象ID', '詳細']);
    $safe_csv_value = static function ($value) {
        $value = (string)($value ?? '');
        if (preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $value)) {
            return "'" . $value;
        }
        return $value;
    };

    while ($log = $stmt->fetch()) {
        fputcsv($output, [
            $safe_csv_value($log['created_at']),
            $safe_csv_value($log['user_name']),
            $safe_csv_value($allowed_actions[$log['action']] ?? $log['action']),
            $safe_csv_value($log['target_type']),
            $safe_csv_value($log['target_id']),
            $safe_csv_value($log['details']),
        ]);
    }

    fclose($output);
    exit;
}

$count_stmt = $pdo->prepare('SELECT COUNT(*) FROM audit_logs' . $where);
$count_stmt->execute($params);
$total_logs = (int)$count_stmt->fetchColumn();

$per_page = 30;
$total_pages = max(1, (int)ceil($total_logs / $per_page));
$requested_page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = $requested_page && $requested_page > 0 ? min($requested_page, $total_pages) : 1;
$offset = ($page - 1) * $per_page;

$stmt = $pdo->prepare(
    'SELECT log_id, user_name, action, target_type, target_id, details, created_at
     FROM audit_logs' . $where . '
     ORDER BY created_at DESC, log_id DESC
     LIMIT ? OFFSET ?'
);
foreach ($params as $index => $value) {
    $stmt->bindValue($index + 1, $value, PDO::PARAM_STR);
}
$stmt->bindValue(count($params) + 1, $per_page, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$logs = $stmt->fetchAll();

$query = ['action' => $action_filter];
?>
<?php include __DIR__ . '/../templates/header.php'; ?>

<div class="card">
  <form method="get" class="form-grid">
    <div class="form-group">
      <label for="audit-action">操作の種類</label>
      <select id="audit-action" name="action">
        <option value="">すべて</option>
        <?php foreach ($allowed_actions as $value => $label): ?>
          <option value="<?php echo e($value); ?>" <?php echo $action_filter === $value ? 'selected' : ''; ?>>
            <?php echo e($label); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="button">絞り込む</button>
    <a class="button-link" href="<?php echo BASE_URL; ?>/admin/audit_logs.php?<?php echo e(http_build_query(['action' => $action_filter, 'export' => 'csv'])); ?>">CSVをダウンロード</a>
  </form>
</div>

<div class="card">
  <p><?php echo $total_logs; ?>件の履歴</p>
  <?php if (!$logs): ?>
    <p>該当する操作履歴はありません。</p>
  <?php else: ?>
    <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr><th>日時</th><th>ユーザー</th><th>操作</th><th>対象</th><th>詳細</th></tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log): ?>
            <tr>
              <td><?php echo e($log['created_at']); ?></td>
              <td><?php echo e($log['user_name']); ?></td>
              <td><?php echo e($allowed_actions[$log['action']] ?? $log['action']); ?></td>
              <td><?php echo e($log['target_type'] ?? ''); ?><?php echo $log['target_id'] ? ' #' . e($log['target_id']) : ''; ?></td>
              <td><?php echo e($log['details'] ?? ''); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($total_pages > 1): ?>
      <nav class="pet-pagination" aria-label="操作履歴のページ">
        <?php if ($page > 1): ?>
          <?php $query['page'] = $page - 1; ?>
          <a class="button-link" href="<?php echo BASE_URL; ?>/admin/audit_logs.php?<?php echo e(http_build_query($query)); ?>">前へ</a>
        <?php endif; ?>
        <span><?php echo $page; ?> / <?php echo $total_pages; ?>ページ</span>
        <?php if ($page < $total_pages): ?>
          <?php $query['page'] = $page + 1; ?>
          <a class="button-link" href="<?php echo BASE_URL; ?>/admin/audit_logs.php?<?php echo e(http_build_query($query)); ?>">次へ</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
