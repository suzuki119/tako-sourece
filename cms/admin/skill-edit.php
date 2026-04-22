<?php
// ===================================================
//  スキル 新規作成 / 編集（?id= なしで新規、あれば編集）
// ===================================================
require_once '../config.php';
require_login();

$pdo   = db();
$error = '';
$id    = (int)($_GET['id'] ?? 0);
$isNew = ($id === 0);

// 編集モードの場合：既存データを取得
if (!$isNew) {
    $stmt = $pdo->prepare('SELECT * FROM skill WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $skill = $stmt->fetch();

    if (!$skill) {
        header('Location: ' . SITE_URL . '/cms/admin/skill.php');
        exit;
    }
} else {
    $skill = ['title' => '', 'period' => '', 'body' => '', 'image_url' => '', 'category' => ''];
}

// ===================================================
//  保存処理
// ===================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $title  = trim($_POST['title']  ?? '');
    $period = trim($_POST['period'] ?? '');
    $body   = trim($_POST['body']   ?? '');
    $category = trim($_POST['category'] ?? '');

    if ($title === '') {
        $error = 'タイトルは必須です。';
    } else {

        // 画像アップロード処理
        $image_url = $skill['image_url'];

        if (!empty($_FILES['image']['name'])) {
            $result = validate_image_upload($_FILES['image'], allow_svg: true); // SVGも許可
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $filename = uniqid() . '.' . $result['ext'];
                if (move_uploaded_file($_FILES['image']['tmp_name'], UPLOAD_DIR . $filename)) {
                    $image_url = UPLOAD_URL . $filename;
                } else {
                    $error = '画像エラー';
                }
            }
        }

        // 画像削除チェック
        if (!empty($_POST['delete_image'])) {
            $image_url = '';
        }

        if ($error === '') {
            if ($isNew) {
                $stmt = $pdo->prepare(
                    'INSERT INTO skill (title, period, body, image_url, category) VALUES (:title, :period, :body, :image_url, :category)'
                );
                $stmt->execute([
                    ':title'     => $title,
                    ':period'    => $period,
                    ':body'      => $body,
                    ':image_url' => $image_url,
                    ':category'  => $category
                ]);
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE skill SET title = :title, period = :period, body = :body, image_url = :image_url, category = :category WHERE id = :id'
                );
                $stmt->execute([
                    ':title'     => $title,
                    ':period'    => $period,
                    ':body'      => $body,
                    ':image_url' => $image_url,
                    ':category'  => $category,
                    ':id'        => $id,
                ]);
            }

            header('Location: ' . SITE_URL . '/cms/admin/skill.php');
            exit;
        }

        // エラー時：入力値を保持
        $skill['title']  = $title;
        $skill['period'] = $period;
        $skill['body']   = $body;
        $skill['category'] = $category;
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?= $isNew ? 'スキル新規追加' : 'スキル編集' ?> | 管理画面</title>
    <style>
        body { font-family: sans-serif; max-width: 700px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 1.4rem; margin-bottom: 24px; }
        label { display: block; margin-top: 20px; font-size: .9rem; font-weight: bold; }
        input[type="text"], textarea { width: 100%; padding: 8px; box-sizing: border-box; margin-top: 6px; border: 1px solid #ccc; font-size: 1rem; }
        textarea { height: 160px; resize: vertical; }
        .actions { margin-top: 24px; display: flex; gap: 12px; align-items: center; }
        button[type="submit"] { padding: 10px 24px; background: #222; color: #fff; border: none; cursor: pointer; font-size: 1rem; }
        a.back { font-size: .9rem; color: #666; }
        .error { margin-top: 16px; padding: 10px; background: #fdecea; border-left: 4px solid #c0392b; font-size: .9rem; }
        .image-preview img { max-width: 200px; margin-top: 8px; display: block; }
        .image-preview label { font-weight: normal; font-size: .85rem; color: #c0392b; margin-top: 6px; }
    </style>
</head>
<body>
    <h1><?= $isNew ? 'スキル新規追加' : 'スキル編集' ?></h1>

    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <label>タイトル（必須）
            <input type="text" name="title" value="<?= h($skill['title']) ?>" required>
        </label>

        <label>期間（例：2年間）
            <input type="text" name="period" value="<?= h($skill['period'] ?? '') ?>">
        </label>

        <label>説明
            <textarea name="body"><?= h($skill['body'] ?? '') ?></textarea>
        </label>

        <label>カテゴリー
            <select name="category" style="width:100%;padding:8px;margin-top:6px;border:1px solid #ccc;font-size:1rem;">
                <?php

                $cats = ['プログラミング', 'デザイン', 'その他'];

                foreach ($cats as $cat):
                ?>
                <option value="<?= h($cat) ?>" <?= ($skill['category'] ?? '') === $cat ? 'selected' : '' ?>>
                    <?= h($cat) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>アイコン画像（任意）
            <?php if (!empty($skill['image_url'])): ?>
                <div class="image-preview">
                    <img src="<?= h($skill['image_url']) ?>" alt="現在の画像">
                    <label>
                        <input type="checkbox" name="delete_image" value="1">
                        この画像を削除する
                    </label>
                </div>
            <?php endif; ?>
            <input type="file" name="image" accept="image/*" style="margin-top:8px;">
        </label>

        <div class="actions">
            <button type="submit"><?= $isNew ? '追加する' : '更新する' ?></button>
            <a class="back" href="<?= SITE_URL ?>/cms/admin/skill.php">← 一覧へ戻る</a>
        </div>

    </form>
</body>
</html>
