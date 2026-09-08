<?php
require_once __DIR__ . '/../db.php';
$pdo = get_pdo();
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS news_keywords (
        id INT AUTO_INCREMENT PRIMARY KEY,
        keyword VARCHAR(255) NOT NULL,
        type ENUM('allow','block') NOT NULL DEFAULT 'allow',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS news_settings (
        `key` VARCHAR(100) PRIMARY KEY,
        `value` TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // set default threshold if not set
    $stmt = $pdo->prepare("SELECT `value` FROM news_settings WHERE `key` = 'animal_score_threshold' LIMIT 1");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $pdo->prepare("INSERT INTO news_settings (`key`,`value`) VALUES ('animal_score_threshold','1')")->execute();
    }

    echo "news_keywords and news_settings ensured\n";
} catch (Exception $e) {
    echo 'ERROR: ' . $e->getMessage() . "\n";
}
