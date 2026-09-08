<?php
$page_description = '写真とQRコードで、大切なペットを見守るデジタルIDアプリです。';
?>

<section class="home-hero home-hero--guest">
  <span class="home-hero-badge">🐾 動物保護デジタルID</span>
  <div class="home-hero-header">
    <h1>大切な家族を、みんなで見守ろう</h1>
    <p>ペットの登録・迷子報告・発見通知をひとつのアプリで。<br>迷子が見つかったときは、登録ユーザー全員にメールでお知らせします。</p>
  </div>
</section>

<section class="quick-search">
  <div class="hero-search-box">
    <div class="hero-search-left">
      <span class="search-icon">🔍</span>
      <form class="hero-search-form" method="get" action="<?php echo BASE_URL; ?>/pet/view.php">
        <span class="search-label">ペットコードで検索</span>
        <input type="text" name="code" placeholder="例: P1234567890" required>
      </form>
    </div>
    <div class="hero-search-right">
      <a class="button" href="<?php echo BASE_URL; ?>/shop/register_pet.php">🐾 ペットを登録する</a>
      <a class="button-outline" href="<?php echo BASE_URL; ?>/lost.php">🆘 迷子・発見を報告</a>
      <a class="button-outline" href="<?php echo BASE_URL; ?>/pet/view.php">個体情報表示</a>
    </div>
  </div>
</section>

<section class="hero-stats">
  <div class="hero-stats-content">
    <div class="hero-stat-pills">
      <div class="hero-stat-pill">
        <strong><?php echo number_format($stats['lost_reports']); ?></strong>
        <span>迷子報告</span>
      </div>
      <div class="hero-stat-pill">
        <strong><?php echo number_format($stats['consultation_threads']); ?></strong>
        <span>相談掲示板</span>
      </div>
    </div>
  </div>
  <img class="hero-pet-icon" src="<?php echo BASE_URL; ?>/assets/dog-illustration.png" alt="犬のイラスト">
</section>

<section class="section">
  <div class="section-head">
    <h2>主な機能</h2>
    <p>ペットの登録から迷子対応、相談・ニュースまで、必要な機能をワンストップで。</p>
  </div>
  <div class="feature-grid">
    <article class="feature-card">
      <div class="feature-card-icon">🐾</div>
      <h3>ペット登録</h3>
      <p>基本情報と顔写真を登録し、個体コードとQRコードを発行します。</p>
      <a class="button-link" href="<?php echo BASE_URL; ?>/shop/register_pet.php">登録する</a>
    </article>
    <article class="feature-card">
      <div class="feature-card-icon">🔍</div>
      <h3>個体表示</h3>
      <p>個体コードからペット情報、健康記録、しつけ記録を確認できます。</p>
      <a class="button-link" href="<?php echo BASE_URL; ?>/pet/view.php">確認する</a>
    </article>
    <article class="feature-card feature-card-accent">
      <div class="feature-card-icon">🆘</div>
      <h3>迷子・発見報告</h3>
      <p>QRコードや個体コードが分かる場合も、写真だけでも発見報告できます。</p>
      <div class="card-actions">
      <a class="button-link" href="<?php echo BASE_URL; ?>/lost.php">報告する</a>
      <a class="button-link" href="<?php echo BASE_URL; ?>/lost_unknown.php" style="margin-left:8px;background:linear-gradient(135deg,#d97706,#b45309)">写真だけで報告</a>
      </div>
    </article>
    <article class="feature-card">
      <div class="feature-card-icon">📋</div>
      <h3>迷子掲示板</h3>
      <p>報告された迷子・発見情報を写真つきで一覧表示し、検索できます。</p>
      <a class="button-link" href="<?php echo BASE_URL; ?>/lost_board.php">掲示板を見る</a>
    </article>
    <article class="feature-card">
      <div class="feature-card-icon">💬</div>
      <h3>相談掲示板</h3>
      <p>飼育や健康に関する相談を投稿・閲覧できます。</p>
      <a class="button-link" href="<?php echo BASE_URL; ?>/consultation.php">相談する</a>
    </article>
    <article class="feature-card">
      <div class="feature-card-icon">📰</div>
      <h3>動物ニュース</h3>
      <p>動物保護やペットに関する最新ニュースをまとめて読めます。</p>
      <a class="button-link" href="<?php echo BASE_URL; ?>/news.php">ニュースを見る</a>
    </article>
  </div>
</section>

<section class="section">
  <div class="section-head">
    <h2>使い方</h2>
    <p>3ステップで、ペットのデジタルIDをはじめられます。</p>
  </div>
  <div class="steps-grid">
    <div class="step-card">
      <span class="step-num">1</span>
      <h3>ペットを登録</h3>
      <p>名前・種類・写真を入力して個体コードを取得</p>
    </div>
    <div class="step-card">
      <span class="step-num">2</span>
      <h3>QRコードを活用</h3>
      <p>首輪タグなどにQRを付けて、迷子時の連絡をスムーズに</p>
    </div>
    <div class="step-card">
      <span class="step-num">3</span>
      <h3>迷子報告で通知</h3>
      <p>発見報告が入ると、登録ユーザー全員にメール通知</p>
    </div>
  </div>
</section>

<?php include __DIR__ . '/partials/recent_reports.php'; ?>

<section class="cta-band">
  <h2>迷子を見つけましたか？</h2>
  <p>コードが分からなくても、写真を送るだけで報告できます。</p>
  <a class="button-outline" href="<?php echo BASE_URL; ?>/lost_unknown.php">📷 写真だけで報告する</a>
</section>
