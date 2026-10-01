<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/functions.php';

if (!function_exists('imagecreatefromstring')) {
    fwrite(STDERR, "エラー: PHP GD拡張が無効です。php.iniでextension=gdを有効にし、再実行してください。\n");
    exit(1);
}

$apply = in_array('--apply', $argv, true);
$pdo = get_pdo();
$pets = $pdo->query('SELECT pet_id, photo_path, image_hash FROM pets')->fetchAll();
$petUploadDirectory = realpath(dirname(__DIR__) . '/uploads/pets');
$updates = [];
$missing = 0;
$failed = 0;

foreach ($pets as $pet) {
    $newHash = null;
    $imageReadable = false;
    $relativePath = (string)($pet['photo_path'] ?? '');
    $photoPath = false;

    if ($relativePath !== '' && strpos($relativePath, 'uploads/pets/') === 0 && $petUploadDirectory) {
        $photoPath = realpath(dirname(__DIR__) . '/' . $relativePath);
        if ($photoPath && strpos(strtolower($photoPath), strtolower($petUploadDirectory . DIRECTORY_SEPARATOR)) !== 0) {
            $photoPath = false;
        }
    }

    if ($photoPath && is_file($photoPath)) {
        $newHash = make_image_hash($photoPath);
        if ($newHash === null) {
            $failed++;
        } else {
            $imageReadable = true;
        }
    } else {
        $missing++;
    }

    $updates[] = [
        'pet_id' => $pet['pet_id'],
        'old_hash' => $pet['image_hash'],
        'new_hash' => $newHash,
        'image_readable' => $imageReadable,
    ];
}

$updated = 0;
$unchanged = 0;
$update = $apply ? $pdo->prepare('UPDATE pets SET image_hash = ? WHERE pet_id = ?') : null;

if ($apply) {
    try {
        $pdo->beginTransaction();
        foreach ($updates as $row) {
            if ($row['old_hash'] === $row['new_hash']) {
                if ($row['image_readable']) {
                    $unchanged++;
                }
                continue;
            }
            $update->execute([$row['new_hash'], $row['pet_id']]);
            $updated++;
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        fwrite(STDERR, "エラー: ハッシュの更新に失敗したため、変更を取り消しました。\n");
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }
} else {
    foreach ($updates as $row) {
        if ($row['old_hash'] === $row['new_hash']) {
            if ($row['image_readable']) {
                $unchanged++;
            }
        } else {
            $updated++;
        }
    }
}

echo $apply ? "画像ハッシュの再生成が完了しました。\n" : "ドライラン完了。データベースは変更していません。\n";
echo ($apply ? '更新' : '更新予定') . ": {$updated} 件\n";
echo "変更なし: {$unchanged} 件\n";
echo "写真なし・ファイル参照不可: {$missing} 件（照合用ハッシュを空にしました）\n";
echo "画像の読み込み失敗: {$failed} 件（照合用ハッシュを空にしました）\n";
if (!$apply) {
    echo "内容を確認し、バックアップ後に --apply を付けて実行すると更新します。\n";
}
