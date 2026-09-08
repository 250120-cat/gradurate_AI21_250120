<section class="section">
  <div class="section-head">
    <h2>最近の迷子・発見報告</h2>
    <p>最新の報告情報を確認できます。</p>
  </div>

  <?php if (count($recent_reports) === 0): ?>
    <div class="recent-empty">
      <p>まだ迷子報告はありません。</p>
      <a class="button-link" href="<?php echo BASE_URL; ?>/lost.php" style="margin-top:12px">最初の報告をする</a>
    </div>
  <?php else: ?>
    <div class="recent-grid">
      <?php foreach ($recent_reports as $report): ?>
        <?php $image_path = $report['photo_path'] ?: $report['pet_photo_path']; ?>
        <article class="recent-card">
          <div class="recent-card-photo">
            <?php if ($image_path): ?>
              <img src="<?php echo BASE_URL . '/' . e($image_path); ?>" alt="<?php echo e($report['pet_name'] ?? '迷子報告'); ?>">
            <?php else: ?>
              <div class="recent-card-placeholder">🐾</div>
            <?php endif; ?>
          </div>
          <div class="recent-card-body">
            <h3><?php echo e($report['pet_name'] ?? '名前未登録'); ?></h3>
            <p class="recent-card-meta">
              <?php echo e($report['species'] ?? '種類未登録'); ?> ／
              <?php echo e($report['location'] ?: '場所未登録'); ?> ／
              <span class="badge"><?php echo e($report['status'] ?? '未確認'); ?></span>
            </p>
            <!-- 変更前 -->
<a class="button-link" href="<?php echo BASE_URL; ?>/lost_board.php">詳細を見る</a>

<!-- 変更後 -->
<?php if (!empty($report['code'])): ?>
  <a class="button-link" href="<?php echo BASE_URL; ?>/pet/view.php?code=<?php echo urlencode($report['code']); ?>">詳細を見る</a>
<?php else: ?>
  <a class="button-link" href="<?php echo BASE_URL; ?>/lost_board.php">詳細を見る</a>
<?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="section-more">
      <a class="button-outline" href="<?php echo BASE_URL; ?>/lost_board.php">すべての報告を見る</a>
    </p>
  <?php endif; ?>
</section>
