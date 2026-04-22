<?php
// ===================================================
//  公開トップページ — 記事一覧
// ===================================================
require_once 'cms/config.php';

$pdo = db();

// 公開済み記事をカテゴリ情報とともに取得
$stmt = $pdo->prepare('
    SELECT p.*, c.name AS category_name
    FROM posts p
    LEFT JOIN post_categories pc ON pc.post_id = p.id
    LEFT JOIN categories c       ON c.id = pc.category_id
    WHERE p.status = :status
    ORDER BY p.sort_order ASC
');
$stmt->execute([':status' => 'published']);
$posts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h(SITE_NAME) ?></title>
    <meta name="description" content="<?= h(SITE_DESCRIPTION) ?>">

    <!-- OGP -->
    <meta property="og:title"       content="<?= h(SITE_NAME) ?>">
    <meta property="og:description" content="<?= h(SITE_DESCRIPTION) ?>">
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="<?= h(SITE_URL) ?>">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f7f7f7;
            color: #222;
            line-height: 1.6;
        }

        /* ---- ヘッダー ---- */
        header {
            background: #111;
            color: #fff;
            padding: 24px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .site-title {
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: -0.5px;
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

        /* ---- コンテンツ ---- */
        main {
            max-width: 1100px;
            margin: 48px auto;
            padding: 0 24px;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 32px;
        }

        /* ---- カードグリッド ---- */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
        }

        .card {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 4px rgba(0,0,0,.08);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            transition: box-shadow .2s, transform .2s;
        }

        .card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,.12);
            transform: translateY(-2px);
        }

        .card-thumb {
            width: 100%;
            aspect-ratio: 16 / 9;
            object-fit: cover;
            background: #e0e0e0;
            display: block;
        }

        .card-thumb-placeholder {
            width: 100%;
            aspect-ratio: 16 / 9;
            background: #e8e8e8;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #bbb;
            font-size: 2rem;
        }

        .card-body {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .card-category {
            font-size: .75rem;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .card-title {
            font-size: 1rem;
            font-weight: 700;
            line-height: 1.4;
        }

        .card-meta {
            font-size: .8rem;
            color: #999;
            margin-top: auto;
        }

        .card-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px;
        }

        .tag {
            background: #f0f0f0;
            border-radius: 4px;
            padding: 2px 8px;
            font-size: .75rem;
            color: #555;
        }

        /* ---- 空状態 ---- */
        .empty {
            text-align: center;
            padding: 80px 0;
            color: #aaa;
        }

        /* ---- フッター ---- */
        footer {
            text-align: center;
            padding: 40px 24px;
            font-size: .8rem;
            color: #aaa;
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
    <h1 class="page-title">Works</h1>

    <?php if (empty($posts)): ?>
        <div class="empty">
            <p>まだ公開された記事がありません。</p>
        </div>
    <?php else: ?>
        <div class="card-grid">
            <?php foreach ($posts as $post): ?>
                <a class="card" href="<?= h(SITE_URL) ?>/post.php?id=<?= h($post['id']) ?>">

                    <?php if ($post['thumbnail']): ?>
                        <img class="card-thumb"
                             src="<?= h(UPLOAD_URL . $post['thumbnail']) ?>"
                             alt="<?= h($post['title']) ?>">
                    <?php else: ?>
                        <div class="card-thumb-placeholder">📄</div>
                    <?php endif; ?>

                    <div class="card-body">
                        <?php if ($post['category_name']): ?>
                            <div class="card-category"><?= h($post['category_name']) ?></div>
                        <?php endif; ?>

                        <div class="card-title"><?= h($post['title']) ?></div>

                        <?php if ($post['tags']): ?>
                            <div class="card-tags">
                                <?php foreach (explode(',', $post['tags']) as $tag): ?>
                                    <?php $tag = trim($tag); ?>
                                    <?php if ($tag !== ''): ?>
                                        <span class="tag"><?= h($tag) ?></span>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="card-meta">
                            <?php if ($post['period']): ?>
                                <?= h($post['period']) ?>
                                <?= $post['type'] ? ' — ' : '' ?>
                            <?php endif; ?>
                            <?= h($post['type'] ?? '') ?>
                        </div>
                    </div>

                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<footer>
    <p>&copy; <?= date('Y') ?> <?= h(SITE_NAME) ?>. Powered by <a href="#" style="color:#aaa;">Tako CMS</a>.</p>
</footer>

</body>
</html>
