<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function e($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function redirect($url){ header('Location: ' . $url); exit; }

/** ログイン後リダイレクト先が同一アプリ内か検証する */
function safe_redirect_after_login($dest){
    if (!is_string($dest) || $dest === '') {
        return null;
    }
    if (strpos($dest, BASE_URL . '/') === 0 || $dest === BASE_URL) {
        return $dest;
    }
    return null;
}

// CSRF
function csrf_token(){
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf_token'];
}

function verify_csrf($token){
    return isset($_SESSION['_csrf_token']) && hash_equals($_SESSION['_csrf_token'], $token);
}

// 認証（プロトタイプ）：管理者は config.php の定義を使う
function require_login(){
    if (empty($_SESSION['user'])){
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect(BASE_URL . '/login.php');
    }

    if (!empty($_SESSION['user']['password_change_required'])) {
        $current_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (basename($current_path ?: '') !== 'change_password.php') {
            redirect(BASE_URL . '/change_password.php');
        }
    }
}

function current_user(){
    return $_SESSION['user'] ?? null;
}

function attempt_login($username, $password){
    // まずDBにユーザーがいれば照合する
    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            'SELECT
                user_id,
                username,
                password_hash,
                role,
                owner_id,
                shop_id,
                clinic_id,
                password_change_required
            FROM users
            WHERE username = ?
            LIMIT 1'
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if ($row && password_verify($password, $row['password_hash'])){
            $_SESSION['user'] = [
                'id'        => $row['user_id'],
                'username'  => $row['username'],
                'role'      => $row['role'],
                'owner_id'  => $row['owner_id'],
                'shop_id'   => $row['shop_id'],
                'clinic_id' => $row['clinic_id'],
                'password_change_required' => (bool)$row['password_change_required'],
            ];
            return true;
        }
    } catch (Exception $e) {
        // DB接続やクエリで失敗した場合はフォールバックでconfigの管理者を許可
    }

    // フォールバック: プロトタイプ時のみ config 定義を確認
    if (defined('ADMIN_USER') && $username === ADMIN_USER && $password === ADMIN_PASS){
        $_SESSION['user'] = ['username'=>ADMIN_USER,'role'=>'admin'];
        return true;
    }

    return false;
}

function logout(){
    unset($_SESSION['user']);
}

function send_email_notification($to, $subject, $body, $isHtml = false){
    // If PHPMailer is available (installed via Composer), use SMTP
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
    }

    if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            // SMTP settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            // Use authentication only if credentials provided
            $mail->SMTPAuth = !empty(SMTP_USER);
            if (!empty(SMTP_USER)) $mail->Username = SMTP_USER;
            if (!empty(SMTP_PASS)) $mail->Password = SMTP_PASS;
            // If SMTPSecure is empty, disable automatic STARTTLS
            if (!empty(SMTP_SECURE)) {
                $mail->SMTPSecure = SMTP_SECURE;
                $mail->SMTPAutoTLS = true;
            } else {
                $mail->SMTPAutoTLS = false;
            }
            $mail->Port = (int)SMTP_PORT;
            $from = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com';
            $mail->setFrom($from);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            if ($isHtml) {
                $mail->isHTML(true);
                $mail->Body = $body;
                $mail->AltBody = strip_tags($body);
            } else {
                $mail->isHTML(false);
                $mail->Body = $body;
            }
            return $mail->send();
        } catch (Exception $e) {
            return false;
        }
    }

    // Fallback to PHP mail()
    $headers = [];
    $from = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com';
    $headers[] = 'From: ' . $from;
    if ($isHtml) {
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    }
    $headers[] = 'MIME-Version: 1.0';
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

function get_app_url($path = ''){
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = ltrim($path, '/');
    return $scheme . '://' . $host . BASE_URL . '/' . $path;
}

// Email template helpers
function load_email_template($key){
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT subject, body_html, body_text FROM email_templates WHERE template_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $res = $stmt->fetch();
    
    // 👇 データが何も取得できなかった場合は、空文字の配列を返してエラーを防ぐ
    if (!$res) {
        return [
            'subject' => '',
            'body_html' => '',
            'body_text' => ''
        ];
    }
    
    return $res;
}

