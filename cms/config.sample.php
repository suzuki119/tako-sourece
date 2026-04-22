<?php
// ===================================================
//  データベース接続設定テンプレート
//  setup.php が自動で config.php を生成するため
//  このファイルは直接編集しないでください。
// ===================================================

define('DB_HOST',    '{{DB_HOST}}');
define('DB_NAME',    '{{DB_NAME}}');
define('DB_USER',    '{{DB_USER}}');
define('DB_PASS',    '{{DB_PASS}}');
define('DB_CHARSET', 'utf8mb4');

define('SITE_URL',         '{{SITE_URL}}');
define('SITE_NAME',        '{{SITE_NAME}}');
define('SITE_DESCRIPTION', '{{SITE_DESCRIPTION}}');
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('UPLOAD_URL', '{{SITE_URL}}/cms/uploads/');

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn     = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            exit('DB接続エラー: ' . $e->getMessage());
        }
    }

    return $pdo;
}

function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function require_login(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . SITE_URL . '/cms/login.php');
        exit;
    }
}

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('不正なリクエストです。');
    }
}

function csrf_verify_header(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['error' => ['message' => '不正なリクエストです。']]));
    }
}

function validate_image_upload(array $file, bool $allow_svg = false): array
{
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'アップロードエラーが発生しました。'];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['error' => '画像サイズは2MB以下にしてください。'];
    }
    $finfo   = new finfo(FILEINFO_MIME_TYPE);
    $mime    = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/gif'     => 'gif',
        'image/webp'    => 'webp',
    ];
    if ($allow_svg) {
        $allowed['image/svg+xml'] = 'svg';
    }
    if (!isset($allowed[$mime])) {
        $types = implode(' / ', array_values($allowed));
        return ['error' => "許可されていないファイル形式です。（{$types}）"];
    }
    return ['ext' => $allowed[$mime]];
}
