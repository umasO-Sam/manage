# 部品調達管理システム — Xserver共用サーバー デプロイ手順

対象: Laravel 13 / PHP 8.3 / MySQL / Xserver 共用サーバー（シェアードプラン）

> **ステータス（2026-07-16時点）**: この手順書はまだ実行されていない。ローカル開発（SQLite）は完了しており、本番デプロイ待ち。

---

## 0. 前提の確認

- 契約プランでSSH接続が有効になっていること（Xserverのビジネス/スタンダード以上のプランで利用可能。サーバーパネル「SSH設定」で有効化）
- PHP 8.3以上が選択できること（サーバーパネル「PHP Ver.切替」）
- 公開URLは `manage.saito-koken.co.jp` （独自ドメイン配下のサブドメイン方式）とする

> **なぜこのドメイン/サブドメインか**: 同じXserverアカウントの初期ドメイン `saitokoken.xsrv.jp` では
> 既に別システム（`https://saitokoken.xsrv.jp/timecard-new/`）が稼働中で、かつ**初期ドメインはサブドメインを
> 追加できない**ことを確認済み（サーバーパネル上で不可）。一方、独自ドメイン `saito-koken.co.jp` では
> サブドメイン追加が可能なため、こちらに `manage` サブドメインを作成する。ドキュメントルートはドメイン/サブドメイン
> 単位の設定なので、`saitokoken.xsrv.jp`（timecard-new稼働中）には一切影響しない。

---

## 1. サーバー側の準備（サーバーパネル操作）

### 1-0. サブドメイン作成
「ドメイン」→「サブドメイン設定」で対象ドメインを `saito-koken.co.jp` に切り替えたうえで
`manage` を追加し、`manage.saito-koken.co.jp` を発行する。
（この時点でドキュメントルートは仮のもので構わない。1-4で本設定を行う）

### 1-1. MySQLデータベース作成
「MySQL設定」→「MySQL追加」で以下を作成し、**控えておく**:
- データベース名（例: `xserverアカウント名_manage`）
- MySQLユーザー名・パスワード
- 「アクセス権限」で対象データベースにユーザーを紐付け

### 1-2. PHPバージョン切替
「PHP Ver.切替」で対象ドメインを **PHP 8.3以上** に設定。

### 1-3. SSH設定
「SSH設定」→ 公開鍵を登録するか、パスワード認証を有効化。
接続情報（ホスト名・ポート・ユーザー名）を控える。

### 1-4. ドキュメントルートの変更（シンボリックリンク方式）
> **重要（実機確認済み）**: Xserverのサーバーパネルには「ドキュメントルート設定」「公開(アップロード)フォルダ設定」に
> 相当する機能が存在しない（全カテゴリを確認済み）。そのため任意パスへのドキュメントルート変更はできず、
> **シンボリックリンクで代替する**。

サブドメイン`manage.saito-koken.co.jp`を作成すると、Xserverが固定のドキュメントルートを自動生成する:

```
/home/saitokoken/saito-koken.co.jp/public_html/manage.saito-koken.co.jp/
```

（中に`index.html`、`default_page.png`という案内用ダミーファイルと、php.ini設定機能が使う`.user.ini`が自動生成されている）

Laravelは `public/` だけを公開し、それ以外（`.env`、`app/` 等）を外部から見えなくする必要があるため、
プロジェクト本体は `public_html` の外、`~/manage/` に配置し、上記の自動生成ディレクトリを
`~/manage/public` へのシンボリックリンクに置き換える。

```bash
# ダミーファイルを削除してディレクトリごと置き換え
rm -f ~/saito-koken.co.jp/public_html/manage.saito-koken.co.jp/index.html
rm -f ~/saito-koken.co.jp/public_html/manage.saito-koken.co.jp/default_page.png
rm -f ~/saito-koken.co.jp/public_html/manage.saito-koken.co.jp/.user.ini
rmdir ~/saito-koken.co.jp/public_html/manage.saito-koken.co.jp
ln -s ~/manage/public ~/saito-koken.co.jp/public_html/manage.saito-koken.co.jp
```

この時点では `~/manage` 本体（コード）がまだ無いためリンク切れの404になるが、
「2. コードの配置」を済ませれば解消する。

`.user.ini`削除後、`upload_max_filesize`・`memory_limit`等はコード配置後に「php.ini設定」パネルで
`manage.saito-koken.co.jp`向けに再設定すること（添付ファイル機能があるため）。

なお、この操作はサブドメイン単位（`~/saito-koken.co.jp/public_html/`配下の1ディレクトリのみ）なので、
初期ドメイン（`saitokoken.xsrv.jp`、`timecard-new`が稼働中）や`saito-koken.co.jp`本体サイトには一切影響しない。

---

## 2. コードの配置

### 方法A: Gitでデプロイ（推奨・今後の更新が楽）

1. GitHub等にプライベートリポジトリを作り、ローカルの `C:\Users\OSAMU\claude\manage` からpush
   ```powershell
   git remote add origin https://github.com/<自分のアカウント>/manage.git
   git push -u origin master
   ```
