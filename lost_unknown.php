<?php
require_once __DIR__ . '/functions.php';

$page_title = '写真で迷子報告';
$page_heading = '写真で迷子報告';
$page_description = 'コードが分からない場合でも、写真つきで発見報告できます。';

$pdo = get_pdo();
$message = null;
$message_type = 'notice';
$matches = [];

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

        if ($reporter_name === '') {
            $message = '発見者名を入力してください。';
            $message_type = 'alert';
        } elseif ($reporter_phone === '') {
            $message = '連絡先を入力してください。';
            $message_type = 'alert';
        } elseif (empty($_FILES['found_photo']['tmp_name'])) {
            $message = '見つけた動物の写真を選択してください。';
            $message_type = 'alert';
        } else {
            $found_photo_path = save_uploaded_image($_FILES['found_photo'] ?? [], 'lost_reports');

            if (!$found_photo_path) {
                $message = '写真の保存に失敗しました。jpg、png、webp の画像を選んでください。';
                $message_type = 'alert';
            } else {
                $found_hash = make_image_hash(__DIR__ . '/' . $found_photo_path);

                if ($found_hash) {
                    $stmt = $pdo->query('SELECT pet_id, code, name, species, photo_path, image_hash FROM pets WHERE image_hash IS NOT NULL AND image_hash != ""');
                    $pets = $stmt->fetchAll();

                    foreach ($pets as $pet) {
                        $distance = image_hash_distance($found_hash, $pet['image_hash']);

                        if ($distance <= 18) {
                            $pet['distance'] = $distance;
                            $matches[] = $pet;
                        }
                    }

                    usort($matches, function ($a, $b) {
                        return $a['distance'] <=> $b['distance'];
                    });
                }

                $matched_pet_id = null;
                $status = '未確認';

                if (!empty($matches)) {
                    $matched_pet_id = $matches[0]['pet_id'];
                    $status = '写真候補あり';
                }

                if ($matched_pet_id) {
                    $combined_notes = "【コード不明の写真報告】\n"
                                    . "【発見者】" . $reporter_name . "\n"
                                    . "【連絡先】" . $reporter_phone . "\n"
                                    . "【写真】" . $found_photo_path . "\n"
                                    . "-------------------------\n"
                                    . $notes;

                    $stmt = $pdo->prepare('INSERT INTO lost_reports (pet_id, report_date, location, status, notes, reporter_name, reporter_phone, photo_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([
                        $matched_pet_id,
                        date('Y-m-d'),
                        $location,
                        $status,
                        $combined_notes,
                        $reporter_name,
                        $reporter_phone,
                        $found_photo_path
                    ]);
                }

                $matched_pet = !empty($matches) ? $matches[0] : null;

                $mail_result = notify_lost_report([
                    'pet_name' => $matched_pet['name'] ?? '不明（コード不明）',
                    'code' => $matched_pet['code'] ?? '不明',
                    'reporter_name' => $reporter_name,
                    'reporter_phone' => $reporter_phone,
                    'location' => $location,
                    'notes' => $notes,
                    'photo_path' => $found_photo_path,
                    'matches' => $matches,
                    'pet_link' => $matched_pet
                        ? get_app_url('pet/view.php?code=' . urlencode($matched_pet['code']))
                        : get_app_url('lost_board.php'),
                ]);

                $message = '写真つきの発見報告を送信しました。';

                if (!empty($matches)) {
                    $message .= ' 登録済みペットの候補が見つかりました。';
                } else {
                    $message .= ' 登録済みペットの候補は見つかりませんでした。';
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
            }
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

  <form method="post" class="form-grid" enctype="multipart/form-data">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">

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
      <input type="file" name="found_photo" accept="image/*" required>
    </div>

    <div class="form-group">
      <label>メモ</label>
      <textarea name="notes" placeholder="例: 公園の近くで保護しました。首輪はありません。"></textarea>
    </div>

    <button type="submit" class="button">写真つきで報告する</button>
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
          <p>
            <a class="button-link" href="<?php echo BASE_URL; ?>/pet/view.php?code=<?php echo urlencode($match['code']); ?>">
              詳細を見る
            </a>
          </p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/templates/footer.php'; ?>