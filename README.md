# ミニSNS セットアップガイド

クローズドSNSの設置・設定手順です。

---

## 📁 ファイル構成(色々追加する前の情報なので色々ファイルないです)

```
minisns/
├── index.php           ← タイムライン（トップページ）
├── login.php           ← ログイン
├── register.php        ← アカウント登録
├── profile.php         ← プロフィール
├── like.php            ← いいねAPI
├── comment.php         ← コメントAPI
├── follow.php          ← フォローAPI
├── api.php             ← フィンガープリントAPI
├── logout.php          ← ログアウト
├── setup_admin.php     ← 管理者初期設定（使用後削除！）
├── schema.sql          ← DBスキーマ
├── .htaccess           ← Apacheセキュリティ設定
├── includes/
│   ├── db.php          ← DB接続設定
│   ├── auth.php        ← 認証ヘルパー
│   └── functions.php   ← ユーティリティ関数
└── assets/
    ├── style.css       ← スタイルシート
    └── script.js       ← フロントエンドJS
```

---

## 🚀 セットアップ手順

### 1. ファイルをサーバーにアップロード

FTP等でこのフォルダの中身をWebサーバーの公開ディレクトリにアップロードしてください。

---

### 2. データベース作成

MySQL でデータベースと権限を設定します。

```sql
CREATE DATABASE minisns CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'minisns_user'@'localhost' IDENTIFIED BY 'パスワード';
GRANT ALL PRIVILEGES ON minisns.* TO 'minisns_user'@'localhost';
FLUSH PRIVILEGES;
```
### 3. DB接続設定

`includes/db.php` を編集して接続情報を設定してください：

```php
define('DB_HOST', 'localhost');      // ホスト
define('DB_NAME', 'minisns');        // データベース名
define('DB_USER', 'minisns_user');   // ユーザー名（変更）
define('DB_PASS', 'パスワード');     // パスワード（変更）
```

---





### 4. 管理者アカウント作成

ブラウザで以下にアクセスします：

```
https://あなたのドメイン/setup_admin.php
```

パスワードとIDを入力して **アカウントを作成** します。
ついでにDBのテーブルも展開されます

> ⚠️ **使用後は必ず `setup_admin.php` を削除してください！**

---

### 5. 動作確認

1. `index.php` にアクセスしてトップページが表示されるか確認
2. `register.php` でテストアカウントを作成
3. ログイン・投稿・いいね・フォローが動作するか確認

---

## 🔒 セキュリティチェックリスト

- [ ] `setup_admin.php` を削除した
- [ ] `includes/db.php` のパスワードを設定した
- [ ] `.htaccess` がサーバーで有効になっている（Apache）
- [ ] HTTPS が有効になっている
- [ ] `schema.sql` をWebからアクセスできない場所に移動した
- [ ] PHPのエラー表示をオフにした（本番環境）

---

## ⚙️ 設定のカスタマイズ

### 投稿の文字数制限を変更する

`index.php` の `280` と `assets/script.js` の `280` を変更：

```php
// index.php
} elseif (mb_strlen($content) > 500) {  // 500文字に変更
```

```js
// script.js
const max = 500;  // 500文字に変更
```

### FingerprintJSのAPIキーを変更する

全PHPファイル・JSファイル内の以下のURLのAPIキー部分を変更：

```html
<script src="https://fpjscdn.net/v3/ogWCYmBVDHhTWcLJYmq5/iife.min.js"></script>
```

`ogWCYmBVDHhTWcLJYmq5` の部分を [FingerprintJS公式サイト](https://fingerprint.com/) で取得した自分のAPIキーに変更してください。

---

## 🛠️ 要件

- PHP 8.0以上
- MySQL 5.7以上 / MariaDB 10.3以上
- PDO拡張（php-pdo, php-mysql）
- Apache（mod_rewrite有効）または Nginx
- HTTPS推奨

---

## ⚠️ 既知の制限事項

- **フィンガープリント識別は完全ではありません**
  - ブラウザのプライベートモード、VPN、別ブラウザでは回避できます
  - これは**抑止力**としての機能です
  - 完全な1端末1アカウント制御は技術的に不可能です



---

## 📌 Nginxの場合の設定例

```nginx
server {
    listen 443 ssl;
    server_name your-domain.com;

    root /var/www/minisns;
    index index.php;

    # includesへの直接アクセス禁止
    location /includes/ {
        deny all;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```
