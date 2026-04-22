<?php
// ===================================================
//  CKEditor 5 用 画像アップロードエンドポイント
//  SimpleUploadAdapter が POST で "upload" フィールドに画像を送ってくる
//  成功時： {"url": "https://..."}
//  失敗時： {"error": {"message": "..."}}
// ===================================================
require_once '../config.php';
require_login();
csrf_verify_header(); // XHR リクエストの CSRF トークンをヘッダーで検証

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => ['message' => 'Method not allowed']]);
    exit;
}

$file = $_FILES['upload'] ?? null;

if (!$file) {
    echo json_encode(['error' => ['message' => 'アップロードに失敗しました']]);
    exit;
}

$result = validate_image_upload($file); // MIMEタイプで検証
if (isset($result['error'])) {
    echo json_encode(['error' => ['message' => $result['error']]]);
    exit;
}

$filename = uniqid() . '.' . $result['ext'];

if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
    echo json_encode(['error' => ['message' => '保存に失敗しました']]);
    exit;
}

echo json_encode(['url' => UPLOAD_URL . $filename]);
