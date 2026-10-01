<?php
require_once __DIR__ . '/functions.php';

require_login();
require_role('admin');

$page_title = '通知・予定';
$page_description = 'ワクチン予定日や期限切れのケア情報を確認できます。';

$pdo = get_pdo();

$today = date('Y-m-d');
$soon = date('Y-m-d', strtotime('+30 days'));

$stmt = $pdo->prepare('
    SELECT
        v.*,
        p.code,
        p.name AS pet_name,
        p.species,
        p.photo_path,
        o.name AS owner_name,
        o.phone AS owner_phone
    FROM vaccinations v
    INNER JOIN pets p ON v.pet_id = p.pet_id
    LEFT JOIN owners o ON p.owner_id = o.owner_id
    WHERE v.next_due_date IS NOT NULL
    ORDER BY v.next_due_date ASC
');
$stmt->execute();
$vaccines = $stmt->fetchAll();

$expired = [];
$upcoming = [];
$future = [];

foreach ($vaccines as $vaccine) {
    if ($vaccine['next_due_date'] < $today) {
        $expired[] = $vaccine;
    } elseif ($vaccine['next_due_date'] <= $soon) {
        $upcoming[] = $vaccine;
    } else {
        $future[] = $vaccine;
    }
}
?>

<?php include __DIR__ . '/templates/header.php'; ?>

<div class="card">
  <h2>通知・予定</h2>
  <p>ワクチンの次回予定日をもとに、期限切れ・30日以内・今後の予定を確認できます。</p>

  <div class="summary-box">
    <div><span><?php echo count($expired); ?></span>期限切れ</div>
    <div><span><?php echo count($upcoming); ?></span>30日以内</div>
    <div><span><?php echo count($future); ?></span>今後の予定</div>
  </div>
</div>

<section class="record-section">
  <h4>期限切れ</h4>

  <?php if (count($expired) === 0): ?>
    <p>期限切れの予定はありません。</p>
  <?php else: ?>
    <div class="card-list">
      <?php foreach ($expired as $item): ?>
        <div class="card-light">
          <span class="badge">期限切れ</span>

          <?php if (!empty($item['photo_path'])): ?>
            <div style="margin: 12px 0;">
              <img
                src="<?php echo BASE_URL . '/' . e($item['photo_path']); ?>"
                alt="<?php echo e($item['pet_name'] ?? 'ペット写真'); ?>"
                style="max-width: 100%; width: 220px; border-radius: 12px; display: block;"
              >
            </div>
          <?php endif; ?>

          <h3><?php echo e($item['pet_name'] ?? '名前未登録'); ?></h3>
          <p>種類: <?php echo e($item['species'] ?? '未登録'); ?></p>
          <p>ワクチン: <?php echo e($item['vaccine_name']); ?></p>
          <p>次回予定日: <?php echo e($item['next_due_date']); ?></p>
          <p>飼い主: <?php echo e($item['owner_name'] ?? '未登録'); ?></p>
          <p>連絡先: <?php echo e($item['owner_phone'] ?? '未登録'); ?></p>

          <p>
            <a class="button-link" href="<?php echo BASE_URL; ?>/pet/view.php?code=<?php echo urlencode($item['code']); ?>">
              個体ページを見る
            </a>
          </p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="record-section">
  <h4>30日以内</h4>

  <?php if (count($upcoming) === 0): ?>
    <p>30日以内の予定はありません。</p>
  <?php else: ?>
    <div class="card-list">
      <?php foreach ($upcoming as $item): ?>
        <div class="card-light">
          <span class="badge">30日以内</span>

          <?php if (!empty($item['photo_path'])): ?>
            <div style="margin: 12px 0;">
              <img
                src="<?php echo BASE_URL . '/' . e($item['photo_path']); ?>"
                alt="<?php echo e($item['pet_name'] ?? 'ペット写真'); ?>"
                style="max-width: 100%; width: 220px; border-radius: 12px; display: block;"
              >
            </div>
          <?php endif; ?>

          <h3><?php echo e($item['pet_name'] ?? '名前未登録'); ?></h3>
          <p>種類: <?php echo e($item['species'] ?? '未登録'); ?></p>
          <p>ワクチン: <?php echo e($item['vaccine_name']); ?></p>
          <p>次回予定日: <?php echo e($item['next_due_date']); ?></p>
          <p>飼い主: <?php echo e($item['owner_name'] ?? '未登録'); ?></p>
          <p>連絡先: <?php echo e($item['owner_phone'] ?? '未登録'); ?></p>

          <p>
            <a class="button-link" href="<?php echo BASE_URL; ?>/pet/view.php?code=<?php echo urlencode($item['code']); ?>">
              個体ページを見る
            </a>
          </p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<section class="record-section">
  <h4>今後の予定</h4>

  <?php if (count($future) === 0): ?>
    <p>今後の予定はありません。</p>
  <?php else: ?>
    <div class="card-list">
      <?php foreach ($future as $item): ?>
        <div class="card-light">
          <span class="badge">予定</span>

          <?php if (!empty($item['photo_path'])): ?>
            <div style="margin: 12px 0;">
              <img
                src="<?php echo BASE_URL . '/' . e($item['photo_path']); ?>"
                alt="<?php echo e($item['pet_name'] ?? 'ペット写真'); ?>"
                style="max-width: 100%; width: 220px; border-radius: 12px; display: block;"
              >
            </div>
          <?php endif; ?>

          <h3><?php echo e($item['pet_name'] ?? '名前未登録'); ?></h3>
          <p>種類: <?php echo e($item['species'] ?? '未登録'); ?></p>
          <p>ワクチン: <?php echo e($item['vaccine_name']); ?></p>
          <p>次回予定日: <?php echo e($item['next_due_date']); ?></p>
          <p>飼い主: <?php echo e($item['owner_name'] ?? '未登録'); ?></p>
          <p>連絡先: <?php echo e($item['owner_phone'] ?? '未登録'); ?></p>

          <p>
            <a class="button-link" href="<?php echo BASE_URL; ?>/pet/view.php?code=<?php echo urlencode($item['code']); ?>">
              個体ページを見る
            </a>
          </p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php include __DIR__ . '/templates/footer.php'; ?>