function save_email_template($key, $subject, $body_html, $body_text){
    $pdo = get_pdo();
    $stmt = $pdo->prepare('INSERT INTO email_templates (template_key, subject, body_html, body_text) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE subject=VALUES(subject), body_html=VALUES(body_html), body_text=VALUES(body_text), updated_at=CURRENT_TIMESTAMP');
    return $stmt->execute([$key, $subject, $body_html, $body_text]);
}

function render_email_template($template, $vars = [], $isHtml = false){
    if ($template === null) return null;
    $out = $template;
    foreach ($vars as $k => $v){
        $placeholder = '{' . $k . '}';
        if ($isHtml) {
            $safe = htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
        } else {
            $safe = $v;
        }
        $out = str_replace($placeholder, $safe, $out);
    }
    return $out;
}

function get_registered_user_emails(){
    try {
        $pdo = get_pdo();
        $stmt = $pdo->query(
            "SELECT email FROM users
             WHERE email IS NOT NULL AND email != ''
             AND (notify_lost IS NULL OR notify_lost = 1)"
        );
        return array_values(array_unique(array_filter($stmt->fetchAll(PDO::FETCH_COLUMN))));
    } catch (Exception $e) {
        return [];
    }
}

function build_lost_report_email($data){
    $tpl = load_email_template('lost_found');

    $pet_name = $data['pet_name'] ?? '不明';
    $code = $data['code'] ?? '不明';
    $reporter_name = $data['reporter_name'] ?? '不明';
    $reporter_phone = $data['reporter_phone'] ?? '不明';
    $location = $data['location'] ?? '未記入';
    $notes = $data['notes'] ?? '';
    $report_date = $data['report_date'] ?? date('Y-m-d H:i:s');
    $pet_link = $data['pet_link'] ?? get_app_url('lost_board.php');

    if (!empty($data['photo_path'])) {
        $notes .= "\n発見写真: " . get_app_url($data['photo_path']);
    }

    if (!empty($data['matches'])) {
        $notes .= "\n\n似ている登録済みペット候補:\n";
        foreach (array_slice($data['matches'], 0, 5) as $match) {
            $notes .= '- ' . ($match['name'] ?? '名前未登録')
                    . ' / コード: ' . ($match['code'] ?? '')
                    . ' / ' . get_app_url('pet/view.php?code=' . urlencode($match['code'] ?? ''))
                    . "\n";
        }
    }

    $vars = [
        'pet_name' => $pet_name,
        'code' => $code,
        'reporter_name' => $reporter_name,
        'reporter_phone' => $reporter_phone,
        'owner_name' => $data['owner_name'] ?? '',
        'location' => $location,
        'notes' => $notes,
        'report_date' => $report_date,
        'pet_link' => $pet_link,
    ];

    $default_subject = '【Pet Digital ID】迷子・発見報告が届きました';
    $default_body = "迷子・発見報告が届きました。\n\n"
        . "ペット名: {pet_name}\n"
        . "コード: {code}\n"
        . "発見者: {reporter_name}\n"
        . "連絡先: {reporter_phone}\n"
        . "発見場所: {location}\n"
        . "報告日時: {report_date}\n\n"
        . "詳細: {pet_link}\n\n"
        . "メモ:\n{notes}\n";

    $subject_tpl = !empty($tpl['subject']) ? $tpl['subject'] : $default_subject;
    $body_tpl = !empty($tpl['body_text']) ? $tpl['body_text'] : $default_body;

    return [
        'subject' => render_email_template($subject_tpl, $vars),
        'body' => render_email_template($body_tpl, $vars),
    ];
}

