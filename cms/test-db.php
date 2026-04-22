<?php
// DB接続テスト用ファイル
// 確認できたら削除すること！
require_once 'config.php';

$pdo = db();

// usersテーブルが取れるか確認
$stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
$row = $stmt->fetch();

echo 'DB接続成功！<br>';
echo 'usersテーブルのレコード数: ' . $row['count'];
