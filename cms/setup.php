<?php
// ===================================================
//  インストールウィザード（SQLite 版）
// ===================================================
session_start();

$configFile = __DIR__ . '/config.php';
$dbFile     = __DIR__ . '/database/tako.db';

// --- インストール済み判定 ---
if (file_exists($configFile) && file_exists($dbFile)) {
    try {
        require_once $configFile;
        $count = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            header('Location: admin/index.php');
            exit;
        }
    } catch (Throwable $e) {
        // DB が壊れている場合はそのまま setup を表示
    }
}

$step  = $_GET['step'] ?? '1';
$error = '';

// =====================================================
//  ヘルパー関数
// =====================================================

function create_tables(PDO $pdo): void
{
    // SQLite 用 DDL（AUTO_INCREMENT → INTEGER PRIMARY KEY AUTOINCREMENT）
    $statements = [
        "CREATE TABLE IF NOT EXISTS users (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            username   TEXT NOT NULL UNIQUE,
            password   TEXT NOT NULL,
            email      TEXT,
            created_at TEXT DEFAULT (datetime('now'))
        )",

        "CREATE TABLE IF NOT EXISTS categories (
            id   INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL
        )",

        "CREATE TABLE IF NOT EXISTS posts (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            title        TEXT NOT NULL,
            thumbnail    TEXT,
            status       TEXT NOT NULL DEFAULT 'draft'
                         CHECK(status IN ('draft','published')),
            author_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
            period       TEXT,
            "type"       TEXT,
            external_url TEXT,
            tags         TEXT,
            sort_order   INTEGER DEFAULT 0,
            created_at   TEXT DEFAULT (datetime('now')),
            updated_at   TEXT DEFAULT (datetime('now'))
        )",

        "CREATE TABLE IF NOT EXISTS post_categories (
            post_id     INTEGER NOT NULL REFERENCES posts(id)      ON DELETE CASCADE,
            category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
            PRIMARY KEY (post_id, category_id)
        )",

        "CREATE TABLE IF NOT EXISTS post_sections (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            post_id    INTEGER NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
            title      TEXT,
            body       TEXT,
            image_url  TEXT,
            sort_order INTEGER DEFAULT 0
        )",

        "CREATE TABLE IF NOT EXISTS skill (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            title     TEXT NOT NULL,
            period    TEXT,
            body      TEXT,
            image_url TEXT,
            category  TEXT
        )",
    ];

    $pdo->exec('PRAGMA foreign_keys = ON');
    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }
}

function make_config(string $siteUrl, string $siteName, string $siteDesc): string
{
    $escape   = fn(string $v): string => str_replace("'", "\\'", $v);
    $siteUrl  = rtrim($escape($siteUrl), '/');
    $siteName = $escape($siteName);
    $siteDesc = $escape($siteDesc);

    $template = file_get_contents(__DIR__ . '/config.sample.php');
    return str_replace(
        ['{{SITE_URL}}', '{{SITE_NAME}}', '{{SITE_DESCRIPTION}}'],
        [$siteUrl, $siteName, $siteDesc],
        $template
    );
}

