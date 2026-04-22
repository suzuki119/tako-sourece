<?php
// ===================================================
//  パスワード再設定 — メールアドレス入力
// ===================================================
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// password_resets テーブルを初回アクセス時に自動作成
db()->exec("CREATE TABLE IF NOT EXISTS password_resets (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    INTEGER NOT NULL,
    token      TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    created_at TEXT DEFAULT (datetime('now'))
)");

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        $error = 'メールアドレスを入力してください。';
    } else {
        $pdo  = db();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if ($user) {
            // ① 古いトークンを削除してから新しいトークンを発行
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = :id')
                ->execute([':id' => $user['id']]);

            $token      = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $pdo->prepare(
                'INSERT INTO password_resets (user_id, token, expires_at)
                 VALUES (:user_id, :token, :expires_at)'
            )->execute([
                ':user_id'    => $user['id'],
                ':token'      => $token,
                ':expires_at' => $expires_at,
            ]);

            // ② メール送信
            $reset_url = SITE_URL . '/cms/reset.php?token=' . $token;
            $subject   = '【' . SITE_NAME . '】パスワード再設定のご案内';
            $body      = implode("\n", [
                $user['username'] . ' 様',
                '',
                'ログイン情報をお送りします。',
                '',
                'ユーザー名: ' . $user['username'],
                '',
                'パスワードを再設定するには、以下のリンクをクリックしてください。',
                'リンクの有効期限は1時間です。',
                '',
                $reset_url,
                '',
                '※ このメールに心当たりがない場合は無視してください。',
                '※ パスワードは変更されません。',
                '',
                '-- ' . SITE_NAME,
            ]);

            $headers = implode("\r\n", [
                'From: noreply@' . parse_url(SITE_URL, PHP_URL_HOST),
                'Content-Type: text/plain; charset=UTF-8',
            ]);

            mail($user['email'], $subject, $body, $headers);
        }

        // メールが存在しない場合でも同じメッセージを表示（メールアドレスの存在確認を防ぐ）
        $sent = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>パスワードをお忘れの方 | <?= h(SITE_NAME) ?></title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; max-width: 400px; margin: 80px auto; padding: 0 20px; color: #222; }
        h1 { font-size: 1.2rem; margin-bottom: 8px; }
        .desc { font-size: .88rem; color: #666; margin-bottom: 24px; line-height: 1.6; }
        label { display: block; margin-top: 16px; font-size: .9rem; font-weight: 600; }
        input[type="email"] { width: 100%; padding: 10px; margin-top: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; }
        input:focus { outline: none; border-color: #222; }
        button { margin-top: 20px; width: 100%; padding: 11px; background: #222; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; }
        button:hover { background: #000; }
        .alert { margin-top: 20px; padding: 14px 16px; border-radius: 4px; font-size: .9rem; line-height: 1.6; }
        .alert-success { background: #eafaf1; border-left: 4px solid #27ae60; color: #1e8449; }
        .alert-error   { background: #fdecea; border-left: 4px solid #e74c3c; color: #c0392b; }
        .back { display: block; margin-top: 20px; font-size: .85rem; color: #888; text-align: center; text-decoration: none; }
        .back:hover { color: #333; }
    </style>
</head>
<body>
    <h1>パスワードをお忘れの方</h1>

    <?php if ($sent): ?>
        <div class="alert alert-success">
            登録済みのメールアドレスであれば、パスワード再設定メールを送信しました。<br>
            メールボックスをご確認ください。<br>
            <small style="color:#555;">※ 届かない場合は迷惑メールフォルダもご確認ください。</small>
        </div>
    <?php else: ?>
        <p class="desc">登録したメールアドレスを入力してください。ユーザー名とパスワード再設定リンクをお送りします。</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <label>メールアドレス
                <input type="email" name="email" required autocomplete="email">
            </label>
            <button type="submit">送信する</button>
        </form>
    <?php endif; ?>

    <a class="back" href="<?= h(SITE_URL) ?>/cms/login.php">← ログイン画面へ戻る</a>
</body>
</html>
