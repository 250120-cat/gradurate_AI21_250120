<?php

// 例: ログイン中の病院IDを取得（システムの実装に合わせて調整してください）
$current = current_user();
$clinicId = $current['clinic_id'] ?? 0;

$healthCount = 0;
$vaccineCount = 0;
$surgeryCount = 0;
$allergyCount = 0;

try {
    // 1回のクエリで各件数をまとめて取得（clinic_id で絞り込み）
    $stmt = $pdo->prepare(
    'SELECT 
        (SELECT COUNT(*) FROM health_records WHERE clinic_id = :h_id) AS health_count,
        (SELECT COUNT(*) FROM vaccinations WHERE clinic_id = :h_id) AS vaccine_count,
        (SELECT COUNT(*) FROM surgeries WHERE clinic_id = :h_id) AS surgery_count'
        );
    
    
    $stmt->execute([':h_id' => $clinicId]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($counts) {
        $healthCount = (int)$counts['health_count'];
        $vaccineCount = (int)$counts['vaccine_count'];
        $surgeryCount = (int)$counts['surgery_count'];
    }
    $allergyCount = (int)$pdo
        ->query('SELECT COUNT(*) FROM allergies')
        ->fetchColumn();

} catch (PDOException $e) {
    error_log($e->getMessage());
}

?>

<section class="section">

    <div class="section-head">
        <h2>動物病院ホーム</h2>
        <p>診療記録と医療情報を管理できます。</p>
    </div>

    <div class="summary-box">

        <div>
            <span><?php echo $healthCount; ?></span>
            診察記録
        </div>

        <div>
            <span><?php echo $vaccineCount; ?></span>
            ワクチン
        </div>

        <div>
            <span><?php echo $surgeryCount; ?></span>
            手術履歴
        </div>

        <div>
            <span><?php echo $allergyCount; ?></span>
            アレルギー
        </div>

    </div>

</section>

<div class="clinic-status-card">

    <h3>🏥 本日の診療状況</h3>

    <div class="clinic-status-grid">

        <div class="clinic-status-item">
            <span class="clinic-status-value">
                <?php echo $healthCount; ?>
            </span>
            <span class="clinic-status-label">
                診察記録
            </span>
        </div>

        <div class="clinic-status-item">
            <span class="clinic-status-value">
                <?php echo $vaccineCount; ?>
            </span>
            <span class="clinic-status-label">
                ワクチン
            </span>
        </div>

        <div class="clinic-status-item">
            <span class="clinic-status-value">
                <?php echo $surgeryCount; ?>
            </span>
            <span class="clinic-status-label">
                手術履歴
            </span>
        </div>

        <div class="clinic-status-item">
            <span class="clinic-status-value">
                <?php echo $allergyCount; ?>
            </span>
            <span class="clinic-status-label">
                アレルギー
            </span>
        </div>

    </div>

</div>
<section class="section">

    <div class="section-head">
        <h2>よく使う機能</h2>
    </div>

    <div class="card-list">

        <div class="card-light">
            <h3>🏥 診療記録</h3>
            <p>
                個体コードからペットを検索して診療情報を登録します。
            </p>
            <a class="button-link" href="<?php echo BASE_URL; ?>/clinic/record.php">
                診療記録を開く
            </a>
        </div>

        <div class="card-light">
            <h3>📋 迷子掲示板</h3>
            <p>
                発見報告や迷子情報を確認できます。
            </p>
            <a class="button-link" href="<?php echo BASE_URL; ?>/lost_board.php">
                掲示板を見る
            </a>
        </div>

        <div class="card-light">
            <h3>📰 動物ニュース</h3>
            <p>
                最新の動物ニュースを確認できます。
            </p>
            <a class="button-link" href="<?php echo BASE_URL; ?>/news.php">
                ニュースを見る
            </a>
        </div>

    </div>

</section>