<?php
// ===================================================
//  記事編集
// ===================================================
require_once '../config.php';
require_login();

$pdo   = db();
$error = '';

$id = (int)($_GET['id'] ?? 0);

if ($id === 0) {
    header('Location: ' . SITE_URL . '/cms/admin/index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM posts WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$post = $stmt->fetch();

if (!$post) {
    header('Location: ' . SITE_URL . '/cms/admin/index.php');
    exit;
}

// カテゴリ一覧を取得
$c_stmt = $pdo->prepare('SELECT * FROM categories ORDER BY id ASC');
$c_stmt->execute();
$categories = $c_stmt->fetchAll();

// この記事に現在付与されているカテゴリIDを取得
$pc_stmt = $pdo->prepare('SELECT category_id FROM post_categories WHERE post_id = :post_id');
$pc_stmt->execute([':post_id' => $id]);
$post_category_id  = $pc_stmt->fetch();
$currentCategoryId = $post_category_id ? $post_category_id['category_id'] : null;

// 既存セクションを取得
$sec_stmt = $pdo->prepare('SELECT * FROM post_sections WHERE post_id = :post_id ORDER BY sort_order ASC');
$sec_stmt->execute([':post_id' => $id]);
$sections = $sec_stmt->fetchAll();

// ===================================================
//  更新処理
// ===================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $title        = trim($_POST['title']        ?? '');
    $status       = $_POST['status']            ?? 'draft';
    $thumbnail    = $post['thumbnail'];
    $category_id  = $_POST['category_id']       ?? '';
    $period       = trim($_POST['period']       ?? '');
    $type         = trim($_POST['type']         ?? '');
    $external_url = trim($_POST['external_url'] ?? '');
    $tags         = trim($_POST['tags']         ?? '');

    if ($title === '') {
        $error = 'タイトルは必須です。';
    } else {
        // ===================================================
        //  画像アップロード処理
        // ===================================================
        if (!empty($_FILES['thumbnail']['name'])) {
            $result = validate_image_upload($_FILES['thumbnail']); // MIMEタイプで検証
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $filename = uniqid() . '.' . $result['ext']; // [組み込み] uniqid()=ユニークなIDを生成
                if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], UPLOAD_DIR . $filename)) { // [組み込み] 一時ファイルを指定場所に移動
                    if ($post['thumbnail'] && file_exists(UPLOAD_DIR . $post['thumbnail'])) {
                        unlink(UPLOAD_DIR . $post['thumbnail']); // [組み込み] 古い画像を削除
                    }
                    $thumbnail = $filename;
                } else {
                    $error = '画像の保存に失敗しました。';
                }
            }
        }

        if (!empty($_POST['delete_thumbnail']) && $post['thumbnail']) {
            if (file_exists(UPLOAD_DIR . $post['thumbnail'])) {
                unlink(UPLOAD_DIR . $post['thumbnail']);
            }
            $thumbnail = null;
        }

        if ($error === '') {
            // posts テーブルを更新
            $stmt = $pdo->prepare(
                'UPDATE posts SET
                    title = :title, thumbnail = :thumbnail, status = :status,
                    period = :period, type = :type,
                    external_url = :external_url, tags = :tags
                 WHERE id = :id'
            );
            $stmt->execute([
                ':title'        => $title,
                ':thumbnail'    => $thumbnail,
                ':status'       => $status,
                ':period'       => $period,
                ':type'         => $type,
                ':external_url' => $external_url,
                ':tags'         => $tags,
                ':id'           => $id,
            ]);

            // カテゴリの紐付けを更新（全削除 → 入れ直し）
            $pc_stmt = $pdo->prepare('DELETE FROM post_categories WHERE post_id = :post_id');
            $pc_stmt->execute([':post_id' => $id]);

            if (!empty($category_id)) {
                $pc_stmt = $pdo->prepare('INSERT INTO post_categories (post_id, category_id) VALUES (:post_id, :category_id)');
                $pc_stmt->execute([':post_id' => $id, ':category_id' => $category_id]);
            }

            // セクションの更新（全削除 → 入れ直し）
            $pdo->prepare('DELETE FROM post_sections WHERE post_id = :post_id')
                ->execute([':post_id' => $id]);

            // name="section_title[]" / name="section_body[]" でフォームから配列として受け取る
            $section_titles = $_POST['section_title'] ?? []; // [組み込み] ??=nullなら空配列
            $section_bodies = $_POST['section_body']  ?? [];

            foreach ($section_titles as $i => $t) {
                if (trim($t) === '') continue; // タイトルが空のセクションはスキップ（削除ボタンを押さず空にしたものも対応）
                $s_stmt = $pdo->prepare(
                    'INSERT INTO post_sections (post_id, sort_order, title, body)
                     VALUES (:post_id, :sort_order, :title, :body)'
                );
                $s_stmt->execute([
                    ':post_id'    => $id,
                    ':sort_order' => $i,
                    ':title'      => trim($t),
                    ':body'       => trim($section_bodies[$i] ?? ''),
                ]);
            }

            header('Location: ' . SITE_URL . '/cms/admin/index.php');
            exit;
        }
    }

    // エラー時：フォームの入力値を保持する
    $post['title']        = $title;
    $post['status']       = $status;
    $post['period']       = $period;
    $post['type']         = $type;
    $post['external_url'] = $external_url;
    $post['tags']         = $tags;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>記事編集 | 管理画面</title>
    <link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.css">


    <script type="importmap">
    {
        "imports": {
            "ckeditor5": "https://cdn.ckeditor.com/ckeditor5/43.3.1/ckeditor5.js",
            "ckeditor5/": "https://cdn.ckeditor.com/ckeditor5/43.3.1/"
        }
    }
    </script>

    <style>
        body { font-family: sans-serif; max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 1.4rem; margin-bottom: 24px; }
        label { display: block; margin-top: 20px; font-size: .9rem; font-weight: bold; }
        input[type="text"], input[type="url"], textarea, select { width: 100%; padding: 8px; box-sizing: border-box; margin-top: 6px; border: 1px solid #ccc; font-size: 1rem; }
        textarea { height: 200px; resize: vertical; font-family: monospace; }
        .actions { margin-top: 24px; display: flex; gap: 12px; align-items: center; }
        button[type="submit"] { padding: 10px 24px; background: #222; color: #fff; border: none; cursor: pointer; font-size: 1rem; }
        a.back { font-size: .9rem; color: #666; }
        .error { margin-top: 16px; padding: 10px; background: #fdecea; border-left: 4px solid #c0392b; font-size: .9rem; }
        .meta { margin-top: 8px; font-size: .8rem; color: #999; }
        .thumbnail-preview img { max-width: 200px; margin-top: 8px; display: block; }
        .thumbnail-preview label { font-weight: normal; font-size: .85rem; color: #c0392b; margin-top: 6px; }

        /* セクションブロック */
        .section-block { border: 1px solid #ccc; border-radius: 6px; margin-top: 20px; background: #fafafa; overflow: hidden; }
        .section-block-header { display: flex; align-items: center; justify-content: space-between; background: #f0f0f0; border-bottom: 1px solid #ccc; padding: 8px 14px; }
        .section-block-number { font-size: .85rem; font-weight: bold; color: #444; }
        .section-block-body { padding: 16px; }
        .section-block label { margin-top: 12px; }
        .section-block label:first-child { margin-top: 0; }
        .section-delete-btn { background: none; border: none; color: #c0392b; cursor: pointer; font-size: .85rem; padding: 2px 6px; }
        .section-delete-btn:hover { background: #fdecea; border-radius: 4px; }
        .add-section-btn { margin-top: 16px; padding: 9px 20px; background: #555; color: #fff; border: none; cursor: pointer; font-size: .9rem; border-radius: 4px; }
        .add-section-btn:hover { background: #333; }
        .section-heading { font-size: 1rem; font-weight: bold; margin-top: 32px; margin-bottom: 8px; }

        /* CKEditor 編集エリアの最小高さ */
        .ck-editor__editable { min-height: 300px; }

        /* エディタ内テーブル */
        .ck-editor__editable table {
            border-collapse: collapse;
            width: 100%;
            margin: 8px 0;
        }
        .ck-editor__editable table th,
        .ck-editor__editable table td {
            border: 1px solid #bbb;
            padding: 8px 12px;
            font-size: .92rem;
        }
        .ck-editor__editable table th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: left;
        }
        .ck-editor__editable table tr:nth-child(even) td {
            background: #fafafa;
        }

        /* エディタ内グリッドレイアウト */
        .ck-editor__editable .is-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            outline: 2px dashed #aaa;
            outline-offset: 4px;
            padding: 8px;
            position: relative;
        }
        .ck-editor__editable .is-grid::before {
            content: 'グリッド';
            position: absolute;
            top: -18px;
            left: 0;
            font-size: .7rem;
            background: #aaa;
            color: #fff;
            padding: 1px 6px;
            border-radius: 2px;
            pointer-events: none;
        }

        .is-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
    </style>
</head>
<body>
    <h1>記事編集</h1>
    <p class="meta">ID: <?= h($post['id']) ?> ／ 作成日: <?= h($post['created_at']) ?></p>

    <?php if ($error !== ''): ?>
        <div class="error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <label>タイトル
            <input type="text" name="title" value="<?= h($post['title']) ?>" required>
        </label>

        <label>一覧用期間（例：2025.06 – 08）
            <input type="text" name="period" value="<?= h($post['period'] ?? '') ?>">
        </label>

        <label>種別（例：個人制作 / ブログサイト）
            <input type="text" name="type" value="<?= h($post['type'] ?? '') ?>">
        </label>

        <label>外部リンクURL
            <input type="url" name="external_url" value="<?= h($post['external_url'] ?? '') ?>">
        </label>

        <label>使用技術タグ（カンマ区切り 例：WordPress,SCSS,JavaScript）
            <input type="text" name="tags" value="<?= h($post['tags'] ?? '') ?>">
        </label>

        <label>サムネイル画像
            <?php if ($post['thumbnail']): ?>
                <div class="thumbnail-preview">
                    <img src="<?= UPLOAD_URL . h($post['thumbnail']) ?>" alt="現在のサムネイル">
                    <label>
                        <input type="checkbox" name="delete_thumbnail" value="1">
                        この画像を削除する
                    </label>
                </div>
            <?php endif; ?>
            <input type="file" name="thumbnail" accept="image/*" style="margin-top:8px;">
        </label>

        <label>ステータス
            <select name="status">
                <option value="draft"     <?= $post['status'] === 'draft'     ? 'selected' : '' ?>>下書き</option>
                <option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>>公開</option>
            </select>
        </label>

        <div class="categories">
            <label>カテゴリー
                <select name="category_id">
                    <option value="">選択してください</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= h($category['id']) ?>"
                            <?= $category['id'] == $currentCategoryId ? 'selected' : '' ?>>
                            <?= h($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <!-- セクション管理 -->
        <div class="section-heading">セクション</div>

        <div id="sections-wrap">
            <?php foreach ($sections as $i => $sec): ?>
                <div class="section-block">
                    <div class="section-block-header">
                        <span class="section-block-number">セクション <?= $i + 1 ?></span>
                        <button type="button" class="section-delete-btn" onclick="deleteSection(this)">削除</button>
                    </div>
                    <div class="section-block-body">
                        <label>見出し
                            <input type="text" name="section_title[]" value="<?= h($sec['title']) ?>">
                        </label>
                        <label>本文
                            <!-- [重要] WYSIWYGエディタの内容はHTMLのまま保存するため、h()でエスケープせずそのまま出力する。 -->
                            <textarea name="section_body[]" class="wysiwyg"><?= $sec['body'] ?></textarea>
                        </label>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="add-section-btn" onclick="addSection()">＋ セクションを追加</button>

        <div class="actions">
            <button type="submit">更新する</button>
            <a class="back" href="<?= SITE_URL ?>/cms/admin/index.php">← 一覧へ戻る</a>
        </div>
    </form>

    <script type="module">// [CKEditor 5] ここではClassicEditorを使用。必要に応じて他のビルドやカスタムビルドも利用可能
import {
    ClassicEditor,
    Essentials,
    Bold, Italic, Underline, Strikethrough,
    Heading,
    Paragraph,
    List,
    Link,
    BlockQuote,
    Indent, IndentBlock,
    SimpleUploadAdapter,
    Image, ImageCaption, ImageStyle, ImageToolbar, ImageResize, ImageUpload,
    Table, TableToolbar,
    Style,
    GeneralHtmlSupport
} from 'ckeditor5';
import 'ckeditor5/translations/ja.js';


    const editorConfig = {
        plugins: [
            Essentials,
            Bold, Italic, Underline, Strikethrough,
            Heading,
            Paragraph,
            List,
            Link,
            BlockQuote,
            Indent, IndentBlock,
            SimpleUploadAdapter,
            Table, TableToolbar,
            Style,
            GeneralHtmlSupport,
            Image, ImageCaption, ImageStyle, ImageToolbar, ImageResize, ImageUpload,
        ],
        toolbar: {
            items: [
                'heading', '|',
                'bold', 'italic', 'underline', 'strikethrough', '|',
                'bulletedList', 'numberedList', 'indent', 'outdent', '|',
                'link', 'blockQuote', 'uploadImage', 'insertTable', '|',
                'style', '|',
                'undo', 'redo',
            ],
            shouldNotGroupWhenFull: true,
        },
        simpleUpload: {
            uploadUrl: '<?= SITE_URL ?>/cms/admin/upload-image.php',
            withCredentials: true,
            headers: {
                'X-CSRF-Token': '<?= csrf_token() ?>',
            },
        },
        image: {
            toolbar: [
                'imageStyle:inline', 'imageStyle:block', 'imageStyle:side', '|',
                'toggleImageCaption', 'imageTextAlternative', '|',
                'resizeImage',
            ]
        },
        style: {
            definitions: [
                {
                    name: 'グリッドレイアウト',
                    element: 'figure',
                    classes: [ 'is-grid' ]
                },
                {
                    name: 'テーブル',
                    element: 'figure',
                    classes: [ 'table' ]
                }
            ]
        },
        htmlSupport: {
            allow: [
                {
                    name: /.*/,
                    attributes: true,
                    classes: true,
                    styles: true
                }
            ]
        },
        table: {
            contentToolbar: [
            'tableColumn',
            'tableRow',
            'mergeTableCells'
        ]
        },
        language: 'ja'
    };


    // textarea要素 → エディタインスタンス の対応を管理
    const editorInstances = new Map();

    function initEditor(textarea) {
        ClassicEditor.create(textarea, editorConfig)
            .then(editor => { editorInstances.set(textarea, editor); })
            .catch(err => console.error(err));
    }

    // 既存セクションのエディタを初期化
    document.querySelectorAll('.wysiwyg').forEach(initEditor);

    // フォーム送信前に CKEditor 5 の内容を textarea へ同期
    document.querySelector('form').addEventListener('submit', function() {
        editorInstances.forEach((editor, textarea) => {
            textarea.value = editor.getData();
        });
    });

    // type="module" はスコープが独立するため、onclick属性から呼べるようグローバルに公開
    window.addSection = function() {
        const wrap = document.getElementById('sections-wrap');
        const block = document.createElement('div');
        block.className = 'section-block';

        const sectionCount = document.querySelectorAll('.section-block').length;
        block.innerHTML = `
            <div class="section-block-header">
                <span class="section-block-number">セクション ${sectionCount}</span>
                <button type="button" class="section-delete-btn" onclick="deleteSection(this)">削除</button>
            </div>
            <div class="section-block-body">
                <label>見出し
                    <input type="text" name="section_title[]">
                </label>
                <label>本文
                    <textarea name="section_body[]" class="wysiwyg"></textarea>
                </label>
            </div>
        `;

        wrap.appendChild(block);
        initEditor(block.querySelector('.wysiwyg'));
    };

    window.deleteSection = function(btn) {// セクション削除ボタンが押されたときの処理
        const block = btn.closest('.section-block');
        const textarea = block.querySelector('textarea');
        if (textarea && editorInstances.has(textarea)) {
            editorInstances.get(textarea).destroy();
            editorInstances.delete(textarea);
        }
        block.remove();
    };
    </script>
</body>
</html>
