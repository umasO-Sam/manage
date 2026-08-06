# 運用ランブック — 不具合対応・改善作業をすぐ再開するために

本番稼働: <https://manage.saito-koken.co.jp>（利用開始 2026-08-07）
デプロイ手順の詳細は [DEPLOY.md](DEPLOY.md) 7章。ここは**日々の対応で見る場所だけ**をまとめる。

---

## 1. 朝いちの健康チェック（3分）

```bash
# 本番の状態・当日のエラー件数・稼働コミット
ssh -i ~/.ssh/xserver_manage -p 10022 saitokoken@saitokoken.xsrv.jp \
  'cd ~/manage && ([ -f storage/framework/down ] && echo メンテナンス中 || echo 公開中) \
   && echo "本日のERROR: $(grep -c "^\[$(date +%Y-%m-%d).*ERROR" storage/logs/laravel.log || echo 0)件" \
   && git log --oneline -1'
```

エラーが出ていたら中身を見る（`userId` で誰の操作かが分かる）:

```bash
ssh -i ~/.ssh/xserver_manage -p 10022 saitokoken@saitokoken.xsrv.jp \
  'cd ~/manage && grep -n "^\[$(date +%Y-%m-%d).*ERROR" storage/logs/laravel.log | tail -5 | cut -c1-400'
```

> ログは `storage/logs/laravel.log` の単一ファイル。肥大化してきたら日次ローテーションを検討する。

---

## 2. 開発環境

```bash
cd C:/Users/OSAMU/claude/manage
php artisan serve --port=8123      # http://127.0.0.1:8123
```

- DBは `database/database.sqlite`（本番はMySQL）
- **権限切替（開発用）**: 右上のユーザーメニューから、自分のロールと各フラグ（上長・日報管理者・役員・資金管理者・administrator）をその場で変更できる。本番ではルートごと存在しない
- テスト: `php artisan test`（現在 **484件パス**）。修正したら必ず通してからコミットする

---

## 3. 本番反映

**前提**: ローカルでテストが全件通っていること。`main` にコミット済みであること。

```bash
cd C:/Users/OSAMU/claude/manage
npm run build && git push origin main

# フロントは公開ディレクトリがgitignoreのため別途送る
tar czf build.tar.gz -C public build
scp -i ~/.ssh/xserver_manage -P 10022 build.tar.gz saitokoken@saitokoken.xsrv.jp:~/manage/
rm -f build.tar.gz

# 本番の作業ツリーは常に origin/main と同一にする（本番側で編集はしない前提）。
# git pull は作業ツリーに差分があると止まるため、reset --hard で確実に合わせる。
ssh -i ~/.ssh/xserver_manage -p 10022 saitokoken@saitokoken.xsrv.jp 'cd ~/manage \
  && /usr/bin/php8.3 artisan down --render="errors::503" \
  && git fetch origin && git reset --hard origin/main \
  && tar xzf build.tar.gz -C public && rm build.tar.gz \
  && /usr/bin/php8.3 artisan migrate --force \
  && /usr/bin/php8.3 artisan config:cache && /usr/bin/php8.3 artisan route:cache && /usr/bin/php8.3 artisan view:cache \
  && /usr/bin/php8.3 artisan up && git log --oneline -1'
```

反映後は必ず**1章の健康チェック**を実行する。

### やってはいけないこと

- **`composer install` を実行しない**。本番のcomposerは1.9.1で、Laravel 13が要求する
  `composer-runtime-api ^2.2` を満たせず落ちる。依存を変更した場合はローカルで
  `vendor/` を作って転送する（今のところ依存の変更は発生していない）
- キャッシュを消したまま放置しない。`config:cache` 済みの状態が正
- 静的ファイル（ファビコン等）はサーバー側にもキャッシュが残る。差し替えたら
  HTMLの `?v=` を上げる。バージョンなしのURLはしばらく古い内容を返すことがある
- **本番のIDレコードを勝手に変更しない**。権限・氏名などの変更は都度確認を取る

### 切り戻し