2. サーバーにSSH接続し、`~/manage` にclone
   ```bash
   ssh <ユーザー名>@<サーバー>.xserver.jp -p <ポート>
   git clone https://github.com/<自分のアカウント>/manage.git ~/manage
   cd ~/manage
   ```

### 方法B: SFTPで直接アップロード（GitHubを使わない場合）

WinSCP等のSFTPクライアントで `C:\Users\OSAMU\claude\manage` の中身を `~/manage` にアップロード。
**アップロード不要 / むしろ除外すべきもの**:
- `vendor/`（サーバー上で `composer install` する）
- `node_modules/`（サーバー上でビルドしないなら不要。ローカルでビルド済みの `public/build/` だけアップロードすればnpm自体不要）
- `.env`（ローカルの値をそのまま持ち込まない。サーバーで新規作成）
- `database/database.sqlite`（本番はMySQLを使うため不要）
- `storage/logs/*.log`、`storage/framework/cache/*`、`storage/framework/sessions/*`

---

## 3. サーバー上でのセットアップ（SSH接続後）

> **重要（実機確認済み・3点）**
> 1. SSHの`php`はシステムデフォルトのPHP 5.4。8.3を使うには`/usr/bin/php8.3`を明示すること
>    （利用可能なバージョン一覧は`/usr/bin/php8.*`で確認できる）。
> 2. システムの`composer`（`/usr/bin/composer`）はバージョン1.9.1でLaravel 13と非互換。
>    以下の手順で自分のホームディレクトリにComposer 2を個別インストールする。
> 3. サーバーにNode.jsが無い。フロントエンド資産は**ローカルでビルドしてアップロード**する
>    （`scp`で`public/build/`をtar転送するのが簡単）。

```bash
cd ~/manage

# 0. Composer 2を個別インストール（初回のみ）
mkdir -p ~/bin && cd ~/bin
/usr/bin/php8.3 -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
/usr/bin/php8.3 composer-setup.php --quiet
rm composer-setup.php
cd ~/manage

# 1. Composer依存関係（本番用、開発用パッケージは除く）
/usr/bin/php8.3 ~/bin/composer.phar install --no-dev --optimize-autoloader --no-interaction

# 2. .env を作成
cp .env.example .env
nano .env   # または vi .env
```

`.env` で変更する項目:

```ini
APP_NAME="部品調達管理システム"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://manage.saito-koken.co.jp

DB_CONNECTION=mysql
DB_HOST=<1-1のMySQL設定画面上部に表示されるホスト名。例: mysqlXXXX.xserver.jp>
DB_PORT=3306
DB_DATABASE=<1-1で作成したDB名>
DB_USERNAME=<1-1で作成したユーザー名>
DB_PASSWORD=<1-1で設定したパスワード>

# Xserverのメールアカウントをサーバーパネル「メールアカウント設定」で事前に作成し、
# そのSMTP情報を設定する。ホスト名はSSH接続に使うサーバーホスト名と同じ(例: sv8637.xserver.jp)。
# ポートは465(暗黙的TLS)を使う場合が多く、その場合 MAIL_SCHEME=smtps が必要
# (587/STARTTLSの場合は MAIL_SCHEME=null のままでよい)。このLaravelバージョンは
# MAIL_ENCRYPTION ではなく MAIL_SCHEME を使う点に注意(config/mail.phpで要確認)。
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=<契約サーバーのホスト名>.xserver.jp
MAIL_PORT=465
MAIL_USERNAME=<作成したメールアカウント>
MAIL_PASSWORD=<メールアカウントのパスワード>
MAIL_FROM_ADDRESS="<作成したメールアカウント>"
MAIL_FROM_NAME="${APP_NAME}"

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=sync   # 常駐ワーカーを置けないため必須
```

```bash
# 3. アプリケーションキー生成（本番用に新規発行。ローカルの値は使い回さない）
/usr/bin/php8.3 artisan key:generate

# 4. マイグレーション
/usr/bin/php8.3 artisan migrate --force

# 4-1. 初期データ投入（担当者・ワークフロー・注番の初期値）
# db:seed はダミーデータ生成に fakerphp/faker (dev依存) を使うため、--no-dev だけでは
# `Call to undefined function fake()` になる。一時的にdev依存を含めて実行し、直後に戻す。
/usr/bin/php8.3 ~/bin/composer.phar require --dev fakerphp/faker --no-interaction
/usr/bin/php8.3 artisan db:seed --force
git checkout -- composer.json composer.lock
/usr/bin/php8.3 ~/bin/composer.phar install --no-dev --optimize-autoloader --no-interaction

# 5. フロントエンド資産のビルド（ローカルで実行し、アップロード）
# ローカルのPowerShellで:
#   . C:\Users\OSAMU\claude\dev-tools-path.ps1
#   cd C:\Users\OSAMU\claude\manage; npm install; npm run build
# その後 public/build を tar.gz にしてscpでアップロードし、サーバー上で展開する:
#   tar czf build.tar.gz -C public build   (ローカル)
#   scp -i ~/.ssh/xserver_manage -P 10022 build.tar.gz saitokoken@saitokoken.xsrv.jp:~/manage/
#   ssh先で: cd ~/manage && tar xzf build.tar.gz -C public && rm build.tar.gz

# 6. パーミッション調整（Webサーバーが書き込めるように）
chmod -R 775 storage bootstrap/cache

# 7. 本番最適化（設定・ルート・ビューをキャッシュ）
/usr/bin/php8.3 artisan config:cache
/usr/bin/php8.3 artisan route:cache
/usr/bin/php8.3 artisan view:cache
```

