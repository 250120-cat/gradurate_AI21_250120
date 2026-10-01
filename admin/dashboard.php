<?php
require_once __DIR__ . '/../functions.php';
require_login();
require_role('admin');

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
    $totals['editorial_news'] = (int)$pdo->query("SELECT COUNT(*) FROM editorial_news WHERE status = 'published'")->fetchColumn();
} catch (Exception $e) {
    $totals = ['pets'=>0,'owners'=>0,'shops'=>0,'health_records'=>0];
    $totals['consultation_threads'] = 0;
    $totals['editorial_news'] = 0;
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
      <a class="admin-button" href="<?php echo BASE_URL; ?>/admin/pets.php">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 12a5 5 0 1 0 0-10 5 5 0 0 0 0 10zM3 22a9 9 0 0 1 18 0H3z" fill="#fff"/></svg>
        <span>ペット管理</span>
      </a>
      <a class="admin-button" href="<?php echo BASE_URL; ?>/admin/audit_logs.php">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9 9 9 0 0 0-9-9zm1 4v5.4l3.2 1.9-1 1.7-4.2-2.6V7h2z" fill="#fff"/></svg>
        <span>操作履歴</span>
      </a>
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
      <a class="admin-button" href="<?php echo BASE_URL; ?>/admin/news_articles.php">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2zm3 4v2h8V7H8zm0 4v2h8v-2H8zm0 4v2h5v-2H8z" fill="#fff"/></svg>
        <span>動物ニュース記事管理</span>
      </a>
      <a class="admin-button" href="<?php echo BASE_URL; ?>/consultation.php">
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path d="M2 7v10l4-4h12V7H2z" fill="#fff"/></svg>
        <span>相談掲示板</span>
      </a>
    </div>
  </div>

</div>
<?php include __DIR__ . '/../templates/footer.php'; ?>