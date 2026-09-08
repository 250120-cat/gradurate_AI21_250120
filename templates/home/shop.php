<?php

$current = current_user();
$shopId = $current['shop_id'] ?? 0;

$shopPets = [];
$petCount = 0;

try {
    // 最近登録された5件を取得
    $stmt = $pdo->prepare(
        'SELECT pet_id, name, species, code, photo_path, created_at
         FROM pets
         WHERE shop_id = ?
         ORDER BY pet_id DESC
         LIMIT 5'
    );
    $stmt->execute([$shopId]);
    $shopPets = $stmt->fetchAll();

    $monthCount = 0;
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM pets
         WHERE shop_id = ? AND MONTH(created_at) =MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())'
    );
    $stmt->execute([$shopId]);
    $monthCount = (int)$stmt->fetchColumn();

    // 総件数を取得
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM pets
         WHERE shop_id = ?'
    );
    $stmt->execute([$shopId]);
    $petCount = (int)$stmt->fetchColumn();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $shopPets = [];
    $petCount = 0;
}

?>

<section class="section">
    <div class="section-head">
        <h2>ペットショップホーム</h2>
        <p>登録済みペットと日常業務を管理できます。</p>
    </div>

    <div class="summary-box">
        <div>
            <span><?php echo $petCount; ?></span>
            登録ペット数
        </div>

        <div>
            <span><?php echo $monthCount; ?></span>
            今月登録
        </div>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <h2>よく使う機能</h2>
    </div>

    <div class="card-list">
        <div class="card-light">
            <h3>🐾 ペット登録</h3>
            <p>新しいペットを登録します。</p>
            <a class="button-link" href="<?php echo BASE_URL; ?>/shop/register_pet.php">登録する</a>
        </div>

        <div class="card-light">
            <h3>🏪 登録ペット管理</h3>
            <p>自店で登録したペットを確認します。</p>
            <a class="button-link" href="<?php echo BASE_URL; ?>/shop/dashboard.php">確認する</a>
        </div>

        <div class="card-light">
            <h3>📋 迷子掲示板</h3>
            <p>発見報告や迷子情報を確認します。</p>
            <a class="button-link" href="<?php echo BASE_URL; ?>/lost_board.php">掲示板を見る</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="section-head">
        <h2>最近登録したペット</h2>
    </div>

    <?php if (count($shopPets) === 0): ?>
        <div class="card">
            <p>まだペットは登録されていません。</p>
        </div>
    <?php else: ?>
        <div class="card-list">
            <?php foreach ($shopPets as $pet): ?>
                <div class="card-light">
                    <h3><?php echo e($pet['name']); ?></h3>
                    <p>種類：<?php echo e($pet['species']); ?></p>
                    <p>コード：<?php echo e($pet['code']); ?></p>
                    <p>
                        登録日：
                        <?php echo e($pet['created_at']); ?>
                    </p>

                    <?php if (!empty($pet['photo_path'])): ?>
                        <img
                            src="<?php echo BASE_URL . '/' . e($pet['photo_path']); ?>"
                            alt="<?php echo e($pet['name']); ?>"
                            style="width:100%;max-width:220px;height:180px;object-fit:cover;border-radius:12px;margin-bottom:12px;"
                        >

                    <?php endif; ?>

                    <a class="button-link" href="<?php echo BASE_URL; ?>/pet/view.php?code=<?php echo urlencode($pet['code']); ?>">
                        詳細を見る
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php include __DIR__ . '/partials/recent_reports.php'; ?>

