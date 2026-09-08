<?php
require_once __DIR__ . '/../functions.php';

require_login();
require_role(['clinic', 'admin']);

$page_title = '動物病院 診療記録';
$page_description = '個体コードからペットを検索し、診察・ワクチン・手術・アレルギー情報を登録できます。';
$current = current_user();

echo '<pre>';
print_r(current_user());
echo '</pre>';



$pdo = get_pdo();

$code = $_GET['code'] ?? ($_POST['code'] ?? '');
$message = null;
$message_type = 'notice';
$pet = null;

if ($code !== '') {
    $stmt = $pdo->prepare('
        SELECT p.*, o.name AS owner_name, o.phone AS owner_phone
        FROM pets p
        LEFT JOIN owners o ON p.owner_id = o.owner_id
        WHERE p.code = ?
        LIMIT 1
    ');
    $stmt->execute([$code]);
    $pet = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        http_response_code(400);
        exit('CSRF token mismatch');
    }

    if (!$pet) {
        $message = '該当するペットが見つかりません。個体コードを確認してください。';
        $message_type = 'alert';
    } else {
        $pet_id = $pet['pet_id'];

        if (isset($_POST['add_health'])) {
            $stmt = $pdo->prepare('INSERT INTO health_records (pet_id, clinic_id, record_date, details, weight) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
            $pet_id,
            $current['clinic_id'] ?? null,
            $_POST['record_date'] ?? null,
            trim($_POST['details'] ?? ''),
            $_POST['weight'] !== '' ? $_POST['weight'] : null
        ]);

            if (function_exists('add_audit_log')) {
                add_audit_log('clinic_health_created', 'pet', (int)$pet_id, '動物病院が診察記録を追加しました');
            }

            $message = '診察記録を登録しました。';
        }

        if (isset($_POST['add_vaccine'])) {
            $stmt = $pdo->prepare('INSERT INTO vaccinations (pet_id, clinic_id, vaccine_name, vaccinated_date, next_due_date, notes) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $pet_id,
                null,
                trim($_POST['vaccine_name'] ?? ''),
                $_POST['vaccinated_date'] ?? null,
                $_POST['next_due_date'] ?: null,
                trim($_POST['vaccine_notes'] ?? '')
            ]);

            if (function_exists('add_audit_log')) {
                add_audit_log('clinic_vaccine_created', 'pet', (int)$pet_id, '動物病院がワクチン履歴を追加しました');
            }

            $message = 'ワクチン履歴を登録しました。';
        }

        if (isset($_POST['add_surgery'])) {
            $stmt = $pdo->prepare('INSERT INTO surgeries (pet_id, clinic_id, surgery_name, surgery_date, notes) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
            $pet_id,
            $current['clinic_id'] ?? null,
                trim($_POST['surgery_name'] ?? ''),
                $_POST['surgery_date'] ?? null,
                trim($_POST['surgery_notes'] ?? '')
            ]);

            if (function_exists('add_audit_log')) {
                add_audit_log('clinic_surgery_created', 'pet', (int)$pet_id, '動物病院が手術履歴を追加しました');
            }

            $message = '手術履歴を登録しました。';
        }

        if (isset($_POST['add_allergy'])) {
            $stmt = $pdo->prepare('INSERT INTO allergies (pet_id, allergy_name, severity, notes) VALUES (?, ?, ?, ?)');
            $stmt->execute([
            $pet_id,
            $current['clinic_id'] ?? null,
                trim($_POST['allergy_name'] ?? ''),
                trim($_POST['severity'] ?? ''),
                trim($_POST['allergy_notes'] ?? '')
            ]);

            if (function_exists('add_audit_log')) {
                add_audit_log('clinic_allergy_created', 'pet', (int)$pet_id, '動物病院がアレルギー情報を追加しました');
            }

            $message = 'アレルギー情報を登録しました。';
        }
    }
}
?>

<?php include __DIR__ . '/../templates/header.php'; ?>

<div class="card">
  <h2>動物病院 診療記録</h2>
  <p>個体コードを入力して、診察記録や医療情報を登録します。</p>

  <?php if ($message): ?>
    <div class="<?php echo $message_type === 'alert' ? 'alert' : 'notice'; ?>">
      <?php echo e($message); ?>
    </div>
  <?php endif; ?>

  <form method="get" class="form-grid">
    <div class="form-group">
      <label>個体コード</label>
      <input type="text" name="code" value="<?php echo e($code); ?>" placeholder="例: P1234567890" required>
    </div>

    <button type="submit" class="button">検索</button>
  </form>
