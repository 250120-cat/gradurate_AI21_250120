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
$comparison_unavailable = false;

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
                    $matches = find_similar_pets($pdo, $found_hash);
                } else {
                    $comparison_unavailable = true;
                }
            }

            if (!$target_pet) {
                $detail_notes = $notes;
                if ($found_photo_path) {
                    $detail_notes = "【写真】" . $found_photo_path . "\n" . $detail_notes;
                }
                if (!empty($matches)) {
                    $detail_notes .= "\n\n【画像照合の候補（未確認・報告対象には未設定）】\n";
                    foreach (array_slice($matches, 0, 5) as $match) {
                        $detail_notes .= '- ' . ($match['name'] ?? '名前未登録')
                            . ' / コード: ' . ($match['code'] ?? '')
                            . ' / ハッシュ一致率: ' . $match['similarity'] . "%\n";
                    }
                }

                $stmt = $pdo->prepare('INSERT INTO lost_reports (pet_id, report_date, location, status, notes, reporter_name, reporter_phone, photo_path) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    date('Y-m-d'),
                    $location,
                    '未確認',
                    "【ペット未特定の発見報告】\n-------------------------\n{$detail_notes}",
                    $reporter_name,
                    $reporter_phone,
                    $found_photo_path
                ]);

                $notification_result = notify_lost_report([
                    'pet_name' => !empty($matches) ? '未特定（写真候補あり）' : '未特定',
                    'code' => '未特定',
                    'reporter_name' => $reporter_name,
                    'reporter_phone' => $reporter_phone,
                    'location' => $location,
                    'notes' => $detail_notes,
                    'photo_path' => $found_photo_path,
                    'matches' => $matches,
                    'pet_link' => get_app_url('lost_board.php'),
                ]);

                $message = '報告を受け付けました。';
                if ($comparison_unavailable) {
                    $message .= ' ただし、サーバーの画像処理機能が利用できず、写真照合は実行できませんでした。';
                } elseif (!empty($matches)) {
                    $message .= ' 写真から候補が見つかりましたが、ペットは自動特定せず未確認の報告として記録しました。';
                } else {
                    $message .= ' 登録済みペットとの一致は見つかりませんでした。';
                }
                $message .= ' 管理者へ通知しました。';
                $message_type = 'notice';

                $message .= ' サイト内受信箱に ' . $notification_result['sent'] . ' 件通知しました。';

                $_SESSION['flash_message'] = $message;
                $_SESSION['flash_type'] = $message_type;

                header('Location: lost.php');
                exit;
            }

            $status = !empty($matches) ? '写真候補あり' : '未対応';

            $combined_notes = "【写真】" . ($found_photo_path ?: 'なし') . "\n"
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

            $notification_result = notify_lost_report([
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

            $message .= ' サイト内受信箱に ' . $notification_result['sent'] . ' 件通知しました。';

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
      <label>メモ（掲示板で公開されます）</label>
      <textarea name="notes" placeholder="例: 公園の近くで保護しました。首輪はありません。個人の連絡先は書かないでください。"></textarea>
    </div>

    <button type="submit" class="button">発見報告を送信する</button>
  </form>
</div>

<?php if (!empty($matches)): ?>
  <div class="card">
    <h2>似ている可能性がある登録済みペット</h2>
    <p>dHashによる画像特徴量の類似候補です。AIによる個体識別ではありません。候補は報告対象へ自動設定されず、必ず目視で確認してください。</p>

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
          <p>ハッシュ一致率（参考・個体特定の確率ではありません）: <?php echo e($match['similarity']); ?>%</p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/templates/footer.php'; ?>