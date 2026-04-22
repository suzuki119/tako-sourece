# Tako CMS — 開発ロードマップ

## 進捗サマリー

| フェーズ | 内容 | 状態 |
|---------|------|------|
| Phase 0 | 管理画面の基本機能（既存） | ✅ 完了 |
| Phase 1 | インストールウィザード | ✅ 完了 |
| Phase 2 | セキュリティ強化 | ✅ 完了 |
| Phase 3 | フロントエンド（公開ページ） | ✅ 完了 |
| Phase 4 | オープンソース整備 | 🔲 未着手 |

---

## Phase 0 — 管理画面の基本機能（既存）

完了済み。詳細は `cms/CMS_DESIGN.md` を参照。

| 機能 | ファイル |
|------|---------|
| DB接続・設定 | `cms/config.php` |
| ログイン・ログアウト | `cms/login.php` / `cms/logout.php` |
| 記事一覧 | `cms/admin/index.php` |
| 記事 新規作成・編集・削除 | `cms/admin/post-new.php` / `post-edit.php` |
| カテゴリ管理 | `cms/admin/categories.php` |
| スキル管理 | `cms/admin/skill.php` / `skill-edit.php` |
| 画像アップロード | `cms/admin/upload-image.php` |

---

## Phase 1 — インストールウィザード

**目標:** ファイルをサーバーに置いてブラウザを開くだけでセットアップ完了（WordPress方式）

### 完了タスク

#### 2026-04-22

- [x] `cms/setup.php` をウィザード形式に全面書き換え
  - Step 1: 動作環境チェック（PHP バージョン・PDO・書き込み権限）
  - Step 2: DB 接続フォーム → 接続テスト → `config.php` 自動生成 → テーブル自動作成
  - Step 3: 管理者アカウント作成
  - Step done: 完了ページ（セキュリティ注意書き付き）
  - インストール済み検知（管理者が存在すれば `admin/index.php` へリダイレクト）
- [x] `cms/config.sample.php` を作成（Git に含めるテンプレート）
- [x] `.gitignore` を追加（`config.php`・`uploads/` 除外、`.gitkeep` 維持）
- [x] `cms/uploads/.gitkeep` を追加（Git に空フォルダを含める）

### テーブル定義（自動生成）

| テーブル | 主なカラム |
|---------|-----------|
| `users` | id, username, password, email |
| `posts` | id, title, thumbnail, status, author_id, period, type, external_url, tags, sort_order |
| `categories` | id, name, slug |
| `post_categories` | post_id, category_id（中間テーブル） |
| `post_sections` | id, post_id, heading, body, image_url, sort_order |
| `skill` | id, title, period, body, image_url, category |

---

## Phase 2 — セキュリティ強化

### 完了タスク

#### 2026-04-22

- [x] `config.php` / `config.sample.php` にセキュリティヘルパーを追加
  - `csrf_token()` — セッションにトークンを生成・保持
  - `csrf_field()` — フォームに埋め込む hidden フィールドを出力
  - `csrf_verify()` — POST の CSRF トークンを検証（不正なら 403）
  - `csrf_verify_header()` — XHR リクエストの `X-CSRF-Token` ヘッダーを検証
  - `validate_image_upload()` — finfo でファイル実体の MIME を確認（拡張子偽装対策）
- [x] CSRF 対策を全フォームに適用
  - `admin/index.php` — 記事削除・並び替えフォーム
  - `admin/post-new.php` — 記事新規作成フォーム
  - `admin/post-edit.php` — 記事編集フォーム
  - `admin/categories.php` — カテゴリ追加・削除フォーム
  - `admin/skill.php` — スキル削除フォーム
  - `admin/skill-edit.php` — スキル新規作成・編集フォーム
- [x] CKEditor 画像アップロード（XHR）に CSRF ヘッダー検証を追加
  - `post-edit.php` の simpleUpload に `X-CSRF-Token` ヘッダーを設定
  - `admin/upload-image.php` で `csrf_verify_header()` を呼び出して検証
- [x] 全アップロード箇所を `validate_image_upload()` に統一（MIME 実体チェック）
  - `admin/post-new.php` / `post-edit.php` / `skill-edit.php` / `upload-image.php`

---

## Phase 3 — フロントエンド・公開ページ

### 完了タスク

#### 2026-04-22

- [x] `index.php`（記事一覧 — カードグリッド、サムネイル・タグ・カテゴリ表示）
- [x] `post.php`（記事詳細 — セクション本文・外部リンク・パンくず・404処理）
- [x] OGP / SEO メタタグを両ページに追加
- [x] CKEditor 出力 HTML のフロント用スタイル（テーブル・グリッド・画像）
- [x] `SITE_NAME` / `SITE_DESCRIPTION` 定数を追加し setup.php の Step 2 フォームで設定可能に
- [x] `setup.php` の post_sections テーブル定義の `heading` → `title` バグを修正

### 残タスク

- [ ] `.htaccess` によるパーマリンク（`/posts/slug` 形式）— 任意
- [ ] 空の `index.html` を削除（Apache が `index.php` より優先する場合の対処）

---

## Phase 4 — オープンソース整備（未着手）

- [ ] `README.md` にインストール手順・スクリーンショットを追加
- [ ] ライセンスファイル（MIT 推奨）を追加
- [ ] `CHANGELOG.md` を追加
