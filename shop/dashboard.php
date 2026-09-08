<?php

require_once __DIR__ . '/../functions.php';

require_login();
require_role('shop');

$current = current_user();

$page_title = 'ショップ管理';
$page_description = '自店で登録したペット一覧です。';

$pdo = get_pdo();

$stmt = $pdo->prepare(
    'SELECT *
     FROM pets
     WHERE shop_id = ?
     ORDER BY pet_id DESC'
);

$stmt->execute([
    $current['shop_id']
]);

$pets = $stmt->fetchAll();

include __DIR__ . '/../templates/header.php';

?>

<div class="card">

    <h2>自店登録ペット</h2>

    <?php if (count($pets) === 0): ?>

        <p>登録済みペットはありません。</p>

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
                            style="max-width:200px;border-radius:12px;"
                            alt="ペット写真"
                        >

                    <?php endif; ?>

                    <p>
                        <a
                            class="button-link"
                            href="<?php echo BASE_URL; ?>/pet/view.php?code=<?php echo urlencode($pet['code']); ?>">
                            詳細を見る
                    </a>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>