</div>

<?php if ($code !== '' && !$pet): ?>
  <div class="alert">該当するペットが見つかりませんでした。</div>
<?php endif; ?>

<?php if ($pet): ?>
  <div class="card">
    <div class="section-title">
      <h2><?php echo e($pet['name'] ?? '名前未登録'); ?></h2>
      <span class="badge"><?php echo e($pet['species'] ?? '種類未登録'); ?></span>
    </div>

    <?php if (!empty($pet['photo_path'])): ?>
      <div style="margin: 12px 0;">
        <img
          src="<?php echo BASE_URL . '/' . e($pet['photo_path']); ?>"
          alt="<?php echo e($pet['name'] ?? 'ペット写真'); ?>"
          style="max-width: 100%; width: 260px; border-radius: 12px; display: block;"
        >
      </div>
    <?php endif; ?>

    <p>コード: <strong><?php echo e($pet['code']); ?></strong></p>
    <p>誕生日: <?php echo e($pet['birthday'] ?? '未登録'); ?></p>
    <p>飼い主: <?php echo e($pet['owner_name'] ?? '未登録'); ?></p>
    <p>連絡先: <?php echo e($pet['owner_phone'] ?? '未登録'); ?></p>

    <p>
      <a class="button-link" href="<?php echo BASE_URL; ?>/pet/view.php?code=<?php echo urlencode($pet['code']); ?>">
        個体ページを見る
      </a>
    </p>
  </div>

  <div class="card-list">
    <div class="card-light">
      <h3>診察記録を追加</h3>

      <form method="post" class="form-grid">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="code" value="<?php echo e($pet['code']); ?>">

        <div class="form-group">
          <label>診察日</label>
          <input type="date" name="record_date" required>
        </div>

        <div class="form-group">
          <label>診察内容</label>
          <textarea name="details" required></textarea>
        </div>

        <div class="form-group">
          <label>体重（kg）</label>
          <input type="text" name="weight">
        </div>

        <button type="submit" name="add_health" class="button">診察記録を登録</button>
      </form>
    </div>

    <div class="card-light">
      <h3>ワクチン履歴を追加</h3>

      <form method="post" class="form-grid">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="code" value="<?php echo e($pet['code']); ?>">

        <div class="form-group">
          <label>ワクチン名</label>
          <input type="text" name="vaccine_name" required>
        </div>

        <div class="form-group">
          <label>接種日</label>
          <input type="date" name="vaccinated_date" required>
        </div>

        <div class="form-group">
          <label>次回予定日</label>
          <input type="date" name="next_due_date">
        </div>

        <div class="form-group">
          <label>メモ</label>
          <textarea name="vaccine_notes"></textarea>
        </div>

        <button type="submit" name="add_vaccine" class="button">ワクチン履歴を登録</button>
      </form>
    </div>

    <div class="card-light">
      <h3>手術履歴を追加</h3>

      <form method="post" class="form-grid">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="code" value="<?php echo e($pet['code']); ?>">

        <div class="form-group">
          <label>手術名</label>
          <input type="text" name="surgery_name" required>
        </div>

        <div class="form-group">
          <label>手術日</label>
          <input type="date" name="surgery_date" required>
        </div>

        <div class="form-group">
          <label>メモ</label>
          <textarea name="surgery_notes"></textarea>
        </div>

        <button type="submit" name="add_surgery" class="button">手術履歴を登録</button>
      </form>
    </div>

    <div class="card-light">
      <h3>アレルギー情報を追加</h3>

      <form method="post" class="form-grid">
        <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
        <input type="hidden" name="code" value="<?php echo e($pet['code']); ?>">

        <div class="form-group">
          <label>アレルギー名</label>
          <input type="text" name="allergy_name" required>
        </div>

        <div class="form-group">
          <label>重症度</label>
          <select name="severity">
            <option value="">未設定</option>
            <option value="軽度">軽度</option>
            <option value="中度">中度</option>
            <option value="重度">重度</option>
          </select>
        </div>

        <div class="form-group">
          <label>メモ</label>
          <textarea name="allergy_notes"></textarea>
        </div>

        <button type="submit" name="add_allergy" class="button">アレルギー情報を登録</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../templates/footer.php'; ?>