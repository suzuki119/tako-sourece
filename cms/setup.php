<?php
// ===================================================
//  インストールウィザード
// ===================================================
session_start();

$configFile = __DIR__ . '/config.php';

// --- インストール済み判定 ---
// config.php が存在し、かつ管理者ユーザーが登録済みならアクセスを拒否
if (file_exists($configFile)) {
    try {
        require_once $configFile;
        $pdo   = db();
        $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($count > 0) {
            header('Location: admin/index.php');
            exit;
        }
        // テーブルはあるがユーザー未登録 → Step 3 へ
        if (!isset($_GET['step']) || (int)$_GET['step'] < 3) {
            header('Location: setup.php?step=3');
            exit;
        }
    } catch (Throwable $e) {
        // config.php が壊れている場合は Step 2 からやり直す
    }
}

$step  = $_GET['step'] ?? '1';
$error = '';

// =====================================================
//  ヘルパー関数
// =====================================================

function try_connect(string $host, string $name, string $user, string $pass): PDO
{
    return new PDO(
        "mysql:host={$host};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
}

function make_config(string $host, string $name, string $user, string $pass, string $siteUrl): string
{
    // シングルクォートをエスケープ（定数値に埋め込むため）
    $escape = fn(string $v): string => str_replace("'", "\\'", $v);

    $host    = $escape($host);
    $name    = $escape($name);
    $user    = $escape($user);
    $pass    = $escape($pass);
    $siteUrl = rtrim($escape($siteUrl), '/');

    // config.sample.php のプレースホルダーを置換して返す
    $template = file_get_contents(__DIR__ . '/config.sample.php');
    $template = str_replace(
        ['{{DB_HOST}}', '{{DB_NAME}}', '{{DB_USER}}', '{{DB_PASS}}', '{{SITE_URL}}'],
        [$host, $name, $user, $pass, $siteUrl],
        $template
    );
    return $template;
}

function create_tables(PDO $pdo): void
{
    $statements = [
        "CREATE TABLE IF NOT EXISTS users (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            username   VARCHAR(100) NOT NULL UNIQUE,
            password   VARCHAR(255) NOT NULL,
            email      VARCHAR(255),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS categories (
            id   INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS posts (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            title        VARCHAR(255) NOT NULL,
            thumbnail    VARCHAR(255),
            status       ENUM('draft','published') DEFAULT 'draft',
            author_id    INT,
            period       VARCHAR(100),
            type         VARCHAR(100),
            external_url VARCHAR(255),
            tags         TEXT,
            sort_order   INT DEFAULT 0,
            created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS post_categories (
            post_id     INT NOT NULL,
            category_id INT NOT NULL,
            PRIMARY KEY (post_id, category_id),
            FOREIGN KEY (post_id)     REFERENCES posts(id)      ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS post_sections (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            post_id    INT NOT NULL,
            heading    VARCHAR(255),
            body       TEXT,
            image_url  VARCHAR(255),
            sort_order INT DEFAULT 0,
            FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        "CREATE TABLE IF NOT EXISTS skill (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            title     VARCHAR(255) NOT NULL,
            period    VARCHAR(100),
            body      TEXT,
            image_url VARCHAR(255),
            category  VARCHAR(100)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    foreach ($statements as $sql) {
        $pdo->exec($sql);
    }
}

// =====================================================
//  各ステップの POST 処理
// =====================================================

// --- Step 2: DB 接続テスト → config.php 生成 → テーブル作成 ---
if ($step === '2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host  = trim($_POST['db_host']  ?? 'localhost');
    $db_name  = trim($_POST['db_name']  ?? '');
    $db_user  = trim($_POST['db_user']  ?? '');
    $db_pass  = $_POST['db_pass']       ?? '';
    $site_url = rtrim(trim($_POST['site_url'] ?? ''), '/');

    if ($db_name === '' || $db_user === '' || $site_url === '') {
        $error = 'DB名・DBユーザー名・サイトURLは必須です。';
    } else {
        try {
            $pdo = try_connect($db_host, $db_name, $db_user, $db_pass);
            create_tables($pdo);

            $content = make_config($db_host, $db_name, $db_user, $db_pass, $site_url);
            if (file_put_contents($configFile, $content) === false) {
                $error = 'config.php の書き込みに失敗しました。フォルダの書き込み権限を確認してください（chmod 755）。';
            } else {
                header('Location: setup.php?step=3');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'DB接続エラー: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}

// --- Step 3: 管理者ユーザー登録 ---
if ($step === '3' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!file_exists($configFile)) {
        header('Location: setup.php?step=2');
        exit;
    }

    if (!function_exists('db')) {
        require_once $configFile;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password']       ?? '';
    $email    = trim($_POST['email']    ?? '');

    if ($username === '' || $password === '') {
        $error = 'ユーザー名とパスワードは必須です。';
    } elseif (strlen($password) < 8) {
        $error = 'パスワードは8文字以上にしてください。';
    } else {
        try {
            $pdo    = db();
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt   = $pdo->prepare('INSERT INTO users (username, password, email) VALUES (:u, :p, :e)');
            $stmt->execute([':u' => $username, ':p' => $hashed, ':e' => $email]);
            header('Location: setup.php?step=done');
            exit;
        } catch (PDOException $e) {
            $error = 'ユーザー登録エラー: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        }
    }
}

// =====================================================
//  Step 1: 要件チェックデータ
// =====================================================
$reqs        = [];
$req_all_ok  = true;

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
            'label'  => 'PDO MySQL 拡張',
            'pass'   => extension_loaded('pdo_mysql'),
            'detail' => '',
        ],
        [
            'label'  => 'uploads/ ディレクトリが書き込み可能',
            'pass'   => is_writable(__DIR__ . '/uploads/'),
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

// =====================================================
//  出力用ヘルパー
// =====================================================
function esc(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$stepLabels = ['1' => '動作確認', '2' => 'データベース設定', '3' => '管理者登録'];
$currentStepNum = match($step) {
    '1'    => 1,
    '2'    => 2,
    '3'    => 3,
    'done' => 4,
    default => 1,
};
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
            background: #f5f5f5;
            margin: 0;
            padding: 40px 20px;
            color: #222;
        }

        .container {
            max-width: 560px;
            margin: 0 auto;
        }

        .logo {
            font-size: 1.6rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .logo span { color: #e74c3c; }

        .subtitle {
            text-align: center;
            color: #666;
            font-size: .9rem;
            margin-bottom: 32px;
        }

        /* ---- ステップインジケーター ---- */
        .steps {
            display: flex;
            align-items: center;
            margin-bottom: 32px;
        }

        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
        }

        .step-circle {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #ddd;
            color: #999;
            font-size: .8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .step-circle.active  { background: #222; color: #fff; }
        .step-circle.done    { background: #27ae60; color: #fff; }

        .step-label {
            font-size: .72rem;
            color: #999;
            margin-top: 6px;
            text-align: center;
        }

        .step-label.active { color: #222; font-weight: 600; }

        .step-line {
            flex: 1;
            height: 2px;
            background: #ddd;
            margin: 0 4px;
            margin-bottom: 20px;
        }

        /* ---- カード ---- */
        .card {
            background: #fff;
            border-radius: 8px;
            padding: 32px;
            box-shadow: 0 1px 4px rgba(0,0,0,.1);
        }

        .card h2 {
            font-size: 1.1rem;
            margin: 0 0 24px;
        }

        /* ---- フォーム ---- */
        label {
            display: block;
            font-size: .85rem;
            font-weight: 600;
            margin-bottom: 4px;
        }

        label .optional {
            font-weight: 400;
            color: #999;
            font-size: .8rem;
        }

        input[type="text"],
        input[type="password"],
        input[type="email"],
        input[type="url"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: .95rem;
            outline: none;
            transition: border-color .15s;
        }

        input:focus { border-color: #222; }

        .field { margin-bottom: 20px; }

        .hint {
            font-size: .78rem;
            color: #888;
            margin-top: 4px;
        }

        /* ---- ボタン ---- */
        .btn {
            display: inline-block;
            padding: 11px 28px;
            background: #222;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: .95rem;
            cursor: pointer;
            text-decoration: none;
            transition: background .15s;
        }

        .btn:hover { background: #000; }
        .btn:disabled { background: #ccc; cursor: not-allowed; }

        .btn-full { width: 100%; text-align: center; }

        /* ---- アラート ---- */
        .alert {
            padding: 12px 16px;
            border-radius: 4px;
            font-size: .88rem;
            margin-bottom: 20px;
        }

        .alert-error   { background: #fdecea; border-left: 4px solid #e74c3c; color: #c0392b; }
        .alert-success { background: #eafaf1; border-left: 4px solid #27ae60; color: #1e8449; }

        /* ---- 要件チェック ---- */
        .req-list { list-style: none; padding: 0; margin: 0; }

        .req-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: .9rem;
        }

        .req-item:last-child { border-bottom: none; }

        .req-icon { font-size: 1rem; flex-shrink: 0; width: 20px; text-align: center; }
        .req-detail { color: #888; font-size: .8rem; margin-left: auto; }

        .req-item.fail { color: #c0392b; }

        /* ---- 完了ページ ---- */
        .done-icon {
            font-size: 3rem;
            text-align: center;
            margin-bottom: 16px;
        }

        .done-title {
            text-align: center;
            font-size: 1.2rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .done-text {
            text-align: center;
            color: #555;
            font-size: .9rem;
            margin-bottom: 28px;
            line-height: 1.7;
        }

        .warning-box {
            background: #fff8e1;
            border: 1px solid #ffe082;
            border-radius: 4px;
            padding: 12px 16px;
            font-size: .85rem;
            color: #7d5a00;
            margin-bottom: 24px;
        }
    </style>
</head>
<body>
<div class="container">

    <div class="logo">Tako <span>CMS</span></div>
    <p class="subtitle">インストールウィザード</p>

    <!-- ステップインジケーター -->
    <?php if ($step !== 'done'): ?>
    <div class="steps">
        <?php foreach ($stepLabels as $num => $label): ?>
            <?php
            $n = (int)$num;
            $circleClass = match(true) {
                $n < $currentStepNum => 'done',
                $n === $currentStepNum => 'active',
                default => '',
            };
            $labelClass = $n === $currentStepNum ? 'active' : '';
            ?>
            <div class="step-item">
                <div class="step-circle <?= $circleClass ?>">
                    <?= $n < $currentStepNum ? '✓' : $n ?>
                </div>
                <div class="step-label <?= $labelClass ?>"><?= esc($label) ?></div>
            </div>
            <?php if ($n < 3): ?>
                <div class="step-line"></div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ===================== Step 1: 要件チェック ===================== -->
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
                ❌ が付いている項目を解決してから進んでください。
            </div>
        <?php endif; ?>

        <div style="margin-top: 24px;">
            <?php if ($req_all_ok): ?>
                <a href="setup.php?step=2" class="btn btn-full">次へ: データベース設定 →</a>
            <?php else: ?>
                <button class="btn btn-full" disabled>次へ: データベース設定 →</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ===================== Step 2: DB 設定フォーム ===================== -->
    <?php elseif ($step === '2'): ?>
    <div class="card">
        <h2>データベース設定</h2>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <form method="post" action="setup.php?step=2">
            <div class="field">
                <label>DB ホスト名</label>
                <input type="text" name="db_host"
                       value="<?= esc($_POST['db_host'] ?? 'localhost') ?>">
                <p class="hint">通常は <code>localhost</code>。レンタルサーバーはパネルで確認してください。</p>
            </div>
            <div class="field">
                <label>DB 名</label>
                <input type="text" name="db_name" required
                       value="<?= esc($_POST['db_name'] ?? '') ?>">
            </div>
            <div class="field">
                <label>DB ユーザー名</label>
                <input type="text" name="db_user" required autocomplete="off"
                       value="<?= esc($_POST['db_user'] ?? '') ?>">
            </div>
            <div class="field">
                <label>DB パスワード <span class="optional">（なければ空白）</span></label>
                <input type="password" name="db_pass" autocomplete="new-password">
            </div>
            <div class="field">
                <label>サイト URL</label>
                <input type="url" name="site_url" required
                       value="<?= esc($_POST['site_url'] ?? '') ?>"
                       placeholder="例: http://localhost:8888/myportfolio">
                <p class="hint">末尾のスラッシュは不要です。本番なら <code>https://example.com</code></p>
            </div>

            <button type="submit" class="btn btn-full">接続テスト &amp; 次へ →</button>
        </form>
    </div>

    <!-- ===================== Step 3: 管理者ユーザー登録 ===================== -->
    <?php elseif ($step === '3'): ?>
    <div class="card">
        <h2>管理者アカウントの作成</h2>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <div class="alert alert-success">
            データベースへの接続とテーブル作成が完了しました。
        </div>

        <form method="post" action="setup.php?step=3">
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

            <button type="submit" class="btn btn-full">管理者を登録して完了 →</button>
        </form>
    </div>

    <!-- ===================== Step done: 完了 ===================== -->
    <?php elseif ($step === 'done'): ?>
    <div class="card">
        <div class="done-icon">🎉</div>
        <div class="done-title">インストール完了！</div>
        <p class="done-text">
            Tako CMS のセットアップが完了しました。<br>
            管理画面からログインして記事を作成できます。
        </p>

        <div class="warning-box">
            <strong>セキュリティ注意：</strong><br>
            <code>setup.php</code> はインストール後に削除またはアクセス制限することを推奨します。<br>
            管理者ユーザーが登録されていれば、次回からは自動的にリダイレクトされます。
        </div>

        <a href="login.php" class="btn btn-full">管理画面へログインする</a>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
