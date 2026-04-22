<?php
// ===================================================
//  パスワード再設定 — 新パスワード入力
// ===================================================
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo   = db();
$token = trim($_GET['token'] ?? '');
$error = '';
$done  = false;

// トークンが空なら forgot.php へ
if ($token === '') {
    header('Location: ' . SITE_URL . '/cms/forgot.php');
    exit;
}

// トークンを検索（有効期限内のものだけ）
$stmt = $pdo->prepare("
    SELECT pr.*, u.username
    FROM password_resets pr
    JOIN users u ON u.id = pr.user_id
    WHERE pr.token = :token
      AND pr.expires_at > datetime('now')
    LIMIT 1
");
$stmt->execute([':token' => $token]);
$reset = $stmt->fetch();

// トークンが無効または期限切れ
if (!$reset) {
    $expired = true;
} else {
    $expired = false;
}

// =====================================================
//  POST 処理 — 新パスワード保存
// =====================================================
if (!$expired && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $password = $_POST['password']         ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    if ($password === '') {
        $error = 'パスワードを入力してください。';
    } elseif (strlen($password) < 8) {
        $error = 'パスワードは8文字以上にしてください。';
    } elseif ($password !== $confirm) {
        $error = 'パスワードが一致しません。';
    } else {
        // ① パスワードを更新
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE users SET password = :password WHERE id = :id')
            ->execute([':password' => $hashed, ':id' => $reset['user_id']]);

        // ② 使用済みトークンを削除
        $pdo->prepare('DELETE FROM password_resets WHERE token = :token')
            ->execute([':token' => $token]);

        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>パスワード再設定 | <?= h(SITE_NAME) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 400px; margin: 80px auto; padding: 0 20px; color: #222; }
        h1 { font-size: 1.2rem; margin-bottom: 8px; }
        .desc { font-size: .88rem; color: #666; margin-bottom: 24px; }
        label { display: block; margin-top: 16px; font-size: .9rem; font-weight: 600; }
        input[type="password"] { width: 100%; padding: 10px; margin-top: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
        input:focus { outline: none; border-color: #222; }
        button { margin-top: 20px; width: 100%; padding: 11px; background: #222; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #000; }
        .alert { margin-top: 20px; padding: 14px 16px; border-radius: 4px; font-size: .9rem; line-height: 1.6; }
        .alert-success { background: #eafaf1; border-left: 4px solid #27ae60; color: #1e8449; }
        .alert-error   { background: #fdecea; border-left: 4px solid #e74c3c; color: #c0392b; }
        .alert-warn    { background: #fff8e1; border-left: 4px solid #f39c12; color: #7d5a00; }
        .btn-link { display: block; margin-top: 20px; width: 100%; padding: 11px; background: #222; color: #fff; border: none; border-radius: 4px; text-align: center; text-decoration: none; font-size: 1rem; }
        .btn-link:hover { background: #000; }
        .back { display: block; margin-top: 16px; font-size: .85rem; color: #888; text-align: center; text-decoration: none; }
        .back:hover { color: #333; }
    </style>
</head>
<body>
    <h1>パスワード再設定</h1>

    <?php if ($done): ?>
        <!-- 再設定完了 -->
        <div class="alert alert-success">
            パスワードを変更しました。新しいパスワードでログインしてください。
        </div>
        <a class="btn-link" href="<?= h(SITE_URL) ?>/cms/login.php">ログイン画面へ</a>

    <?php elseif ($expired): ?>
        <!-- トークン無効 / 期限切れ -->
        <div class="alert alert-warn">
            このリンクは無効または有効期限（1時間）が切れています。<br>
            再度メールアドレスを入力して、新しいリンクを発行してください。
        </div>
        <a class="btn-link" href="<?= h(SITE_URL) ?>/cms/forgot.php">再設定メールを再送する</a>

    <?php else: ?>
        <!-- 新パスワード入力フォーム -->
        <p class="desc"><?= h($reset['username']) ?> さんの新しいパスワードを設定してください。</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <label>新しいパスワード <span style="font-weight:400;color:#999;font-size:.8rem;">（8文字以上）</span>
                <input type="password" name="password" required autocomplete="new-password">
            </label>
            <label>新しいパスワード（確認）
                <input type="password" name="password_confirm" required autocomplete="new-password">
            </label>
            <button type="submit">パスワードを変更する</button>
        </form>

        <a class="back" href="<?= h(SITE_URL) ?>/cms/login.php">← ログイン画面へ戻る</a>
    <?php endif; ?>
</body>
</html>
