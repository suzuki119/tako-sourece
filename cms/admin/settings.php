<?php
// ===================================================
//  サイト設定
// ===================================================
require_once '../config.php';
require_login();

$configFile = dirname(__DIR__) . '/config.php';
$sampleFile = dirname(__DIR__) . '/config.sample.php';

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $site_url  = rtrim(trim($_POST['site_url']  ?? ''), '/');
    $site_name = trim($_POST['site_name'] ?? '');
    $site_desc = trim($_POST['site_desc'] ?? '');

    if ($site_url === '') {
        $error = 'サイト URL は必須です。';
    } elseif ($site_name === '') {
        $error = 'サイト名は必須です。';
    } elseif (!file_exists($sampleFile)) {
        $error = 'config.sample.php が見つかりません。';
    } elseif (!is_writable($configFile)) {
        $error = 'config.php に書き込み権限がありません。';
    } else {
        $escape = fn(string $v): string => str_replace("'", "\\'", $v);

        $template = file_get_contents($sampleFile);
        $content  = str_replace(
            ['{{SITE_URL}}', '{{SITE_NAME}}', '{{SITE_DESCRIPTION}}'],
            [$escape($site_url), $escape($site_name), $escape($site_desc)],
            $template
        );

        if (file_put_contents($configFile, $content) === false) {
            $error = 'config.php の書き込みに失敗しました。';
        } else {
            // PRG パターン：保存後にリダイレクトして新しい定数値を反映させる
            header('Location: ' . $site_url . '/cms/admin/settings.php?saved=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>サイト設定 | 管理画面</title>
    <style>
        body { font-family: sans-serif; max-width: 640px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 1.4rem; margin-bottom: 32px; }
        label { display: block; margin-top: 20px; font-size: .9rem; font-weight: bold; }
        label .note { font-weight: normal; color: #999; font-size: .8rem; }
        input[type="text"], input[type="url"] {
            width: 100%; padding: 9px 10px; margin-top: 6px;
            border: 1px solid #ccc; border-radius: 4px; font-size: .95rem;
            box-sizing: border-box;
        }
        input:focus { outline: none; border-color: #222; }
        .hint { font-size: .78rem; color: #888; margin-top: 4px; }
        .actions { margin-top: 32px; display: flex; gap: 12px; align-items: center; }
        button { padding: 10px 28px; background: #222; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-size: .95rem; }
        button:hover { background: #000; }
        .alert { padding: 12px 16px; border-radius: 4px; font-size: .88rem; margin-bottom: 24px; }
        .alert-error   { background: #fdecea; border-left: 4px solid #e74c3c; color: #c0392b; }
        .alert-success { background: #eafaf1; border-left: 4px solid #27ae60; color: #1e8449; }
        a.back { font-size: .9rem; color: #666; text-decoration: none; }
        a.back:hover { color: #222; }
        .current { font-size: .78rem; color: #aaa; margin-top: 4px; }
    </style>
</head>
<body>

    <h1>サイト設定</h1>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success">設定を保存しました。</div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>

        <label>サイト URL
            <input type="url" name="site_url" required
                   value="<?= h($_POST['site_url'] ?? SITE_URL) ?>">
            <p class="hint">末尾のスラッシュは不要。本番なら <code>https://example.com</code></p>
            <p class="hint">変更後は <strong>この URL でアクセスしないと管理画面に戻れません</strong>。</p>
        </label>

        <label>サイト名
            <input type="text" name="site_name" required
                   value="<?= h($_POST['site_name'] ?? SITE_NAME) ?>">
        </label>

        <label>サイト概要 <span class="note">（任意・OGP に使用）</span>
            <input type="text" name="site_desc"
                   value="<?= h($_POST['site_desc'] ?? SITE_DESCRIPTION) ?>">
        </label>

        <div class="actions">
            <button type="submit">保存する</button>
            <a class="back" href="<?= h(SITE_URL) ?>/cms/admin/index.php">← 一覧へ戻る</a>
        </div>
    </form>

</body>
</html>