function notify_lost_report($data){
    $pdo = get_pdo();
    $recipients = $pdo->query(
        "SELECT user_id FROM users
         WHERE role = 'admin' OR notify_lost = 1"
    )->fetchAll(PDO::FETCH_COLUMN);

    $pet_name = $data['pet_name'] ?? '不明';
    $code = $data['code'] ?? '不明';
    $reporter_name = $data['reporter_name'] ?? '不明';
    $reporter_phone = $data['reporter_phone'] ?? '不明';
    $location = $data['location'] ?? '未記入';
    $notes = trim($data['notes'] ?? '');
    $body = "ペット名: {$pet_name}\n"
        . "個体コード: {$code}\n"
        . "発見者: {$reporter_name}\n"
        . "連絡先: {$reporter_phone}\n"
        . "発見場所: {$location}\n";

    if ($notes !== '') {
        $body .= "\nメモ:\n{$notes}\n";
    }

    $insert = $pdo->prepare(
        'INSERT INTO app_notifications (user_id, title, body, link_path) VALUES (?, ?, ?, ?)'
    );
    foreach ($recipients as $user_id) {
        $insert->execute([
            (int)$user_id,
            '迷子・発見報告が届きました',
            $body,
            'lost_board.php',
        ]);
    }

    return [
        'sent' => count($recipients),
        'failed' => 0,
        'total' => count($recipients),
    ];
}

function save_uploaded_image($file, $folder)
{
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return null;
    }

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    $mime = mime_content_type($file['tmp_name']);

    if (!isset($allowed[$mime])) {
        return null;
    }

    $upload_dir = __DIR__ . '/uploads/' . $folder;

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $path = $upload_dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        return null;
    }

    return 'uploads/' . $folder . '/' . $filename;
}

function delete_uploaded_file($relative_path, $folder)
{
    $prefix = 'uploads/' . $folder . '/';
    if (!is_string($relative_path) || strpos($relative_path, $prefix) !== 0) {
        return false;
    }

    $project_root = realpath(__DIR__);
    $upload_root = $project_root
        ? realpath($project_root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $folder)
        : false;
    $file_path = $project_root
        ? realpath($project_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative_path))
        : false;

    if (!$file_path) {
        return true;
    }
    if (!$upload_root || strpos($file_path, $upload_root . DIRECTORY_SEPARATOR) !== 0 || !is_file($file_path)) {
        return false;
    }

    return unlink($file_path);
}

function save_uploaded_pdf($file, $folder)
{
    if (empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    if (($file['size'] ?? 0) > 10 * 1024 * 1024) {
        return null;
    }

    $allowed = [
        'application/pdf' => 'pdf'
    ];

    $mime = mime_content_type($file['tmp_name']);

    if (!isset($allowed[$mime])) {
        return null;
    }

    $upload_dir = __DIR__ . '/uploads/' . $folder;

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $filename = date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $path = $upload_dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        return null;
    }

    return 'uploads/' . $folder . '/' . $filename;
}

function image_hash_from_grayscale(array $pixels)
{
    if (count($pixels) !== 8) {
        return null;
    }

    $hash = '';
    foreach ($pixels as $row) {
        if (!is_array($row) || count($row) !== 9) {
            return null;
        }

        for ($x = 0; $x < 8; $x++) {
            if (!is_numeric($row[$x]) || !is_numeric($row[$x + 1])) {
                return null;
            }
            $hash .= (float)$row[$x] > (float)$row[$x + 1] ? '1' : '0';
        }
    }

    return $hash;
}

function make_image_hash($path)
{
    if (!function_exists('imagecreatefromstring') || !is_file($path)) {
        return null;
    }

    $data = @file_get_contents($path);
    if ($data === false) {
        return null;
    }

    $image = @imagecreatefromstring($data);
    if (!$image) {
        return null;
    }

    $small = imagecreatetruecolor(9, 8);
    if (!$small) {
        imagedestroy($image);
        return null;
    }

    $resampled = imagecopyresampled(
        $small,
        $image,
        0,
        0,
        0,
        0,
        9,
        8,
        imagesx($image),
        imagesy($image)
    );
    imagedestroy($image);
    if (!$resampled) {
        imagedestroy($small);
        return null;
    }

    $pixels = [];
    for ($y = 0; $y < 8; $y++) {
        $row = [];
        for ($x = 0; $x < 9; $x++) {
            $rgb = imagecolorat($small, $x, $y);
            $red = ($rgb >> 16) & 255;
            $green = ($rgb >> 8) & 255;
            $blue = $rgb & 255;
            $row[] = (299 * $red + 587 * $green + 114 * $blue) / 1000;
        }
        $pixels[] = $row;
    }
    imagedestroy($small);

    return image_hash_from_grayscale($pixels);
}

