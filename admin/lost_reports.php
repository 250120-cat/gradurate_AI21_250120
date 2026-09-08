<?php
require_once __DIR__ . '/../functions.php';
require_login();

$pdo = get_pdo();
$reports = $pdo->query('SELECT lr.*, p.name as pet_name, o.name as owner_name FROM lost_reports lr LEFT JOIN pets p ON lr.pet_id = p.pet_id LEFT JOIN owners o ON p.owner_id = o.owner_id ORDER BY lr.report_date DESC')->fetchAll();
?>
<?php include __DIR__ . '/../templates/header.php'; ?>
<h2>迷子報告一覧</h2>
<table style="width:100%;border-collapse:collapse;">
  <thead>
    <tr>
      <th style="border:1px solid #ddd;padding:8px">日付</th>
      <th style="border:1px solid #ddd;padding:8px">ペット</th>
      <th style="border:1px solid #ddd;padding:8px">飼い主</th>
      <th style="border:1px solid #ddd;padding:8px">場所</th>
      <th style="border:1px solid #ddd;padding:8px">状態</th>
      <th style="border:1px solid #ddd;padding:8px">メモ</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($reports as $report): ?>
    <tr>
      <td style="border:1px solid #ddd;padding:8px"><?php echo e($report['report_date']); ?></td>
      <td style="border:1px solid #ddd;padding:8px"><?php echo e($report['pet_name']); ?></td>
      <td style="border:1px solid #ddd;padding:8px"><?php echo e($report['owner_name']); ?></td>
      <td style="border:1px solid #ddd;padding:8px"><?php echo e($report['location']); ?></td>
      <td style="border:1px solid #ddd;padding:8px"><?php echo e($report['status']); ?></td>
      <td style="border:1px solid #ddd;padding:8px"><?php echo nl2br(e($report['notes'])); ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php include __DIR__ . '/../templates/footer.php'; ?>
