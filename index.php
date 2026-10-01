<?php
require_once __DIR__ . '/functions.php';

$page_title = 'ホーム';
$hide_page_header = true;

$user = current_user();
$role = user_role();
$username = $user['username'] ?? '';

$pdo = get_pdo();
$stats = [
    'pets' => 0,
    'owners' => 0,
    'shops' => 0,
    'lost_reports' => 0,
    'health_records' => 0,
    'vaccinations' => 0,
    'consultation_threads' => 0,
];
$recent_reports = [];
$recent_pets = [];

try {
    $stats['pets'] = (int) $pdo->query('SELECT COUNT(*) FROM pets')->fetchColumn();
    $stats['owners'] = (int) $pdo->query('SELECT COUNT(*) FROM owners')->fetchColumn();
    $stats['shops'] = (int) $pdo->query('SELECT COUNT(*) FROM shops')->fetchColumn();
    $stats['lost_reports'] = (int) $pdo->query('SELECT COUNT(*) FROM lost_reports')->fetchColumn();
    $stats['health_records'] = (int) $pdo->query('SELECT COUNT(*) FROM health_records')->fetchColumn();
    $stats['vaccinations'] = (int) $pdo->query('SELECT COUNT(*) FROM vaccinations')->fetchColumn();
    $stats['consultation_threads'] = (int) $pdo->query('SELECT COUNT(*) FROM consultation_threads')->fetchColumn();

    $stmt = $pdo->query('
        SELECT lr.lost_id, lr.pet_id, lr.location, lr.status, lr.report_date, lr.photo_path,
               p.code,
               p.name AS pet_name, p.species, p.photo_path AS pet_photo_path
        FROM lost_reports lr
        LEFT JOIN pets p ON lr.pet_id = p.pet_id
        ORDER BY lr.created_at DESC
        LIMIT 3
    ');
    $recent_reports = $stmt->fetchAll();

    $stmt = $pdo->query('
        SELECT p.code, p.name, p.species, p.photo_path, p.created_at
        FROM pets p
        ORDER BY p.pet_id DESC
        LIMIT 3
    ');
    $recent_pets = $stmt->fetchAll();
} catch (Exception $e) {
    // DB未接続時もページ表示
}

$role_labels = [
    'admin' => '管理者',
    'clinic' => '動物病院',
    'shop' => 'ペットショップ',
    'owner' => '飼い主',
    'guest' => 'ゲスト',
];

$home_templates = [
    'admin' => 'admin.php',
    'clinic' => 'clinic.php',
    'shop' => 'shop.php',
    'owner' => 'owner.php',
];

$home_template = $home_templates[$role] ?? 'guest.php';

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/home/' . $home_template;
include __DIR__ . '/templates/footer.php';
