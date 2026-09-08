<?php

// 2. ユーザー情報の取得（未定義エラー対策）
if (function_exists('current_user')) {
    $user = current_user();
} else {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $user = $_SESSION['user'] ?? [];
}

$ownerId = $user['owner_id'] ?? 0;

$myPets = [];
$vaccineDue = [];
$recent_reports = [];

// 3. データベースからのデータ取得処理
try {
    if (isset($pdo) && $pdo instanceof PDO) {

        // マイペット一覧を取得
        if ($ownerId) {
            $stmt = $pdo->prepare("
                SELECT *
                FROM pets
                WHERE owner_id = ?
                ORDER BY pet_id DESC
            ");
            $stmt->execute([$ownerId]);
            $myPets = $stmt->fetchAll();
        }

        // 直近の迷子報告を取得
        $stmt = $pdo->query("
            SELECT
                lr.*,
                p.name AS pet_name,
                p.species,
                p.photo_path AS pet_photo_path
            FROM lost_reports lr
            LEFT JOIN pets p
            ON lr.pet_id = p.pet_id
            ORDER BY lr.created_at DESC
            LIMIT 3
        ");

        $recent_reports = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $myPets = [];
    $recent_reports = [];
}
?>

<section class="section">
    <div class="section-head">
        <h2>飼い主ホーム</h2>
        <p>登録されているペットの情報を確認できます。</p>
    </div>
</section>

<section class="section">
    <div class="card-list">

        <div class="card-light">
            <h3>🐾 個体情報</h3>
            <p>ペットの情報を確認します。</p>
            <a
                class="button-link"
                href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/pet/view.php"
            >
                詳細を見る
            </a>
        </div>

        <div class="card-light">
            <h3>💬 相談掲示板</h3>
            <p>相談掲示板を利用します。</p>
            <a
                class="button-link"
                href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/consultation.php"
            >
                相談する
            </a>
        </div>

        <div class="card-light">
            <h3>🆘 迷子報告</h3>
            <p>迷子報告を行います。</p>
            <a
                class="button-link"
                href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/lost.php"
            >
                迷子報告
            </a>
        </div>

    </div>
</section>

<section class="section">
    <div class="section-head">
        <h2>マイペット一覧</h2>
    </div>

    <?php if (empty($myPets)): ?>

        <div class="card">
            <p>登録されているペットはありません。</p>
        </div>

    <?php else: ?>

        <div class="card-list">

            <?php foreach ($myPets as $pet): ?>

                <div class="card-light">

                    <?php if (!empty($pet['photo_path'])): ?>
                        <img
                            src="<?php echo (defined('BASE_URL') ? BASE_URL : '') . '/' . (function_exists('e') ? e($pet['photo_path']) : htmlspecialchars($pet['photo_path'], ENT_QUOTES, 'UTF-8')); ?>"
                            alt="<?php echo function_exists('e') ? e($pet['name']) : htmlspecialchars($pet['name'], ENT_QUOTES, 'UTF-8'); ?>"
                            style="width:100%;max-width:220px;height:180px;object-fit:cover"
                        >
                    <?php endif; ?>

                    <h3><?php echo function_exists('e') ? e($pet['name']) : htmlspecialchars($pet['name'], ENT_QUOTES, 'UTF-8'); ?></h3>

                    <p>
                        種類：
                        <?php echo function_exists('e') ? e($pet['species'] ?? '未登録') : htmlspecialchars($pet['species'] ?? '未登録', ENT_QUOTES, 'UTF-8'); ?>
                    </p>

                    <p>
                        コード：
                        <?php echo function_exists('e') ? e($pet['code']) : htmlspecialchars($pet['code'], ENT_QUOTES, 'UTF-8'); ?>
                    </p>

                    <?php
                    $age = '';
                    if (!empty($pet['birthday'])) {
                        $birth = new DateTime($pet['birthday']);
                        $today = new DateTime();
                        $diff = $birth->diff($today);
                        $age = $diff->y . '歳 ' . $diff->m . 'か月';
                    }
                    ?>

                    <p>
                        年齢：
                        <?php echo $age ?: '未登録'; ?>
                    </p>

                    <a
                        class="button-link"
                        href="<?php echo defined('BASE_URL') ? BASE_URL : ''; ?>/pet/view.php?code=<?php echo urlencode($pet['code']); ?>"
                    >
                        詳細を見る
                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>