```bash
ssh -i ~/.ssh/xserver_manage -p 10022 saitokoken@saitokoken.xsrv.jp 'cd ~/manage \
  && /usr/bin/php8.3 artisan down && git reset --hard <戻したいコミット> \
  && /usr/bin/php8.3 artisan config:cache && /usr/bin/php8.3 artisan view:cache \
  && /usr/bin/php8.3 artisan up'
```

マイグレーションを伴う変更を戻す場合は、**先に**`migrate:rollback --step=N --force` を実行する。
データを消す方向のロールバックは影響を確認してから。

---

## 4. 権限の全体像（不具合の切り分けで最初に見る）

ロールは3つ。上に重ねる**フラグ**で権限が増える。

| ロール | 主な範囲 |
|---|---|
| 経理資材担当 | カード移動、仕入管理の全機能、担当者管理 |
| 営業担当 | 各ボード、仕入管理の検索・原価計算、物件管理 |
| 一般社員 | 購入手配・見積依頼ボード、履歴、自分の勤怠 |

| フラグ | 効果 |
|---|---|
| 上長 | 他人の勤怠・原価の閲覧、申請承認、作業日報一覧・確認の閲覧 |
| 日報管理者 | 作業日報の**確認・差し戻し**と未確認バッジ（経理資材担当に付ける） |
| 名簿に表示しない | 各画面の担当者リストから除外（ＩＤ管理には残る） |
| 役員 | 物件管理ボード、原価、役員フラグの付与 |
| 資金管理者 | 取引先一覧、入金済の非表示。経理資材担当と同等の扱い |
| administrator | すべての機能。administratorのみが付与できる |

付与のはしご: 経理資材担当 ＜ 役員 ＜ 資金管理者 ＜ administrator。
**自分より上の権限のアカウントは編集・削除できない**（IDとパスワードが見えるため）。

---

## 5. 本番データの現況（2026-08-06 時点）

| 対象 | 件数 |
|---|---|
| 担当者ID | 32 |
| 注番マスタ | 37（全件プルダウン表示） |
| 受注ヘッダ | 1,743（合計 ¥17,362,901,931） |
| 仕入明細 | 242,030 |
| 調達ボードのカード | 51 |
| 物件カード | 0（運用開始待ち） |
| 作業日報 | 1（運用開始待ち） |
| 見積台帳（過去注番） | 9,196 |

日報管理者: 管理アカウント／瀧上祥子／水上留美子／柴田拓弥／斉藤央奈（+ administrator の斉藤修）

---

## 6. 自動実行（cron）

`*/5 * * * * php8.3 artisan schedule:run` が設定済み。

| 時刻 | 処理 |
|---|---|
| 2:00 | `app:archive-completed-cards` 完了カードを履歴へ |
| 2:15 | `app:purge-archived-cards` 保存期間を過ぎた履歴を削除 |
| 2:30 | `app:prune-operation-logs` 操作ログの整理 |

物件管理ボードは `retention_days = null` のため、この自動処理の対象外
（入金済でも人が非表示にするまで残す）。

---

## 7. 積み残し

- **テストアカウント（test）・管理アカウント（admin）の「名簿に表示しない」が未設定**。
  既存レコードの変更のため、指示待ち
- **新サーバー簡単移行**（Xserverの案内分）。休日出勤のない日に別途計画する。
  現環境は sv8637
- ログの日次ローテーション未設定

---

## 8. 利用開始後に出やすい問い合わせと確認先

| 症状 | 最初に見る場所 |
|---|---|
| メニューに項目が出ない | 4章の権限表。ロールとフラグの組み合わせを確認 |
| 作業日報確認で確認ボタンが出ない | 日報管理者フラグ。閲覧だけなら上長・役員でも可 |
| 調達ボードのバッジが減らない | 経理資材担当・administratorは**全カード**を数える仕様。「自分の依頼以外を既読にする」で片付ける |
| 注番がプルダウンに出ない | 注番管理の「プルダウン」チェック。各画面の「非表示の注番も表示する」で一時的に選べる |
| 打刻と日報の乖離警告が出ない | `timecard` 接続とSIDの一致（SIDを正とする） |
| 画面が真っ白 | 1章のエラーログ。`view:cache` の作り直しで直ることが多い |
