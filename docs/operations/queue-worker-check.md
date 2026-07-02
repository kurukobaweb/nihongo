# Queue Worker 運用確認手順

T012-02 の確認対象は、Laravel Queue の database driver 前提で Queue Worker の起動、停止時の `jobs` 滞留、再開時の処理、`failed_jobs` の確認、失敗 Job の再実行または調査に必要な最小手順に限定する。

Redis / SQS 移行、Supervisor 本番設定、cron 本番設定、`job_batches` 追加、OI-003 / OI-008 / OI-021 の確定はこの手順の対象外とする。

## 前提

- `.env` と実 Secrets は commit しない。
- `QUEUE_CONNECTION=database` または未指定時の fallback が `database` であることを前提にする。
- `jobs` / `failed_jobs` の migration が適用済みであることを前提にする。
- 実DB確認ができない場合は、実値投入や Secrets 作成を行わず、未実行理由を記録する。
- CleanupTempFilesJob は既存の Queue 確認用 Job として参照できるが、仕様変更や Scheduler 頻度確定は行わない。

## 基本確認

```bash
php artisan route:list
php artisan queue:work --once --stop-when-empty
php artisan queue:failed
```

`queue:work --once --stop-when-empty` が exit 0 の場合、Worker コマンドの起動確認として扱う。ただし、`jobs` への投入と処理完了を確認するまでは、実DB上の Queue 処理確認完了とは扱わない。

## Worker 停止時の jobs 滞留確認

Worker を起動していない状態で、既存の安全な Queue Job を1件投入し、`jobs` に滞留することを確認する。

例:

```bash
php artisan tinker --execute="App\Jobs\CleanupTempFilesJob::dispatch(); echo DB::table('jobs')->count();"
```

確認観点:

- `jobs` 件数が投入前より増えること
- Worker を起動していない間は `jobs` に残ること
- CleanupTempFilesJob を使う場合、事前に cleanup 対象の submission 件数を確認し、想定外のファイル削除が起きない状態でのみ実行すること

cleanup 対象件数の確認例:

```bash
php artisan tinker --execute="echo Schema::hasTable('submissions') ? DB::table('submissions')->whereIn('status', ['completed', 'failed'])->whereNotNull('audio_path')->count() : 'NO_TABLE';"
```

## Worker 再開時の処理確認

`jobs` に滞留がある状態で Worker を起動する。

```bash
php artisan queue:work --once
```

または、空になるまで処理する。

```bash
php artisan queue:work --stop-when-empty
```

確認観点:

- 対象 Job が `DONE` になること
- 処理後に `jobs` 件数が減ること
- 失敗した場合は `queue:failed` とアプリケーションログを確認すること

## failed_jobs 確認

```bash
php artisan queue:failed
```

`failed_jobs` の件数確認例:

```bash
php artisan tinker --execute="echo DB::table('failed_jobs')->count();"
```

失敗 Job がある場合は、次を確認する。

- `queue:failed` に表示される UUID
- connection / queue / class
- 失敗時刻
- `storage/logs/` 配下のアプリケーションログ
- 対象 submission の状態と、重複処理しても安全な単位かどうか

## 失敗 Job の再実行または調査

再実行してよいと判断できる場合のみ、UUID を指定して再実行する。

```bash
php artisan queue:retry <failed-job-uuid>
php artisan queue:work --once
```

再実行してはいけない、または原因が不明な場合は、`queue:retry` を実行せず、`queue:failed` の表示、アプリケーションログ、対象データの状態を保全して調査する。

継続的に失敗する場合は、必要に応じて Queue Worker を一時停止し、一次対応の基本順序に従う。

## 未実行理由の記録例

実DB確認できない場合は、次のように理由を区別して記録する。

- `.env` 未作成
- DB接続実値未投入
- `pdo_pgsql` 未有効
- `pdo_sqlite` 未有効
- DB未起動
- migration未実行
- Docker daemonまたはローカル環境制約
- Queue対象Jobが存在しない
- その他のLaravel設定エラー

実 Secrets の投入、`.env` の commit、外部サービス接続、Redis / SQS 移行、OI-003 / OI-008 / OI-021 の判断は行わない。

