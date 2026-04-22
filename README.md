# Tako CMS

シンプルなポートフォリオ向け PHP 製オープンソース CMS です。  
ブラウザベースのインストールウィザードで、ファイルを置くだけでセットアップが完了します。

---

## 機能

- ブラウザで完結するインストールウィザード（WordPress 方式）
- 記事の作成・編集・削除・並び替え
- WYSIWYG エディタ（CKEditor 5）
- カテゴリ管理
- スキル管理
- 画像アップロード
- 下書き / 公開 ステータス管理
- CSRF 対策・MIME タイプ検証済み

---

## 動作環境

| 項目 | 要件 |
|------|------|
| PHP | 8.0 以上 |
| データベース | MySQL 5.7 以上 / MariaDB 10.3 以上 |
| 拡張 | PDO、PDO_MySQL |
| Web サーバー | Apache / Nginx |

---

## インストール

### 1. ファイルをサーバーへ配置

リポジトリを ZIP でダウンロードし、公開ディレクトリへ展開します。

```
tako-cms/
├── cms/
├── index.php
├── post.php
└── ...
```

### 2. データベースを作成

ホスティングのコントロールパネルまたは phpMyAdmin で、空のデータベースを作成してください。

### 3. インストールウィザードを開く

ブラウザで以下にアクセスします。

```
https://あなたのドメイン/tako-cms/cms/setup.php
```

ウィザードの手順に従って入力するだけでインストールが完了します。

```
Step 1: 動作環境チェック（自動）
Step 2: DB 接続情報・サイト名を入力
Step 3: 管理者アカウントを作成
完了 → ログイン画面へ
```

### 4. 管理画面にログイン

```
https://あなたのドメイン/tako-cms/cms/login.php
```

---

## ローカル開発（MAMP）

```
URL: http://localhost:8888/tako-cms/cms/setup.php
DB ホスト: localhost
DB ユーザー: root
DB パスワード: root
```

---

## ディレクトリ構成

```
tako-cms/
├── cms/
│   ├── admin/             # 管理画面
│   │   ├── index.php      # 記事一覧
│   │   ├── post-new.php   # 記事新規作成
│   │   ├── post-edit.php  # 記事編集
│   │   ├── categories.php # カテゴリ管理
│   │   ├── skill.php      # スキル一覧
│   │   ├── skill-edit.php # スキル編集
│   │   └── upload-image.php # 画像アップロード（CKEditor 用）
│   ├── uploads/           # アップロード画像（Git 管理外）
│   ├── config.php         # DB・サイト設定（Git 管理外・自動生成）
│   ├── config.sample.php  # 設定テンプレート
│   ├── login.php
│   ├── logout.php
│   └── setup.php          # インストールウィザード
├── index.php              # 公開トップ（記事一覧）
├── post.php               # 公開記事詳細
├── .gitignore
├── CHANGELOG.md
└── README.md
```

---

## セキュリティ

- 全フォームに CSRF トークンを実装
- CKEditor 画像アップロードは `X-CSRF-Token` ヘッダーで検証
- ファイルアップロードは拡張子ではなく MIME タイプ（`finfo`）で検証
- パスワードは `password_hash()` でハッシュ化
- SQL インジェクション対策に PDO プリペアドステートメントを使用
- XSS 対策に `htmlspecialchars()` を使用

---

## ライセンス

[MIT License](LICENSE)