// =====================================================
//  Step 2 POST 処理
//  サイト設定保存 + DB 作成 + 管理者登録 を一括で行う
// =====================================================
if ($step === '2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $site_url  = rtrim(trim($_POST['site_url']  ?? ''), '/');
    $site_name = trim($_POST['site_name'] ?? '');
    $site_desc = trim($_POST['site_desc'] ?? '');
    $username  = trim($_POST['username']  ?? '');
    $password  = $_POST['password']       ?? '';
    $email     = trim($_POST['email']     ?? '');

    if ($site_url === '' || $site_name === '' || $username === '' || $password === '') {
        $error = 'サイトURL・サイト名・ユーザー名・パスワードは必須です。';
    } elseif (strlen($password) < 8) {
        $error = 'パスワードは8文字以上にしてください。';
    } else {
        try {
            // ① config.php を生成
            $content = make_config($site_url, $site_name, $site_desc);
            if (file_put_contents(__DIR__ . '/config.php', $content) === false) {
                throw new RuntimeException('config.php の書き込みに失敗しました。フォルダの書き込み権限を確認してください。');
            }

            // ② SQLite DB とテーブルを作成
            $pdo = new PDO('sqlite:' . $dbFile, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            create_tables($pdo);

            // ③ 管理者ユーザーを登録
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt   = $pdo->prepare(
                'INSERT INTO users (username, password, email) VALUES (:u, :p, :e)'
            );
            $stmt->execute([':u' => $username, ':p' => $hashed, ':e' => $email]);

            header('Location: setup.php?step=done');
            exit;

        } catch (Throwable $e) {
            // 失敗した場合は生成した config.php を削除して巻き戻す
            if (file_exists(__DIR__ . '/config.php')) {
                unlink(__DIR__ . '/config.php');
            }
            if (file_exists($dbFile)) {
                unlink($dbFile);
            }
            $error = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}

// =====================================================
//  Step 1: 要件チェックデータ
// =====================================================
$reqs       = [];
$req_all_ok = true;

if ($step === '1') {
    $reqs = [
        [
            'label'  => 'PHP 8.0 以上',
            'pass'   => version_compare(PHP_VERSION, '8.0.0', '>='),
            'detail' => 'PHP ' . PHP_VERSION,
        ],
        [
            'label'  => 'PDO 拡張',
            'pass'   => extension_loaded('pdo'),
            'detail' => '',
        ],
        [
            'label'  => 'PDO SQLite 拡張',
            'pass'   => extension_loaded('pdo_sqlite'),
            'detail' => '',
        ],
        [
            'label'  => 'uploads/ ディレクトリが書き込み可能',
            'pass'   => is_writable(__DIR__ . '/uploads/'),
            'detail' => '',
        ],
        [
            'label'  => 'database/ ディレクトリが書き込み可能',
            'pass'   => is_writable(__DIR__ . '/database/'),
            'detail' => '',
        ],
        [
            'label'  => 'インストール先が書き込み可能（config.php 生成）',
            'pass'   => is_writable(__DIR__),
            'detail' => '',
        ],
    ];
    $req_all_ok = empty(array_filter($reqs, fn($r) => !$r['pass']));
}

function esc(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$stepLabels     = ['1' => '動作確認', '2' => 'サイト設定 &amp; 管理者登録'];
$currentStepNum = match($step) { '1' => 1, '2' => 2, default => 1 };
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>インストール | Tako CMS</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f5f5f5; margin: 0; padding: 40px 20px; color: #222;
        }
        .container { max-width: 560px; margin: 0 auto; }
        .logo { font-size: 1.6rem; font-weight: 700; text-align: center; margin-bottom: 8px; }
        .logo span { color: #e74c3c; }
        .subtitle { text-align: center; color: #666; font-size: .9rem; margin-bottom: 32px; }

        .steps { display: flex; align-items: center; margin-bottom: 32px; }
        .step-item { display: flex; flex-direction: column; align-items: center; flex: 1; }
        .step-circle {
            width: 32px; height: 32px; border-radius: 50%;
            background: #ddd; color: #999; font-size: .8rem; font-weight: 700;
            display: flex; align-items: center; justify-content: center;
        }
        .step-circle.active { background: #222; color: #fff; }
        .step-circle.done   { background: #27ae60; color: #fff; }
        .step-label { font-size: .72rem; color: #999; margin-top: 6px; text-align: center; }
        .step-label.active { color: #222; font-weight: 600; }
        .step-line { flex: 1; height: 2px; background: #ddd; margin: 0 4px 20px; }

        .card { background: #fff; border-radius: 8px; padding: 32px; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
        .card h2 { font-size: 1.1rem; margin: 0 0 24px; }

        .section-label {
            font-size: .8rem; font-weight: 700; color: #888;
            text-transform: uppercase; letter-spacing: .5px;
            margin: 24px 0 16px; padding-bottom: 8px;
            border-bottom: 1px solid #eee;
        }
        .section-label:first-child { margin-top: 0; }

        label { display: block; font-size: .85rem; font-weight: 600; margin-bottom: 4px; }
        label .optional { font-weight: 400; color: #999; font-size: .8rem; }
        input[type="text"], input[type="password"], input[type="email"], input[type="url"] {
            width: 100%; padding: 10px 12px; border: 1px solid #ccc;
            border-radius: 4px; font-size: .95rem; outline: none;
        }
        input:focus { border-color: #222; }
        .field { margin-bottom: 16px; }
        .hint { font-size: .78rem; color: #888; margin-top: 4px; }

        .btn {
            display: block; width: 100%; padding: 12px;
            background: #222; color: #fff; border: none; border-radius: 4px;
            font-size: .95rem; cursor: pointer; text-align: center;
            text-decoration: none; margin-top: 8px;
        }
        .btn:hover { background: #000; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }

        .alert { padding: 12px 16px; border-radius: 4px; font-size: .88rem; margin-bottom: 20px; }
        .alert-error   { background: #fdecea; border-left: 4px solid #e74c3c; color: #c0392b; }
        .alert-success { background: #eafaf1; border-left: 4px solid #27ae60; color: #1e8449; }

        .req-list { list-style: none; padding: 0; margin: 0; }
        .req-item {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 0; border-bottom: 1px solid #f0f0f0; font-size: .9rem;
        }
        .req-item:last-child { border-bottom: none; }
        .req-icon { font-size: 1rem; flex-shrink: 0; width: 20px; text-align: center; }
        .req-detail { color: #888; font-size: .8rem; margin-left: auto; }
        .req-item.fail { color: #c0392b; }

        .done-icon  { font-size: 3rem; text-align: center; margin-bottom: 16px; }
        .done-title { text-align: center; font-size: 1.2rem; font-weight: 700; margin-bottom: 8px; }
        .done-text  { text-align: center; color: #555; font-size: .9rem; margin-bottom: 28px; line-height: 1.7; }
    </style>
</head>
<body>
<div class="container">

    <div class="logo">Tako <span>CMS</span></div>
    <p class="subtitle">インストールウィザード</p>

    <?php if ($step !== 'done'): ?>
    <div class="steps">
        <?php foreach ($stepLabels as $num => $label): ?>
            <?php
            $n = (int)$num;
            $circleClass = match(true) {
                $n < $currentStepNum  => 'done',
                $n === $currentStepNum => 'active',
                default               => '',
            };
            ?>
            <div class="step-item">
                <div class="step-circle <?= $circleClass ?>">
                    <?= $n < $currentStepNum ? '✓' : $n ?>
                </div>
                <div class="step-label <?= $n === $currentStepNum ? 'active' : '' ?>">
                    <?= $label ?>
                </div>
            </div>
            <?php if ($n < 2): ?>
                <div class="step-line"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ===== Step 1: 要件チェック ===== -->
    <?php if ($step === '1'): ?>
    <div class="card">
        <h2>動作環境の確認</h2>
        <ul class="req-list">
            <?php foreach ($reqs as $req): ?>
            <li class="req-item <?= $req['pass'] ? '' : 'fail' ?>">
                <span class="req-icon"><?= $req['pass'] ? '✅' : '❌' ?></span>
                <span><?= esc($req['label']) ?></span>
                <?php if ($req['detail'] !== ''): ?>
                    <span class="req-detail"><?= esc($req['detail']) ?></span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if (!$req_all_ok): ?>
            <div class="alert alert-error" style="margin-top:20px;">
                ❌ の項目を解決してから進んでください。
            </div>
        <?php endif; ?>

        <div style="margin-top: 24px;">
            <?php if ($req_all_ok): ?>
                <a href="setup.php?step=2" class="btn">次へ →</a>
            <?php else: ?>
                <button class="btn" disabled>次へ →</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===== Step 2: サイト設定 + 管理者登録 ===== -->
    <?php elseif ($step === '2'): ?>
    <div class="card">
        <h2>サイト設定 &amp; 管理者登録</h2>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <form method="post" action="setup.php?step=2">

            <div class="section-label">サイト情報</div>

            <div class="field">
                <label>サイト名</label>
                <input type="text" name="site_name" required
                       value="<?= esc($_POST['site_name'] ?? '') ?>"
                       placeholder="例: My Portfolio">
            </div>
            <div class="field">
                <label>サイト URL</label>
                <input type="url" name="site_url" required
                       value="<?= esc($_POST['site_url'] ?? '') ?>"
                       placeholder="例: http://localhost:8888/myportfolio">
                <p class="hint">末尾のスラッシュは不要です。本番なら <code>https://example.com</code></p>
            </div>
            <div class="field">
                <label>サイト概要 <span class="optional">（任意・OGP に使用）</span></label>
                <input type="text" name="site_desc"
                       value="<?= esc($_POST['site_desc'] ?? '') ?>">
            </div>

            <div class="section-label">管理者アカウント</div>

            <div class="field">
                <label>ユーザー名（ログイン ID）</label>
                <input type="text" name="username" required autocomplete="off"
                       value="<?= esc($_POST['username'] ?? '') ?>">
            </div>
            <div class="field">
                <label>パスワード <span class="optional">（8文字以上）</span></label>
                <input type="password" name="password" required autocomplete="new-password">
            </div>
            <div class="field">
                <label>メールアドレス <span class="optional">（任意）</span></label>
                <input type="email" name="email"
                       value="<?= esc($_POST['email'] ?? '') ?>">
            </div>

            <button type="submit" class="btn" style="margin-top:24px;">
                インストール完了 →
            </button>
        </form>
    </div>

    <!-- ===== 完了 ===== -->
    <?php elseif ($step === 'done'):
        // レスポンス送信後に setup.php 自身を削除する
        register_shutdown_function(fn() => @unlink(__FILE__));
    ?>
    <div class="card">
        <div class="done-icon">🎉</div>
        <div class="done-title">インストール完了！</div>
        <p class="done-text">
            Tako CMS のセットアップが完了しました。<br>
            管理画面からログインして記事を作成できます。
        </p>
        <a href="login.php" class="btn">管理画面へログインする</a>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