function image_hash_distance($hash1, $hash2)
{
    if (!is_string($hash1) || !is_string($hash2)
        || strlen($hash1) !== 64 || strlen($hash2) !== 64
        || !preg_match('/^[01]{64}$/', $hash1)
        || !preg_match('/^[01]{64}$/', $hash2)) {
        return 999;
    }

    $distance = 0;
    for ($i = 0; $i < 64; $i++) {
        if ($hash1[$i] !== $hash2[$i]) {
            $distance++;
        }
    }
    return $distance;
}

function find_similar_pets(PDO $pdo, $query_hash, $max_distance = 18, $limit = 5)
{
    if (!is_string($query_hash) || !preg_match('/^[01]{64}$/', $query_hash)) {
        return [];
    }

    $stmt = $pdo->query(
        'SELECT pet_id, code, name, species, photo_path, image_hash
         FROM pets
         WHERE image_hash IS NOT NULL AND image_hash != ""'
    );

    $matches = [];
    foreach ($stmt->fetchAll() as $pet) {
        $distance = image_hash_distance($query_hash, $pet['image_hash']);
        if ($distance <= $max_distance) {
            $pet['distance'] = $distance;
            $pet['similarity'] = (int)round((64 - $distance) * 100 / 64);
            $matches[] = $pet;
        }
    }

    usort($matches, static function ($left, $right) {
        return $left['distance'] <=> $right['distance'];
    });

    return array_slice($matches, 0, max(0, $limit));
}

function public_lost_report_notes($notes)
{
    if (!is_string($notes) || $notes === '') {
        return '';
    }

    $public_lines = [];
    foreach (preg_split('/\R/u', $notes) as $line) {
        if (preg_match('/^\s*(?:【(?:発見者|連絡先)】|(?:発見者|連絡先)\s*[:：])/u', $line)) {
            continue;
        }
        $public_lines[] = $line;
    }

    return trim(implode("\n", $public_lines));
}

function add_audit_log($action, $target_type = null, $target_id = null, $details = null)
{
    try {
        $pdo = get_pdo();
        $user = current_user();
        $user_name = $user['username'] ?? 'guest';

        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_name, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$user_name, $action, $target_type, $target_id, $details]);
    } catch (Exception $e) {
        // 履歴保存に失敗しても本体処理は止めない
    }
}
function user_role()
{
    $user = current_user();
    return $user['role'] ?? 'guest';
}

function has_role($roles)
{
    $role = user_role();

    if (!is_array($roles)) {
        $roles = [$roles];
    }

    return in_array($role, $roles, true);
}

function require_role($roles)
{
    if (!has_role($roles)) {
        http_response_code(403);
        echo 'このページを表示する権限がありません。';
        exit;
    }
}

// Lightweight server-side translation using LibreTranslate public instance.
function translate_text($text, $target = 'ja'){
    $text = trim($text);
    if ($text === '') return '';
    $url = 'https://libretranslate.de/translate';
    $data = http_build_query([
        'q' => $text,
        'source' => 'auto',
        'target' => $target,
        'format' => 'text'
    ]);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/x-www-form-urlencoded\r\nUser-Agent: Mozilla/5.0\r\n",
            'content' => $data,
            'timeout' => 10
        ]
    ];
    $ctx = stream_context_create($opts);
    $res = @file_get_contents($url, false, $ctx);
    if ($res) {
        $j = json_decode($res, true);
        if (isset($j['translatedText'])) return $j['translatedText'];
    }
    // Fallback to cURL if available
    if (function_exists('curl_version')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/x-www-form-urlencoded", 'User-Agent: Mozilla/5.0']);
        $cres = curl_exec($ch);
        curl_close($ch);
        if ($cres) {
            $j = json_decode($cres, true);
            if (isset($j['translatedText'])) return $j['translatedText'];
        }
    }
    // Fallback: unofficial Google Translate API endpoint
    $gurl = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=auto&tl=' . rawurlencode($target) . '&dt=t&q=' . rawurlencode($text);
    $gres = @file_get_contents($gurl);
    if ($gres) {
        $arr = json_decode($gres, true);
        if (is_array($arr) && isset($arr[0][0][0])) {
            return $arr[0][0][0];
        }
    }
    return '';
}
