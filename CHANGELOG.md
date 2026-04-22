# Changelog

このプロジェクトのすべての変更はこのファイルに記録されます。  
形式は [Keep a Changelog](https://keepachangelog.com/ja/1.0.0/) に準拠しています。

---

## [Unreleased]

### Added

- パスワード再設定メール機能
  - `cms/forgot.php` — メールアドレス入力 → ユーザー名 + 再設定リンクをメール送信
  - `cms/reset.php` — トークン検証 → 新パスワード設定（有効期限1時間）
  - `password_resets` テーブルを初回アクセス時に自動作成（マイグレーション不要）
  - `cms/login.php` に「パスワードをお忘れの方」リンクを追加
- SQLite への移行（ファイルを置くだけでインストール完了）
  - MySQL 依存を完全に排除
  - `cms/setup.php` を 2 ステップに簡略化（DB 設定ステップ削除）
  - `cms/database/` に SQLite ファイルを自動生成
  - `cms/database/.htaccess` で DB ファイルへの Web アクセスを遮断

---

## [0.1.0] - 2026-04-22

### Added

- ブラウザベースのインストールウィザード（`cms/setup.php`）
  - Step 1: 動作環境チェック（PHP バージョン・PDO・書き込み権限）
  - Step 2: DB 接続情報・サイト名入力 → `config.php` 自動生成・テーブル自動作成
  - Step 3: 管理者アカウント作成
  - インストール済み検知（二重インストール防止）
- `cms/config.sample.php`：配布用設定テンプレート
- `.gitignore`：`config.php`・`uploads/` を除外
- `cms/uploads/.gitkeep`：空フォルダを Git に含めるため追加
- CSRF 対策（全管理画面フォーム）
  - `csrf_token()` / `csrf_field()` / `csrf_verify()` を `config.php` に追加
  - CKEditor 画像アップロード（XHR）は `X-CSRF-Token` ヘッダーで検証
- ファイルアップロードの MIME タイプ検証（`finfo` による実体チェック）
  - `validate_image_upload()` を `config.php` に追加し全アップロード箇所で使用
- 公開フロントエンドページ
  - `index.php`：公開済み記事のカードグリッド一覧（サムネイル・タグ・カテゴリ表示）
  - `post.php`：記事詳細（セクション本文・外部リンク・パンくず・404 処理）
  - 両ページに OGP / SEO メタタグを追加
- `SITE_NAME` / `SITE_DESCRIPTION` 定数をサイト設定に追加

### Fixed

- `cms/setup.php` の `post_sections` テーブル定義で `heading` となっていたカラム名を `title` に修正

### Existing features（初回コミット時点）

- 管理者ログイン・ログアウト
- 記事の作成・編集・削除・並び替え
- WYSIWYG エディタ（CKEditor 5）
- カテゴリ管理
- スキル管理
- 画像アップロード（サムネイル・CKEditor 本文内）
- 下書き / 公開 ステータス管理
