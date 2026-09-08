<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';

$page_title = 'ペット登録';
$page_description = 'ペットショップ向けの登録フォームです。登録後、個体コードとQRコードを発行します。';

$pdo = get_pdo();
$errors = [];

$old = [
    'name' => '',
    'species' => '',
    'birthday' => '',
    'microchip' => '',
    'shop_name' => '',
    'owner_name' => '',
    'owner_phone' => '',
    'personality' => '',
    'parent_info' => '',
    'care_notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        http_response_code(400);
        echo 'CSRF token mismatch';
        exit;
    }

    $old = [
        'name' => trim($_POST['name'] ?? ''),
        'species' => trim($_POST['species'] ?? ''),
        'birthday' => trim($_POST['birthday'] ?? ''),
        'microchip' => trim($_POST['microchip'] ?? ''),
        'shop_name' => trim($_POST['shop_name'] ?? ''),
        'owner_name' => trim($_POST['owner_name'] ?? ''),
        'owner_phone' => trim($_POST['owner_phone'] ?? ''),
        'personality' => trim($_POST['personality'] ?? ''),
        'parent_info' => trim($_POST['parent_info'] ?? ''),
        'care_notes' => trim($_POST['care_notes'] ?? ''),
    ];

    if ($old['name'] === '') {
        $errors[] = 'ペット名は必須です。';
    }

    if ($old['birthday'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $old['birthday'])) {
        $errors[] = '誕生日の形式が正しくありません。';
    }

    if ($old['owner_phone'] !== '' && !preg_match('/^[0-9\-\+\s]+$/', $old['owner_phone'])) {
        $errors[] = '飼い主電話番号は数字、ハイフン、プラス記号、スペースで入力してください。';
    }

    if (empty($_FILES['pet_photo']['tmp_name'])) {
        $errors[] = '顔写真は必須です。';
    }

    if (empty($errors)) {
        $shop_id = null;

        if ($old['shop_name']) {
            $stmt = $pdo->prepare('SELECT shop_id FROM shops WHERE name = ? LIMIT 1');
            $stmt->execute([$old['shop_name']]);
            $row = $stmt->fetch();

            if ($row) {
                $shop_id = $row['shop_id'];
            } else {
                $stmt = $pdo->prepare('INSERT INTO shops (name) VALUES (?)');
                $stmt->execute([$old['shop_name']]);
                $shop_id = $pdo->lastInsertId();
            }
        }

        $owner_id = null;

        if ($old['owner_name']) {

            // 同じ名前・電話番号の飼い主が存在するか確認
            $stmt = $pdo->prepare(
                'SELECT owner_id
                FROM owners
                WHERE name = ?
                AND (
                    phone = ?
                    OR (phone IS NULL AND ? = "")
                    OR (phone = "" AND ? = "")
                )
                LIMIT 1'
            );

            $stmt->execute([
                $old['owner_name'],
                $old['owner_phone'],
                $old['owner_phone'],
                $old['owner_phone']
            ]);

            $owner = $stmt->fetch();

            if ($owner) {

                // 既存データを利用
                $owner_id = $owner['owner_id'];

            } else {

                // 新規登録
                $stmt = $pdo->prepare(
                    'INSERT INTO owners (name, phone)
                    VALUES (?, ?)'
                );

                $stmt->execute([
                    $old['owner_name'],
                    $old['owner_phone']
                ]);

                $owner_id = $pdo->lastInsertId();
            }
        }
        $photo_path = save_uploaded_image($_FILES['pet_photo'] ?? [], 'pets');
        $image_hash = null;

        if ($photo_path) {
            $image_hash = make_image_hash(__DIR__ . '/../' . $photo_path);
        } else {
            $errors[] = '顔写真の保存に失敗しました。jpg、png、webp の画像を選んでください。';
        }

        if (empty($errors)) {
            $code = 'P' . time() . rand(1000, 9999);

            $stmt = $pdo->prepare('
                INSERT INTO pets
                (
                    code,
                    name,
                    species,
                    birthday,
                    shop_id,
                    owner_id,
                    microchip,
                    photo_path,
                    image_hash,
                    personality,
                    parent_info,
                    care_notes,
                    created_by_user_id
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $stmt->execute([
                $code,
                $old['name'],
                $old['species'],
                $old['birthday'] ?: null,
                $shop_id,
                $owner_id,
                $old['microchip'],
                $photo_path,
                $image_hash,
                $old['personality'],
                $old['parent_info'],
                $old['care_notes'],
                current_user()['id'] ?? null
            ]);

            if (function_exists('add_audit_log')) {
                add_audit_log('pet_created', 'pet', (int)$pdo->lastInsertId(), 'ペット情報を登録しました');
            }

            header('Location: ../pet/view.php?code=' . urlencode($code));
            exit;
        }
    }
}
?>

<?php include __DIR__ . '/../templates/header.php'; ?>

<div class="card">
  <h2>ペット登録</h2>
  <p>新しいペットを登録して、個体コードとQRコードを発行します。</p>

  <?php if (!empty($errors)): ?>
    <div class="errors">
      <ul>
        <?php foreach ($errors as $error): ?>
          <li><?php echo e($error); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" class="form-grid" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">

    <div class="form-group">
      <label>ペット名</label>
      <input type="text" name="name" value="<?php echo e($old['name']); ?>" required>
    </div>

    <div class="form-group">
      <label>顔写真</label>
      <input type="file" name="pet_photo" accept="image/*" required>
    </div>

    <div class="form-group">
      <label>種類（例: 犬、猫）</label>
      <input type="text" name="species" value="<?php echo e($old['species']); ?>">
    </div>

    <div class="form-group">
      <label>誕生日</label>
      <input type="date" name="birthday" value="<?php echo e($old['birthday']); ?>">
    </div>

    <div class="form-group">
      <label>性格</label>
      <textarea name="personality" placeholder="例: 人懐っこい、怖がり、音に敏感など"><?php echo e($old['personality']); ?></textarea>
    </div>

    <div class="form-group">
      <label>親犬・親猫情報</label>
      <textarea name="parent_info" placeholder="例: 父犬、母犬、血統、出生情報など"><?php echo e($old['parent_info']); ?></textarea>
    </div>

    <div class="form-group">
      <label>飼育メモ</label>
      <textarea name="care_notes" placeholder="例: 食事の傾向、生活環境、注意点など"><?php echo e($old['care_notes']); ?></textarea>
    </div>

    <div class="form-group">
      <label>マイクロチップ番号</label>
      <input type="text" name="microchip" value="<?php echo e($old['microchip']); ?>">
    </div>

    <div class="form-group">
      <label>販売店名（任意）</label>
      <input type="text" name="shop_name" value="<?php echo e($old['shop_name']); ?>">
    </div>

    <div class="form-group">
      <label>飼い主名（任意）</label>
      <input type="text" name="owner_name" value="<?php echo e($old['owner_name']); ?>">
    </div>

    <div class="form-group">
      <label>飼い主電話番号（任意）</label>
      <input type="text" name="owner_phone" value="<?php echo e($old['owner_phone']); ?>">
    </div>

    <button type="submit" class="button">登録してコード発行</button>
  </form>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>