### 3-1. 初期ログイン確認
シーダーで作成される初期アカウント（**要パスワード変更**）:
- ログインID: `admin`
- パスワード: `password`

ログイン後、必ずパスワードを変更するか、正式な資材管理担当者アカウントを作成して `admin` は使わないようにする。

---

## 4. Cron設定（保持期間バッチ）

> **重要（実機確認済み）**: SSHの`php`コマンドはシステムデフォルトのPHP 5.4を指しており、
> パネルの「PHP Ver.切替」設定（8.3.30）はWebサーバー用でCLIには反映されない。
> Cronでも明示的にPHP8.3のバイナリ（`/usr/bin/php8.3`）を指定すること。

> **重要（毎分実行にしない）**: Laravelの公式推奨は`schedule:run`の毎分実行だが、
> **Xserverは毎分Cronを推奨しておらず**、サーバー負荷が著しい場合はアカウント利用制限の
> 対象になり得ると明記している（Cron設定画面の警告文より）。そのため本プロジェクトでは
> **5分ごと**（`*/5 * * * *`）で運用する。現在のスケジュールタスクは
> `app:archive-completed-cards`(毎日02:00)と`app:purge-archived-cards`(毎日02:15)のみで、
> どちらも5の倍数の時刻なので5分間隔でも取りこぼしなく実行される。
> **今後`routes/console.php`にタスクを追加する際は、実行時刻を5分の倍数にすること**
> （例: `02:03`のような非5の倍数を指定すると最大4分の遅延または実行漏れが起きる）。

サーバーパネル「Cron設定」で以下を追加（**5分ごと**）:

```
/usr/bin/php8.3 /home/saitokoken/manage/artisan schedule:run >> /dev/null 2>&1
```
実行間隔設定: 毎日・毎時・**5分毎**

これが `app:archive-completed-cards`（毎日2:00）と `app:purge-archived-cards`（毎日2:15）を内部的に呼び出す（`routes/console.php` に登録済み）。

---

## 5. HTTPS化

> **実機確認済み**: `manage.saito-koken.co.jp`作成時に無料独自SSLが自動発行されており、
> 追加作業なしで`https://`が有効だった（`curl`で証明書検証つきで200を確認済み）。
> 反映されていない場合のみ、サーバーパネル「SSL設定」→ 対象ドメインで有効化する。

---

## 6. 動作確認チェックリスト

- [ ] `https://<ドメイン>/login` が開ける
- [ ] `admin` でログインできる → ログイン後パスワード変更 or 正式アカウント作成
- [ ] 購入部品手配・見積り依頼の両ボードが表示される
- [ ] 注番管理・担当者管理（資材管理担当者のみ）にアクセスできる
- [ ] 新規依頼を作成 → 資材管理担当者へのメールが実際に届く（`MAIL_MAILER=smtp` 反映確認）
- [ ] カードの移動・差し戻し・即時非表示・コメントが動作する
- [ ] 添付ファイルのアップロード・ダウンロードができる
- [ ] 履歴（アーカイブ）検索ができる
- [ ] `storage/logs/laravel.log` にエラーが出ていないか確認

---

## 7. 今後の更新手順（2回目以降のデプロイ）

Gitでデプロイした場合:

```bash
ssh -i ~/.ssh/xserver_manage -p 10022 saitokoken@saitokoken.xsrv.jp
cd ~/manage
git pull
/usr/bin/php8.3 ~/bin/composer.phar install --no-dev --optimize-autoloader --no-interaction
/usr/bin/php8.3 artisan migrate --force
# フロント変更があれば、ローカルでnpm run build → scpでpublic/buildを再アップロード
/usr/bin/php8.3 artisan config:cache
/usr/bin/php8.3 artisan route:cache
/usr/bin/php8.3 artisan view:cache
```

---

## 補足: ローカル(SQLite)と本番(MySQL)の違いについて

このプロジェクトはローカル開発をSQLiteで行っているが、Laravelのマイグレーションは
MySQL/SQLite双方で概ね互換性がある。念のため、本番投入前に一度
`php artisan migrate:fresh --seed` を **本番と同条件のMySQL** で試すことを推奨する
（可能であれば別のテスト用データベースで）。
