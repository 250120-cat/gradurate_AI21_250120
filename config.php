<?php

$hostName = $_SERVER['HTTP_HOST'] ?? '';

$isLocal =
    $hostName === 'localhost' ||
    $hostName === '127.0.0.1' ||
    strpos($hostName, 'localhost:') === 0;

// Admin login
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin');
define('ADMIN_USER', 'hospital_test');
define('ADMIN_PASS', 'hospitalpass');
define('ADMIN_USER', 'shop_test');
define('ADMIN_PASS', 'shoppass');



if ($isLocal) {

    // XAMPP / LOCAL
    define('BASE_URL', '/pet-digital-id');

    define('DB_HOST', 'localhost');
    define('DB_NAME', 'pet_digital_id');
    define('DB_USER', 'root');
    define('DB_PASS', '');

} else {

    // INFINITYFREE / LIVE
    define('BASE_URL', '');

    define('DB_HOST', 'sql208.infinityfree.com');
    define('DB_NAME', 'if0_42937876_pet_digital_id');
    define('DB_USER', 'if0_42937876');

    require_once __DIR__ . '/config.local.php';
}