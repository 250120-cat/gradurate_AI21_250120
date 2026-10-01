<?php
$page_description = '管理者向けホーム。運用状況の確認と管理機能へのショートカットです。';
?>

<section class="home-hero home-hero--guest">
  <span class="home-hero-badge">⚙️ 管理者</span>
  <div class="home-hero-header">
    <h1>こんにちは、<?php echo e($username); ?> さん</h1>
    <p>迷子報告・ユーザー・ニュースなど、システム全体を管理できます。</p>
  </div>
</section>

<section class="section">
  <div class="section-head">
    <h2>登録状況</h2>
  </div>
  <div class="hero-stat-pills" style="flex-wrap:wrap;">
    <div class="hero-stat-pill">
      <strong><?php echo number_format($stats['pets']); ?></strong>
      <span>登録ペット</span>
    </div>
    <div class="hero-stat-pill">
      <strong><?php echo number_format($stats['owners']); ?></strong>
      <span>飼い主</span>
    </div>
    <div class="hero-stat-pill">
      <strong><?php echo number_format($stats['lost_reports']); ?></strong>
      <span>迷子報告</span>
    </div>
    <div class="hero-stat-pill">
      <strong><?php echo number_format($stats['consultation_threads']); ?></strong>
      <span>相談スレッド</span>
    </div>
  </div>
</section>

<section class="section">
  <div class="section-head">
    <h2>管理メニュー</h2>
  </div>
  <div class="card-list">
    <div class="card-light">
      <h3>⚙️ 管理ダッシュボード</h3>
      <p>サイトの集計や管理機能を確認します。</p>
      <a class="button-link" href="<?php echo BASE_URL; ?>/admin/dashboard.php">開く</a>
    </div>
    <div class="card-light">
      <h3>👤 ユーザー管理</h3>
      <p>迷子報告のサイト内受信箱通知を受け取るユーザーを設定します。</p>
      <a class="button-link" href="<?php echo BASE_URL; ?>/admin/users.php">開く</a>
    </div>
    <div class="card-light">
      <h3>🆘 迷子報告</h3>
      <p>届いた報告の一覧を確認します。</p>
      <a class="button-link" href="<?php echo BASE_URL; ?>/admin/lost_reports.php">開く</a>
    </div>
    <div class="card-light">
      <h3>📋 迷子掲示板</h3>
      <p>公開掲示板の状態更新・削除を行います。</p>
      <a class="button-link" href="<?php echo BASE_URL; ?>/lost_board.php">開く</a>
    </div>
    <div class="card-light">
      <h3>📰 動物ニュース記事作成</h3>
      <p>独自の紹介文と出典リンクを使ってニュースを作成・公開します。</p>
      <a class="button-link" href="<?php echo BASE_URL; ?>/admin/news_articles.php">開く</a>
    </div>
    <div class="card-light">
      <h3>🧾 操作履歴</h3>
      <p>ペット情報など、管理画面で行われた変更を確認します。</p>
      <a class="button-link" href="<?php echo BASE_URL; ?>/admin/audit_logs.php">開く</a>
    </div>
  </div>
</section>

<?php include __DIR__ . '/partials/recent_reports.php'; ?>
