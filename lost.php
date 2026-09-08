<?php
require_once __DIR__ . '/functions.php';

$page_title = '迷子発見報告';
$page_heading = '迷子発見報告';
$page_description = 'コードが分かる場合も、分からない場合も、写真つきで発見報告できます。';

$pdo = get_pdo();
$code = $_GET['code'] ?? ($_POST['code'] ?? null);
$message = null;
$message_type = 'notice';
$pet = null;
$matches = [];

if ($code) {
    $stmt = $pdo->prepare('SELECT p.*, o.name as owner_name, o.phone as owner_phone FROM pets p LEFT JOIN owners o ON p.owner_id = o.owner_id WHERE p.code = ? LIMIT 1');
    $stmt->execute([$code]);
    $pet = $stmt->fetch();
}

if (!empty($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $message_type = $_SESSION['flash_type'] ?? 'notice';
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        http_response_code(400);
        $message = '不正な操作が検出されました。もう一度お試しください。';
        $message_type = 'alert';
    } else {
        $reporter_name = trim($_POST['reporter_name'] ?? '');
        $reporter_phone = trim($_POST['reporter_phone'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $posted_code = trim($_POST['code'] ?? '');

        if ($reporter_name === '') {
            $message = '発見者名を入力してください。';
            $message_type = 'alert';
        } elseif ($reporter_phone === '') {
            $message = '連絡先を入力してください。';
            $message_type = 'alert';
        } else {
            $found_photo_path = save_uploaded_image($_FILES['found_photo'] ?? [], 'lost_reports');

            $target_pet = null;

            if ($posted_code !== '') {
                $stmt = $pdo->prepare('SELECT p.*, o.name as owner_name, o.phone as owner_phone FROM pets p LEFT JOIN owners o ON p.owner_id = o.owner_id WHERE p.code = ? LIMIT 1');
                $stmt->execute([$posted_code]);
                $target_pet = $stmt->fetch();
            }

            if (!$target_pet && $found_photo_path) {
                $found_hash = make_image_hash(__DIR__ . '/' . $found_photo_path);

                if ($found_hash) {
                    $stmt = $pdo->query('SELECT pet_id, code, name, species, photo_path, image_hash FROM pets WHERE image_hash IS NOT NULL AND image_hash != ""');
                    $pets = $stmt->fetchAll();

                    foreach ($pets as $candidate) {
                        $distance = image_hash_distance($found_hash, $candidate['image_hash']);

                        if ($distance <= 18) {
                            $candidate['distance'] = $distance;
                            $matches[] = $candidate;
                        }
                    }

                    usort($matches, function ($a, $b) {
                        return $a['distance'] <=> $b['distance'];
                    });

                    if (!empty($matches)) {
                        $target_pet = $matches[0];
                    }
                }
            }

            if (!$target_pet) {
                $message = '報告は受け付けましたが、登録済みペットとの一致候補は見つかりませんでした。写真や場所を確認して管理者が対応します。';
                $message_type = 'notice';

                $_SESSION['flash_message'] = $message;
                $_SESSION['flash_type'] = $message_type;

                header('Location: lost.php');
                exit;
            }

            $status = !empty($matches) ? '写真候補あり' : '未対応';

            $combined_notes = "【発見者】" . $reporter_name . "\n"
                            . "【連絡先】" . $reporter_phone . "\n"
                            . "【写真】" . ($found_photo_path ?: 'なし') . "\n"
                            . "-------------------------\n"
                            . $notes;

            $stmt = $pdo->prepare('INSERT INTO lost_reports (pet_id, report_date, location, status, notes, reporter_name, reporter_phone, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $target_pet['pet_id'],
                date('Y-m-d'),
                $location,
                $status,
                $combined_notes,
                $reporter_name,
                $reporter_phone,
                $found_photo_path
            ]);

            $mail_result = notify_lost_report([
                'pet_name' => $target_pet['name'] ?? '不明',
                'code' => $target_pet['code'] ?? '不明',
                'reporter_name' => $reporter_name,
                'reporter_phone' => $reporter_phone,
                'location' => $location,
                'notes' => $notes,
                'photo_path' => $found_photo_path,
                'matches' => $matches,
                'owner_name' => $target_pet['owner_name'] ?? '',
                'pet_link' => get_app_url('pet/view.php?code=' . urlencode($target_pet['code'] ?? '')),
            ]);

            $message = '迷子発見報告を送信しました。';

            if (!empty($matches)) {
                $message .= ' 写真から似ている登録済みペット候補が見つかりました。';
            }

            if ($mail_result['total'] > 0) {
                if ($mail_result['sent'] > 0) {
                    $message .= ' 登録ユーザー ' . $mail_result['sent'] . ' 件にメール通知しました。';
                }
                if ($mail_result['failed'] > 0) {
                    $message .= ' ただし、' . $mail_result['failed'] . ' 件のメール送信に失敗しました。';
                    $message_type = 'alert';
                }
            }

            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $message_type;

            header('Location: lost.php');
            exit;
        }
    }
}
?>

<?php include __DIR__ . '/templates/header.php'; ?>

<div class="card">
  <?php if ($message): ?>
    <div class="<?php echo $message_type === 'alert' ? 'alert' : 'notice'; ?>">
      <?php echo e($message); ?>
    </div>
  <?php endif; ?>

  <form method="get" class="form-grid">
    <div class="form-group">
      <label>ペットコードで確認する</label>
      <input type="text" name="code" value="<?php echo e($code ?? ''); ?>" placeholder="例: P1234567890">
    </div>
    <button type="submit" class="button">コードを確認</button>
  </form>
</div>

<?php if (!$pet && isset($code) && $code !== ''): ?>
  <div class="alert">該当するペットが見つかりませんでした。写真つきで報告できます。</div>
<?php endif; ?>

<?php if (!empty($pet)): ?>
  <div class="card">
    <div class="section-title">
      <h2>コードで見つかったペット</h2>
      <span class="badge"><?php echo e($pet['species']); ?></span>
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

    <p><strong><?php echo e($pet['name']); ?></strong> の情報です。</p>
    <p>コード: <strong><?php echo e($pet['code']); ?></strong></p>
  </div>
<?php endif; ?>

<div class="card">
  <h2>発見報告フォーム</h2>

  <form method="post" class="form-grid" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">

    <div class="form-group">
      <label>ペットコード（分かる場合のみ）</label>
      <input type="text" name="code" value="<?php echo e($code ?? ''); ?>" placeholder="例: P1234567890">
    </div>

    <div class="form-group">
      <label>発見者名</label>
      <input type="text" name="reporter_name" required>
    </div>

    <div class="form-group">
      <label>連絡先</label>
      <input type="text" name="reporter_phone" required>
    </div>

    <div class="form-group">
      <label>発見場所</label>
      <input type="text" name="location">
    </div>

    <div class="form-group">
      <label>見つけた動物の写真</label>
      <input type="file" name="found_photo" accept="image/*">
    </div>

    <div class="form-group">
      <label>メモ</label>
      <textarea name="notes" placeholder="例: 公園の近くで保護しました。首輪はありません。"></textarea>
    </div>

    <button type="submit" class="button">発見報告を送信する</button>
  </form>
</div>

<?php if (!empty($matches)): ?>
  <div class="card">
    <h2>似ている可能性がある登録済みペット</h2>
    <p>写真の簡易比較による候補です。必ず目視で確認してください。</p>

    <div class="card-list">
      <?php foreach (array_slice($matches, 0, 5) as $match): ?>
        <div class="card-light">
          <h3>
            <?php echo e($match['name'] ?? '名前未登録'); ?>
            <span class="badge"><?php echo e($match['species'] ?? '種類未登録'); ?></span>
          </h3>

          <?php if (!empty($match['photo_path'])): ?>
            <div style="margin: 12px 0;">
              <img
                src="<?php echo BASE_URL . '/' . e($match['photo_path']); ?>"
                alt="<?php echo e($match['name'] ?? 'ペット写真'); ?>"
                style="max-width: 100%; width: 220px; border-radius: 12px; display: block;"
              >
            </div>
          <?php endif; ?>

          <p>コード: <strong><?php echo e($match['code']); ?></strong></p>
          <p>判定差分: <?php echo e($match['distance']); ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/templates/footer.php'; ?>