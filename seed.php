<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// サンプルデータ投入スクリプト（改善版）
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

// config.phpで定義されていない場合のフォールバック（デフォルト値）
$admin_user = defined('ADMIN_USER') ? ADMIN_USER : 'admin';
$admin_pass = defined('ADMIN_PASS') ? ADMIN_PASS : 'admin123';

$pdo = get_pdo();

try {
    // トランザクション開始
    $pdo->beginTransaction();

    // 1. ショップ情報の登録
    $stmt = $pdo->prepare("INSERT INTO shops (name, address, phone) VALUES (?, ?, ?)");
    $stmt->execute(['Happy Pets', 'Tokyo', '03-0000-0000']);
    $shop_id = $pdo->lastInsertId();

    // 2. 飼い主情報の登録
    $stmt = $pdo->prepare("INSERT INTO owners (name, address, phone) VALUES (?, ?, ?)");
    $stmt->execute(['山田 太郎', '東京都', '090-0000-0000']);
    $owner_id = $pdo->lastInsertId();

    // 3. ペット情報の登録（ユニークコードの生成）
    $code = 'P' . time() . rand(1000, 9999);
    $stmt = $pdo->prepare('INSERT INTO pets (code, name, species, birthday, shop_id, owner_id, microchip) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([$code, 'ポチ', '犬', '2020-05-01', $shop_id, $owner_id, 'MC123456']);
    $pet_id = $pdo->lastInsertId();

    // 4. 健康記録の登録（安全なプレースホルダに変更）
    $stmt = $pdo->prepare("INSERT INTO health_records (pet_id, record_date, details, weight) VALUES (?, ?, ?, ?)");
    $stmt->execute([$pet_id, '2023-04-01', '初回健診、ワクチン済み', 5.4]);

    // 5. 管理者ユーザーを作成（存在しなければ）
    // ※ usersテーブルが存在しない場合でも、ここまでの処理をコミットできるように個別に try-catch を維持、または判定
    try {
        $userEmails = [
            'admin' => 'admin@example.com',
            'hospital_test' => 'hospital@example.com',
            'owner_test' => 'owner@example.com',
            'shop_test' => 'shop@example.com',
        ];

        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$admin_user]);
        
        if (!$stmt->fetch()) {
            $hash = password_hash($admin_pass, PASSWORD_DEFAULT);
            try {
                $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, notify_lost) VALUES (?, ?, ?, ?, 1)');
                $ins->execute([$admin_user, $userEmails['admin'], $hash, 'admin']);
            } catch (Exception $e) {
                $ins = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
                $ins->execute([$admin_user, $hash, 'admin']);
            }
        }

        // 動物病院用のテストユーザーを追加
        $clinicUsername = 'hospital_test';
        $clinicPassword = 'hospitalpass';
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$clinicUsername]);
        if (!$stmt->fetch()) {
            $hash = password_hash($clinicPassword, PASSWORD_DEFAULT);
            try {
                $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, notify_lost) VALUES (?, ?, ?, ?, 1)');
                $ins->execute([$clinicUsername, $userEmails['hospital_test'], $hash, 'clinic']);
            } catch (Exception $e) {
                $ins = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
                $ins->execute([$clinicUsername, $hash, 'clinic']);
            }
        }

        // 飼い主用テストユーザーを追加
        $ownerUsername = 'owner_test';
        $ownerPassword = 'ownerpass';
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$ownerUsername]);
        if (!$stmt->fetch()) {
            $hash = password_hash($ownerPassword, PASSWORD_DEFAULT);
            try {
                $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, notify_lost) VALUES (?, ?, ?, ?, 1)');
                $ins->execute([$ownerUsername, $userEmails['owner_test'], $hash, 'owner']);
            } catch (Exception $e) {
                $ins = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
                $ins->execute([$ownerUsername, $hash, 'owner']);
            }
        }

        // ペットショップ用テストユーザーを追加
        $shopUsername = 'shop_test';
        $shopPassword = 'shoppass';
        $stmt = $pdo->prepare('SELECT user_id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$shopUsername]);
        if (!$stmt->fetch()) {
            $hash = password_hash($shopPassword, PASSWORD_DEFAULT);
            try {
                $ins = $pdo->prepare('INSERT INTO users (username, email, password_hash, role, notify_lost) VALUES (?, ?, ?, ?, 1)');
                $ins->execute([$shopUsername, $userEmails['shop_test'], $hash, 'shop']);
            } catch (Exception $e) {
                $ins = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)');
                $ins->execute([$shopUsername, $hash, 'shop']);
            }
        }

        try {
            foreach ($userEmails as $uname => $email) {
                $upd = $pdo->prepare('UPDATE users SET email = ?, notify_lost = 1 WHERE username = ? AND (email IS NULL OR email = "")');
                $upd->execute([$email, $uname]);
            }
        } catch (Exception $e) {
            // email カラム未追加時はスキップ
        }
    } catch (Exception $e) {
        // users テーブルがない等のエラーは開発中を考慮して許容（ログ等に出す場合はここに記述）
    }

    // すべての変更を確定
    $pdo->commit();

    echo 'サンプルデータを正常に投入しました。<br>';
    echo '発行されたペットコード: <strong>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</strong><br>';
    echo '管理者ユーザー: ' . htmlspecialchars($admin_user, ENT_QUOTES, 'UTF-8') . '<br>';
    echo '病院ユーザー: hospital_test / hospitalpass<br>';
    echo '飼い主ユーザー: owner_test / ownerpass<br>';
    echo 'ペットショップユーザー: shop_test / shoppass<br>';

} catch (Exception $e) {
    // エラー時はロールバック
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo 'エラーが発生したため処理を中断しました: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}