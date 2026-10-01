<?php
require_once __DIR__ . '/functions.php';

$page_title = '写真で迷子報告';
$page_heading = '写真で迷子報告';
$page_description = 'コードが分からない場合でも、写真つきで発見報告できます。';

$pdo = get_pdo();
$message = null;
$message_type = 'notice';
$matches = [];
$comparison_unavailable = false;

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
                    $matches = find_similar_pets($pdo, $found_hash);
                } else {
                    $comparison_unavailable = true;
                }

                $combined_notes = "【コード不明の写真報告】\n"
                                . "【写真】" . $found_photo_path . "\n"
                                . "-------------------------\n"
                                . $notes;
                if (!empty($matches)) {
                    $combined_notes .= "\n\n【画像照合の候補（未確認・報告対象には未設定）】\n";
                    foreach ($matches as $match) {
                        $combined_notes .= '- ' . ($match['name'] ?? '名前未登録')
                            . ' / コード: ' . ($match['code'] ?? '')
                            . ' / ハッシュ一致率: ' . $match['similarity'] . "%\n";
                    }
                }

                $stmt = $pdo->prepare('INSERT INTO lost_reports (pet_id, report_date, location, status, notes, reporter_name, reporter_phone, photo_path) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([
                    date('Y-m-d'),
                    $location,
                    '未確認',
                    $combined_notes,
                    $reporter_name,
                    $reporter_phone,
                    $found_photo_path
                ]);

                $notification_result = notify_lost_report([
                    'pet_name' => '未特定（写真候補あり）',
                    'code' => '未特定',
                    'reporter_name' => $reporter_name,
                    'reporter_phone' => $reporter_phone,
                    'location' => $location,
                    'notes' => $combined_notes,
                    'photo_path' => $found_photo_path,
                    'matches' => $matches,
                    'pet_link' => get_app_url('lost_board.php'),
                ]);

                $message = '写真つきの発見報告を送信しました。';

                if (!empty($matches)) {
                    $message .= ' 登録済みペットの候補が見つかりました。';
                } elseif ($comparison_unavailable) {
                    $message .= ' ただし、サーバーの画像処理機能が利用できず、写真照合は実行できませんでした。';
                } else {
                    $message .= ' 登録済みペットの候補は見つかりませんでした。';
                }

                $message .= ' サイト内受信箱に ' . $notification_result['sent'] . ' 件通知しました。';
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
      <label>メモ（掲示板で公開されます）</label>
      <textarea name="notes" placeholder="例: 公園の近くで保護しました。首輪はありません。個人の連絡先は書かないでください。"></textarea>
    </div>

    <button type="submit" class="button">写真つきで報告する</button>
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