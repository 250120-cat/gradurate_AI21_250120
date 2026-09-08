<?php
require_once __DIR__ . '/../functions.php';
require_login();

$page_title = '管理ダッシュボード';
$page_description = 'Pet Digital ID の管理者画面です。データの集計と迷子報告の確認ができます。';

$pdo = get_pdo();
try {
    $totals = [];
    $totals['pets'] = (int)$pdo->query('SELECT COUNT(*) FROM pets')->fetchColumn();
    $totals['owners'] = (int)$pdo->query('SELECT COUNT(*) FROM owners')->fetchColumn();
    $totals['shops'] = (int)$pdo->query('SELECT COUNT(*) FROM shops')->fetchColumn();
    $totals['health_records'] = (int)$pdo->query('SELECT COUNT(*) FROM health_records')->fetchColumn();
    $totals['consultation_threads'] = (int)$pdo->query('SELECT COUNT(*) FROM consultation_threads')->fetchColumn();
    $newsFeeds = $pdo->query('SELECT nf.feed_id, nf.title, nf.url, nf.enabled, nf.last_fetched, COUNT(nc.item_id) AS cached_count FROM news_feeds nf LEFT JOIN news_cache nc ON nf.feed_id = nc.feed_id GROUP BY nf.feed_id ORDER BY nf.last_fetched DESC')->fetchAll();
} catch (Exception $e) {
    $totals = ['pets'=>0,'owners'=>0,'shops'=>0,'health_records'=>0];
    $newsFeeds = [];
}
?>
<?php include __DIR__ . '/../templates/header.php'; ?>
<div class="dashboard-grid">
  <div class="card-light dashboard-summary">
    <div class="dashboard-header">
      <h3>管理ダッシュボード</h3>
      <p class="dashboard-intro">主要な運用機能と現在の登録状況がひと目でわかる画面です。</p>
    </div>
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value"><?php echo $totals['pets']; ?></div>
        <div class="stat-label">登録ペット</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?php echo $totals['owners']; ?></div>
        <div class="stat-label">飼い主</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?php echo $totals['shops']; ?></div>
        <div class="stat-label">店舗</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?php echo $totals['health_records']; ?></div>
        <div class="stat-label">健康記録</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?php echo $totals['consultation_threads']; ?></div>
        <div class="stat-label">相談スレッド</div>
      </div>
    </div>
  </div>

  <div class="card-light dashboard-actions">
    <div class="dashboard-header">
      <h3>よく使う機能</h3>
      <p class="dashboard-intro">管理作業の主要なページへすばやく移動できます。</p>
    </div>
    <div class="admin-menu admin-menu-grid">
      <a class="admin-button" href="<?php echo BASE_URL; ?>/admin/users.php">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zm-7 9a7 7 0 0 1 14 0H5z" fill="#fff"/></svg>
        <span>ユーザー管理</span>
      </a>
      <a class="admin-button" href="<?php echo BASE_URL; ?>/admin/lost_reports.php">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M21 19V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14l4-3 4 3 6-6 4 5z" fill="#fff"/></svg>
        <span>迷子報告</span>
      </a>
      <a class="admin-button" href="<?php echo BASE_URL; ?>/lost_board.php">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M2 7v10l4-4h12V7H2z" fill="#fff"/></svg>
        <span>迷子掲示板</span>
      </a>
      <a class="admin-button" href="<?php echo BASE_URL; ?>/admin/email_templates.php">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M4 4h16v16H4V4zm2 2v2l6 3 6-3V6H6z" fill="#fff"/></svg>
        <span>メールテンプレ</span>
      </a>
      <a class="admin-button" href="<?php echo BASE_URL; ?>/admin/news_feeds.php">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M3 5h18v4H3V5zm0 6h12v4H3v-4zM3 19h8v-2H3v2z" fill="#fff"/></svg>
        <span>ニュース管理</span>
      </a>
      <a class="admin-button" href="<?php echo BASE_URL; ?>/consultation.php">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M2 7v10l4-4h12V7H2z" fill="#fff"/></svg>
        <span>相談掲示板</span>
      </a>
      <a class="admin-button" href="<?php echo BASE_URL; ?>/seed.php">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M6 2h8v2H6V2zM4 6h12v2H4V6zm2 4h8v10H6V10z" fill="#fff"/></svg>
        <span>サンプルデータ</span>
      </a>
      <a class="admin-button" href="<?php echo BASE_URL; ?>/admin/fetch_news.php" onclick="return confirm('全フィールドを今すぐ取得しますか？処理には時間がかかる場合があります。よろしいですか？');">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 2v6l4-4 4 4V2h-8zM4 8v14h16v-6h-2v4H6V8H4z" fill="#fff"/></svg>
        <span>全フィールド取得</span>
      </a>
    </div>
  </div>

  <div class="card-light dashboard-status">
    <h3>取得状況</h3>
    <?php
      try {
        $last = $pdo->query('SELECT MAX(fetched_at) AS last_fetch FROM news_cache')->fetchColumn();
        $total = (int)$pdo->query('SELECT COUNT(*) FROM news_cache')->fetchColumn();
      } catch (Exception $e) {
        $last = null; $total = 0;
      }
    ?>
    <div class="status-row">
      <div>
        <p class="status-label">最新取得日時</p>
        <p class="status-value"><?php echo $last ? e($last) : '未取得'; ?></p>
      </div>
      <div>
        <p class="status-label">保存済み記事数</p>
        <p class="status-value"><?php echo $total; ?></p>
      </div>
    </div>
    <p><a class="button-link" href="<?php echo BASE_URL; ?>/admin/news_feeds.php">ニュースフィード管理</a></p>
  </div>

  <div class="card-light dashboard-feed">
    <h3>フィード状況</h3>
    <?php if (count($newsFeeds) === 0): ?>
      <p>登録されているニュースフィードはありません。</p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>タイトル</th>
              <th>最終取得</th>
              <th>件数</th>
              <th>状態</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($newsFeeds as $feed): ?>
            <tr>
              <td><?php echo e($feed['feed_id']); ?></td>
              <td><?php echo e($feed['title'] ?: '未設定'); ?></td>
              <td><?php echo e($feed['last_fetched'] ?? '未取得'); ?></td>
              <td><?php echo e($feed['cached_count']); ?></td>
              <td><?php echo $feed['enabled'] ? '有効' : '無効'; ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>