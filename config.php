<?php

$hostName = $_SERVER['HTTP_HOST'] ?? '';

$isLocal =
    $hostName === 'localhost' ||
    $hostName === '127.0.0.1' ||
    strpos($hostName, 'localhost:') === 0;

define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'admin');

if ($isLocal) {

    define('BASE_URL', '/pet-digital-id');

    define('DB_HOST', 'localhost');
    define('DB_NAME', 'pet_digital_id');
    define('DB_USER', 'root');
    define('DB_PASS', '');

} else {

    define('BASE_URL', '');

    define('DB_HOST', 'sql208.infinityfree.com');
    define('DB_NAME', 'if0_42937876_pet_digital_id');
    define('DB_USER', 'if0_42937876');
    define('DB_PASS', 'Iamrich1234');
}