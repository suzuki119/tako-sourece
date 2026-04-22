<?php
// ===================================================
//  スキル一覧・削除
// ===================================================
require_once '../config.php';
require_login();

$pdo = db();

// 削除処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['delete_id'])) {
    csrf_verify();
    $stmt = $pdo->prepare('DELETE FROM skill WHERE id = :id');
    $stmt->execute([':id' => (int)$_POST['delete_id']]);
    header('Location: ' . SITE_URL . '/cms/admin/skill.php');
    exit;
}

// 一覧取得
$stmt = $pdo->prepare('SELECT * FROM skill ORDER BY id ASC');
$stmt->execute();
$skills = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>スキル管理 | 管理画面</title>
    <style>
        body { font-family: sans-serif; max-width: 900px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 1.4rem; margin-bottom: 24px; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        a.button { padding: 8px 16px; background: #222; color: #fff; text-decoration: none; font-size: .9rem; }
        table { width: 100%; border-collapse: collapse; font-size: .9rem; }
        th, td { padding: 10px 12px; border-bottom: 1px solid #ddd; text-align: left; vertical-align: middle; }
        th { background: #f5f5f5; }
        .thumb { width: 60px; height: 40px; object-fit: cover; }
        .actions form { display: inline; }
        .actions a { margin-right: 8px; color: #333; font-size: .85rem; }
        .actions button { background: none; border: none; color: #c0392b; cursor: pointer; font-size: .85rem; }
        .empty { padding: 40px; text-align: center; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>スキル管理</h1>
        <div>
            <a class="button" href="<?= SITE_URL ?>/cms/admin/skill-edit.php">+ 新規追加</a>
            <a class="button" href="<?= SITE_URL ?>/cms/admin/index.php" style="margin-left:8px; background:#666;">← 戻る</a>
        </div>
    </div>

    <?php if (empty($skills)): ?>
        <p class="empty">スキルがまだありません。</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>画像</th>
                    <th>タイトル</th>
                    <th>期間</th>
                    <th>説明</th>
                    <th>操作</th>
                    <th>カテゴリー</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($skills as $skill): ?>
                <tr>
                    <td>
                        <?php if ($skill['image_url']): ?>
                            <img src="<?= h($skill['image_url']) ?>" class="thumb" alt="">
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= h($skill['title']) ?></td>
                    <td><?= h($skill['period'] ?? '') ?></td>
                    <td><?= h(mb_strimwidth($skill['body'] ?? '', 0, 40, '…')) ?></td>
                    <td class="actions">
                        <a href="<?= SITE_URL ?>/cms/admin/skill-edit.php?id=<?= h($skill['id']) ?>">編集</a>
                        <form method="post" onsubmit="return confirm('削除しますか？');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="delete_id" value="<?= h($skill['id']) ?>">
                            <button type="submit">削除</button>
                        </form>
                    </td>
                    <td><?= h($skill['category'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
