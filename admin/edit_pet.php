<?php
require_once __DIR__ . '/../functions.php';

require_login();
require_role('admin');

$pdo = get_pdo();
$pet_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$pet_id || $pet_id < 1) {
    http_response_code(400);
    exit('ペットIDが正しくありません。');
}

$stmt = $pdo->prepare('
    SELECT p.*, s.name AS shop_name, o.name AS owner_name, o.phone AS owner_phone
    FROM pets p
    LEFT JOIN shops s ON p.shop_id = s.shop_id
    LEFT JOIN owners o ON p.owner_id = o.owner_id
    WHERE p.pet_id = ?
    LIMIT 1
');
$stmt->execute([$pet_id]);
$pet = $stmt->fetch();

if (!$pet) {
    http_response_code(404);
    exit('ペットが見つかりません。');
}

$errors = [];
$values = [
    'name' => $pet['name'] ?? '',
    'species' => $pet['species'] ?? '',
    'birthday' => $pet['birthday'] ?? '',
    'microchip' => $pet['microchip'] ?? '',
    'personality' => $pet['personality'] ?? '',
    'parent_info' => $pet['parent_info'] ?? '',
    'care_notes' => $pet['care_notes'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        http_response_code(400);
        exit('CSRF token mismatch');
    }

    foreach ($values as $field => $_) {
        $values[$field] = trim($_POST[$field] ?? '');
    }

    if ($values['name'] === '') {
        $errors[] = 'ペット名は必須です。';
    }

    if ($values['birthday'] !== '') {
        $date = DateTime::createFromFormat('!Y-m-d', $values['birthday']);
        if (!$date || $date->format('Y-m-d') !== $values['birthday']) {
            $errors[] = '誕生日を正しい日付で入力してください。';
        }
    }

    $photo_path = $pet['photo_path'];
    $image_hash = $pet['image_hash'];
    $photo = $_FILES['pet_photo'] ?? null;

    if (empty($errors) && $photo && ($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($photo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors[] = '写真のアップロードに失敗しました。';
        } else {
            $new_photo_path = save_uploaded_image($photo, 'pets');
            if (!$new_photo_path) {
                $errors[] = 'jpg、png、webp形式で5MB以下の写真を選んでください。';
            } else {
                $photo_path = $new_photo_path;
                $image_hash = make_image_hash(__DIR__ . '/../' . $new_photo_path);
            }
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('
            UPDATE pets
            SET name = ?, species = ?, birthday = ?, microchip = ?,
                personality = ?, parent_info = ?, care_notes = ?,
                photo_path = ?, image_hash = ?
            WHERE pet_id = ?
        ');
        $stmt->execute([
            $values['name'],
            $values['species'],
            $values['birthday'] !== '' ? $values['birthday'] : null,
            $values['microchip'],
            $values['personality'],
            $values['parent_info'],
            $values['care_notes'],
            $photo_path,
            $image_hash,
            $pet_id,
        ]);

        if (function_exists('add_audit_log')) {
            add_audit_log('pet_updated', 'pet', (int)$pet_id, 'ペット情報を更新しました');
        }

        header('Location: ' . BASE_URL . '/admin/edit_pet.php?id=' . $pet_id . '&saved=1');
        exit;
    }
}

$saved = isset($_GET['saved']) && $_GET['saved'] === '1';
?>
<?php include __DIR__ . '/../templates/header.php'; ?>

<div class="card">
  <div class="section-title">
    <h2>ペット情報を編集</h2>
    <span class="badge"><?php echo e($pet['code']); ?></span>
  </div>

  <?php if ($saved): ?>
    <div class="notice">ペット情報を更新しました。</div>
  <?php endif; ?>

  <?php if ($errors): ?>
    <div class="errors">
      <ul>
        <?php foreach ($errors as $error): ?>
          <li><?php echo e($error); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if (!empty($pet['photo_path'])): ?>
    <p>現在の写真:</p>
    <img src="<?php echo BASE_URL . '/' . e($pet['photo_path']); ?>" alt="<?php echo e($pet['name'] ?? 'ペット写真'); ?>" style="width:180px;border-radius:12px;">
  <?php endif; ?>

  <form method="post" class="form-grid" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">

    <div class="form-group">
      <label for="pet-name">ペット名</label>
      <input id="pet-name" type="text" name="name" value="<?php echo e($values['name']); ?>" required>
    </div>
    <div class="form-group">
      <label for="pet-species">種類</label>
      <input id="pet-species" type="text" name="species" value="<?php echo e($values['species']); ?>">
    </div>
    <div class="form-group">
      <label for="pet-birthday">誕生日</label>
      <input id="pet-birthday" type="date" name="birthday" value="<?php echo e($values['birthday']); ?>">
    </div>
    <div class="form-group">
      <label for="pet-microchip">マイクロチップ番号</label>
      <input id="pet-microchip" type="text" name="microchip" value="<?php echo e($values['microchip']); ?>">
    </div>
    <div class="form-group">
      <label for="pet-personality">性格</label>
      <textarea id="pet-personality" name="personality"><?php echo e($values['personality']); ?></textarea>
    </div>
    <div class="form-group">
      <label for="pet-parent-info">親犬・親猫情報</label>
      <textarea id="pet-parent-info" name="parent_info"><?php echo e($values['parent_info']); ?></textarea>
    </div>
    <div class="form-group">
      <label for="pet-care-notes">飼育メモ</label>
      <textarea id="pet-care-notes" name="care_notes"><?php echo e($values['care_notes']); ?></textarea>
    </div>
    <div class="form-group">
      <label for="pet-photo">写真（変更する場合のみ）</label>
      <input id="pet-photo" type="file" name="pet_photo" accept="image/jpeg,image/png,image/webp">
      <small>jpg、png、webp形式、5MB以下。選択しない場合は現在の写真を保持します。</small>
    </div>

    <p>個体コード、販売店、飼い主の関連付けはこの画面では変更しません。</p>
    <button type="submit" class="button">変更を保存</button>
    <a class="button-link" href="<?php echo BASE_URL; ?>/pet/view.php?code=<?php echo urlencode($pet['code']); ?>">個体ページへ戻る</a>
  </form>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
