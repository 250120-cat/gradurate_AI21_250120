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

$stmt = $pdo->prepare('SELECT pet_id, code, name, photo_path FROM pets WHERE pet_id = ? LIMIT 1');
$stmt->execute([$pet_id]);
$pet = $stmt->fetch();
if (!$pet) {
    http_response_code(404);
    exit('ペットが見つかりません。');
}

$related_tables = [
    'health_records' => '健康記録',
    'vaccinations' => 'ワクチン履歴',
    'surgeries' => '手術履歴',
    'allergies' => 'アレルギー情報',
    'training_logs' => 'しつけ記録',
    'lost_reports' => '迷子報告',
];
$related_counts = [];
foreach ($related_tables as $table => $label) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE pet_id = ?");
    $stmt->execute([$pet_id]);
    $related_counts[$table] = [
        'label' => $label,
        'count' => (int)$stmt->fetchColumn(),
    ];
}

$linked_threads_stmt = $pdo->prepare('SELECT COUNT(*) FROM consultation_threads WHERE pet_id = ?');
$linked_threads_stmt->execute([$pet_id]);
$linked_threads = (int)$linked_threads_stmt->fetchColumn();

$report_photos_stmt = $pdo->prepare('SELECT photo_path FROM lost_reports WHERE pet_id = ? AND photo_path IS NOT NULL');
$report_photos_stmt->execute([$pet_id]);
$report_photos = $report_photos_stmt->fetchAll(PDO::FETCH_COLUMN);

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? '')) {
        http_response_code(400);
        exit('CSRF token mismatch');
    }

    $confirmation_code = trim($_POST['confirmation_code'] ?? '');
    if (!hash_equals((string)$pet['code'], $confirmation_code)) {
        $error = '確認のため、個体コードを正確に入力してください。';
    } else {
        try {
            $pdo->beginTransaction();
            foreach (array_keys($related_tables) as $table) {
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE pet_id = ?");
                $stmt->execute([$pet_id]);
            }

            $stmt = $pdo->prepare('UPDATE consultation_threads SET pet_id = NULL WHERE pet_id = ?');
            $stmt->execute([$pet_id]);

            $stmt = $pdo->prepare('DELETE FROM pets WHERE pet_id = ?');
            $stmt->execute([$pet_id]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('対象のペットを削除できませんでした。');
            }
            $pdo->commit();
        } catch (PDOException | RuntimeException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = '削除に失敗しました。データベースの状態を確認して、もう一度お試しください。';
        }

        if ($error === null) {
            if (function_exists('add_audit_log')) {
                add_audit_log('pet_deleted', 'pet', (int)$pet_id, 'ペットと関連記録を削除しました: ' . $pet['code']);
            }

            $photo_cleanup_warning = false;
            $project_root = realpath(__DIR__ . '/..');
            $remove_uploaded_file = function ($relative_path, $folder) use ($project_root) {
                $expected_prefix = 'uploads/' . $folder . '/';
                if (!$project_root || strpos($relative_path, $expected_prefix) !== 0) {
                    return true;
                }

                $upload_root = realpath($project_root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder);
                $file_path = realpath($project_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_path));
                if (!$file_path) {
                    return true;
                }
                if (!$upload_root || strpos($file_path, $upload_root . DIRECTORY_SEPARATOR) !== 0 || !is_file($file_path)) {
                    return false;
                }
                return unlink($file_path);
            };

            if (!empty($pet['photo_path']) && !$remove_uploaded_file($pet['photo_path'], 'pets')) {
                $photo_cleanup_warning = true;
            }
            foreach ($report_photos as $report_photo) {
                if (!$remove_uploaded_file($report_photo, 'lost_reports')) {
                    $photo_cleanup_warning = true;
                }
            }
            $_SESSION['pet_deleted'] = true;
            $_SESSION['pet_delete_photo_warning'] = $photo_cleanup_warning;
            header('Location: ' . BASE_URL . '/admin/pets.php');
            exit;
        }
    }
}
?>
<?php include __DIR__ . '/../templates/header.php'; ?>

<div class="card">
  <h2>ペットを完全削除</h2>
  <div class="alert">
    <strong>この操作は取り消せません。</strong>
    ペット本体、登録写真、迷子報告の写真、および以下の関連記録を完全に削除します。関連する相談スレッドと添付ファイルは削除せず、ペットとの紐付けだけを解除します。
  </div>

  <?php if ($error): ?>
    <div class="errors"><ul><li><?php echo e($error); ?></li></ul></div>
  <?php endif; ?>

  <dl>
    <dt>ペット名</dt>
    <dd><?php echo e($pet['name'] ?? '名前未登録'); ?></dd>
    <dt>個体コード</dt>
    <dd><strong><?php echo e($pet['code']); ?></strong></dd>
  </dl>

  <ul>
    <?php foreach ($related_counts as $related): ?>
      <li><?php echo e($related['label']); ?>: <?php echo $related['count']; ?>件を削除</li>
    <?php endforeach; ?>
    <li>関連相談スレッド: <?php echo $linked_threads; ?>件は残し、ペットとの関連付けを解除</li>
  </ul>

  <form method="post" class="form-grid">
    <input type="hidden" name="_csrf" value="<?php echo csrf_token(); ?>">
    <div class="form-group">
      <label for="confirmation-code">続行するには個体コード「<?php echo e($pet['code']); ?>」を入力してください</label>
      <input id="confirmation-code" type="text" name="confirmation_code" autocomplete="off" required>
    </div>
    <button type="submit" class="button" style="background:#b91c1c;">ペットと関連記録を完全削除</button>
    <a class="button-link" href="<?php echo BASE_URL; ?>/admin/pets.php">キャンセルして一覧へ戻る</a>
  </form>
</div>

<?php include __DIR__ . '/../templates/footer.php'; ?>
