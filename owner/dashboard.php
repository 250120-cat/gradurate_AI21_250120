<?php

require_once __DIR__ . '/../functions.php';

require_login();
require_role('owner');

$current = current_user();

$pdo = get_pdo();

/* 自分のペット */
$stmt = $pdo->prepare(
    'SELECT *
     FROM pets
     WHERE owner_id = ?
     ORDER BY pet_id DESC'
);

$stmt->execute([
    $current['owner_id']
]);

$pets = $stmt->fetchAll();

/* 迷子報告履歴 */
$lostReports = [];

$stmt = $pdo->prepare(
    'SELECT
        lr.*,
        p.name AS pet_name
     FROM lost_reports lr
     INNER JOIN pets p
        ON lr.pet_id = p.pet_id
     WHERE p.owner_id = ?
     ORDER BY lr.report_date DESC
     LIMIT 10'
);

$stmt->execute([
    $current['owner_id']
]);

$lostReports = $stmt->fetchAll();

/* 相談履歴 */
$consultations = [];

$stmt = $pdo->prepare(
    'SELECT
        thread_id,
        title,
        created_at,
        genre
     FROM consultation_threads
     WHERE creator_id = ?
     ORDER BY created_at DESC
     LIMIT 10'
);

$stmt->execute([
    $current['id']
]);

$consultations = $stmt->fetchAll();

/* 統計 */
$petCount = count($pets);

try {
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM lost_reports lr
         INNER JOIN pets p
            ON lr.pet_id = p.pet_id
         WHERE p.owner_id = ?'
    );

    $stmt->execute([
        $current['owner_id']
    ]);

    $lostCount = (int)$stmt->fetchColumn();

} catch (PDOException $e) {
    error_log($e->getMessage());
    $lostCount = 0;
}

$page_title = 'マイペット';
$page_description = '自分のペット情報を管理します。';

include __DIR__ . '/../templates/header.php';

?>

<div class="card">

    <h2>マイページ</h2>

    <div class="summary-box">

        <div>
            <span><?php echo $petCount; ?></span>
            登録ペット
        </div>

        <div>
            <span><?php echo $lostCount; ?></span>
            迷子報告
        </div>

        <div>
            <span><?php echo count($consultations); ?></span>
            相談履歴
        </div>

    </div>

</div>

<div class="card">

    <h2>自分のペット</h2>

    <?php if (empty($pets)): ?>

        <p>登録されているペットはありません。</p>

    <?php else: ?>

        <div class="card-list">

            <?php foreach ($pets as $pet): ?>

                <div class="card-light">

                    <h3><?php echo e($pet['name']); ?></h3>

                    <p>
                        種類：
                        <?php echo e($pet['species']); ?>
                    </p>

                    <p>
                        コード：
                        <?php echo e($pet['code']); ?>
                    </p>

                    <?php if (!empty($pet['photo_path'])): ?>

                        <img 
                            src="<?php echo BASE_URL . '/' . e($pet['photo_path']); ?>" 
                            alt="<?php echo e($pet['name']); ?>"
                            style="
                                width: 100%;
                                max-width: 220px;
                                height: 180px;
                                object-fit: cover;
                                border-radius: 12px;
                                margin-bottom: 12px;
                            "
                        >

                    <?php endif; ?>

                    <a 
                        class="button-link" 
                        href="<?php echo BASE_URL; ?>/pet/view.php?code=<?php echo urlencode($pet['code']); ?>"
                    >
                        詳細を見る
                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<div class="card">

    <h2>迷子報告履歴</h2>

    <?php if (empty($lostReports)): ?>

        <p>迷子報告はありません。</p>

    <?php else: ?>

        <div class="table-scroll">

            <table class="data-table">

                <thead>
                    <tr>
                        <th>日付</th>
                        <th>ペット名</th>
                        <th>場所</th>
                        <th>状態</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($lostReports as $report): ?>

                    <tr>

                        <td>
                            <?php echo e($report['report_date']); ?>
                        </td>

                        <td>
                            <?php echo e($report['pet_name']); ?>
                        </td>

                        <td>
                            <?php echo e($report['location']); ?>
                        </td>

                        <td>
                            <?php echo e($report['status']); ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>

<div class="card">

    <h2>相談履歴</h2>

    <?php if (empty($consultations)): ?>

        <p>相談履歴はありません。</p>

    <?php else: ?>

        <div class="table-scroll">

            <table class="data-table">

                <thead>
                    <tr>
                        <th>日時</th>
                        <th>ジャンル</th>
                        <th>タイトル</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($consultations as $thread): ?>

                    <tr>

                        <td>
                            <?php echo e($thread['created_at']); ?>
                        </td>

                        <td>
                            <?php echo e($thread['genre'] ?? '未分類'); ?>
                        </td>

                        <td>
                            <?php echo e($thread['title']); ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>