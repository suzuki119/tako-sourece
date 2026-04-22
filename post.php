<?php
// ===================================================
//  公開ページ — 記事詳細
// ===================================================
require_once 'cms/config.php';

$pdo = db();

$id = (int)($_GET['id'] ?? 0);

if ($id === 0) {
    header('Location: ' . SITE_URL . '/');
    exit;
}

// 公開済み記事のみ取得（下書きは 404）
$stmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id AND status = :status LIMIT 1');
$stmt->execute([':id' => $id, ':status' => 'published']);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>404</title></head>';
    echo '<body style="font-family:sans-serif;text-align:center;padding:80px;">';
    echo '<h1>404 — ページが見つかりません</h1>';
    echo '<p><a href="' . h(SITE_URL) . '/">一覧へ戻る</a></p>';
    echo '</body></html>';
    exit;
}

// カテゴリを取得
$cat_stmt = $pdo->prepare('
    SELECT c.name FROM categories c
    JOIN post_categories pc ON pc.category_id = c.id
    WHERE pc.post_id = :post_id
    LIMIT 1
');
$cat_stmt->execute([':post_id' => $id]);
$category_name = $cat_stmt->fetchColumn() ?: '';

// セクションを取得
$sec_stmt = $pdo->prepare(
    'SELECT * FROM post_sections WHERE post_id = :post_id ORDER BY sort_order ASC'
);
$sec_stmt->execute([':post_id' => $id]);
$sections = $sec_stmt->fetchAll();

// タグを配列に変換
$tags = array_filter(array_map('trim', explode(',', $post['tags'] ?? '')));

// OGP 用サムネイル URL
$og_image = $post['thumbnail'] ? UPLOAD_URL . $post['thumbnail'] : '';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($post['title']) ?> | <?= h(SITE_NAME) ?></title>

    <!-- OGP -->
    <meta property="og:title"       content="<?= h($post['title']) ?>">
    <meta property="og:type"        content="article">
    <meta property="og:url"         content="<?= h(SITE_URL) ?>/post.php?id=<?= h($post['id']) ?>">
    <?php if ($og_image): ?>
    <meta property="og:image"       content="<?= h($og_image) ?>">
    <?php endif; ?>

    <!-- CKEditor 5 のスタイルをフロントでも読み込む（本文HTML用） -->
    <link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.css">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f7f7f7;
            color: #222;
            line-height: 1.7;
        }

        /* ---- ヘッダー ---- */
        header {
            background: #111;
            color: #fff;
            padding: 20px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .site-title {
            font-size: 1.1rem;
            font-weight: 700;
            text-decoration: none;
            color: #fff;
        }

        header nav a {
            color: #ccc;
            text-decoration: none;
            font-size: .85rem;
            margin-left: 20px;
        }

        header nav a:hover { color: #fff; }

        /* ---- メイン ---- */
        main {
            max-width: 820px;
            margin: 48px auto;
            padding: 0 24px 80px;
        }

        /* ---- パンくず ---- */
        .breadcrumb {
            font-size: .8rem;
            color: #999;
            margin-bottom: 24px;
        }

        .breadcrumb a { color: #999; text-decoration: none; }
        .breadcrumb a:hover { color: #333; }
        .breadcrumb span { margin: 0 6px; }

        /* ---- 記事ヘッダー ---- */
        .post-category {
            font-size: .78rem;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            letter-spacing: .6px;
            margin-bottom: 12px;
        }

        .post-title {
            font-size: 2rem;
            font-weight: 800;
            line-height: 1.3;
            letter-spacing: -0.5px;
            margin-bottom: 16px;
        }

        .post-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            font-size: .85rem;
            color: #888;
            margin-bottom: 24px;
        }

        .post-meta-item { display: flex; align-items: center; gap: 4px; }

        .post-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 32px;
        }

        .tag {
            background: #eee;
            border-radius: 4px;
            padding: 3px 10px;
            font-size: .78rem;
            color: #555;
        }

        /* ---- サムネイル ---- */
        .post-thumbnail {
            width: 100%;
            max-height: 420px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 40px;
            display: block;
        }

        /* ---- 外部リンク ---- */
        .external-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            background: #111;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-size: .9rem;
            margin-bottom: 40px;
        }

        .external-link:hover { background: #333; }

        /* ---- セクション ---- */
        .section {
            margin-bottom: 48px;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        /* ---- CKEditor 出力のスタイル調整 ---- */
        .section-body {
            font-size: .97rem;
            line-height: 1.8;
        }

        .section-body h2 { font-size: 1.3rem; margin: 1.6em 0 .6em; }
        .section-body h3 { font-size: 1.1rem; margin: 1.4em 0 .5em; }
        .section-body p  { margin-bottom: 1em; }

        .section-body ul,
        .section-body ol {
            padding-left: 1.5em;
            margin-bottom: 1em;
        }

        .section-body li { margin-bottom: .4em; }

        .section-body blockquote {
            border-left: 4px solid #ccc;
            margin: 1em 0;
            padding: 8px 16px;
            color: #666;
            font-style: italic;
        }

        .section-body table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 1em;
            font-size: .92rem;
        }

        .section-body table th,
        .section-body table td {
            border: 1px solid #ddd;
            padding: 8px 12px;
            text-align: left;
        }

        .section-body table th { background: #f5f5f5; font-weight: 700; }

        .section-body img {
            max-width: 100%;
            border-radius: 4px;
            margin: 1em 0;
            display: block;
        }

        /* ---- グリッドレイアウト（CKEditor 用カスタムクラス） ---- */
        .section-body .is-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 1em;
        }

        /* ---- 戻るリンク ---- */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: .9rem;
            color: #666;
            text-decoration: none;
            margin-top: 40px;
        }

        .back-link:hover { color: #222; }

        /* ---- フッター ---- */
        footer {
            text-align: center;
            padding: 40px 24px;
            font-size: .8rem;
            color: #aaa;
        }

        @media (max-width: 600px) {
            .post-title { font-size: 1.5rem; }
            .section-body .is-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<header>
    <a class="site-title" href="<?= h(SITE_URL) ?>"><?= h(SITE_NAME) ?></a>
    <nav>
        <a href="<?= h(SITE_URL) ?>/cms/admin/index.php">管理画面</a>
    </nav>
</header>

<main>

    <!-- パンくず -->
    <div class="breadcrumb">
        <a href="<?= h(SITE_URL) ?>/">Works</a>
        <span>›</span>
        <?= h($post['title']) ?>
    </div>

    <!-- 記事ヘッダー -->
    <?php if ($category_name): ?>
        <div class="post-category"><?= h($category_name) ?></div>
    <?php endif; ?>

    <h1 class="post-title"><?= h($post['title']) ?></h1>

    <div class="post-meta">
        <?php if ($post['period']): ?>
            <div class="post-meta-item">📅 <?= h($post['period']) ?></div>
        <?php endif; ?>
        <?php if ($post['type']): ?>
            <div class="post-meta-item">🏷 <?= h($post['type']) ?></div>
        <?php endif; ?>
    </div>

    <?php if (!empty($tags)): ?>
        <div class="post-tags">
            <?php foreach ($tags as $tag): ?>
                <span class="tag"><?= h($tag) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- サムネイル -->
    <?php if ($post['thumbnail']): ?>
        <img class="post-thumbnail"
             src="<?= h(UPLOAD_URL . $post['thumbnail']) ?>"
             alt="<?= h($post['title']) ?>">
    <?php endif; ?>

    <!-- 外部リンク -->
    <?php if ($post['external_url']): ?>
        <a class="external-link" href="<?= h($post['external_url']) ?>" target="_blank" rel="noopener noreferrer">
            🔗 サイトを見る
        </a>
    <?php endif; ?>

    <!-- セクション本文 -->
    <?php if (!empty($sections)): ?>
        <?php foreach ($sections as $section): ?>
            <div class="section">
                <?php if ($section['title'] !== '' && $section['title'] !== null): ?>
                    <h2 class="section-title"><?= h($section['title']) ?></h2>
                <?php endif; ?>
                <?php if ($section['body']): ?>
                    <!-- CKEditor が生成した HTML をそのまま出力（管理者のみ入力可能なため信頼済み） -->
                    <div class="section-body"><?= $section['body'] ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <a class="back-link" href="<?= h(SITE_URL) ?>/">← 一覧へ戻る</a>

</main>

<footer>
    <p>&copy; <?= date('Y') ?> <?= h(SITE_NAME) ?>. Powered by <a href="#" style="color:#aaa;">Tako CMS</a>.</p>
</footer>

</body>
</html>
