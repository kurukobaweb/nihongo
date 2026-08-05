# T000-06 MediaRecorder停止誤差計測helper手順

## 目的と範囲

このhelperは、T000-06「OI-030 MediaRecorder停止誤差確定」のために、10 / 40 / 60 / 90 / 120秒の各評価プロファイルを実ブラウザで1試行ずつ計測するlocal/testing限定ツールである。production録音UIとは分離し、Azure、submission、DBは使用しない。

今回はhelperの実装と自動テストまでを対象とする。実ブラウザ録音、25有効試行、technical marginの具体値確定、OI-030解消、本番判定の実装は行わない。

## 利用条件

- routeは`local`または`testing`環境でだけ登録される。production環境ではroute自体が存在しない。
- GET URL: `/verification/t000-06/media-recorder`
- preflight POST URL: `/verification/t000-06/media-recorder/trials/preflight`
- POST URL: `/verification/t000-06/media-recorder/trials`
- GET、preflight POST、保存POSTのすべてに`auth`と`verified` middlewareが必要である。
- 一般ユーザー向けnavigationからはリンクしない。
- ホスト上で`ffmpeg`と`ffprobe`が実行でき、LaravelからSymfony Processを利用できることが前提である。
- 25試行の自動連続実行機能はない。利用者が1回ずつ開始する。

## 実ブラウザ計測の正式なユーザー操作手順

### 各trial前の必須事前告知

CodeXはStart操作前に、ユーザーへ次をすべて明示する。

- 今回選択する評価profileの秒数
- Startを押すと録音処理へ入ること
- マイク許可済みの場合はStart直後に録音が始まること
- 指定時間中、実際に発話すること
- 画面に「録音終了」と表示されるまで発話すること
- 録音中はtabまたはwindowを切り替えないこと
- 自動停止後もserver resultが表示されるまで待つこと
- invalid結果でも独断で再試行しないこと

画面の「録音前の確認」と予定録音時間をユーザーが確認できる状態にし、CodeXはStart前で操作を停止する。ユーザーが使用マイクと発話内容を準備し、CodeXチャットへ「録音準備完了」と返信するまで、Start操作やマイク権限要求へ進まない。

### trial条件URLとStart前確認

正式trialは、次の4項目をqueryに含むURLで開く。

```text
/verification/t000-06/media-recorder?environment_id=env-a&profile_seconds=10&run_number=2&attempt_number=1
```

4項目の一部だけを指定したqueryや不正値はHTTP 422となる。queryなしの場合だけ従来のdefault（`env-a`、10秒、run 1、attempt 1）を使う。ページをreloadした場合、入力値とtrial ID previewはqueryから復元するが、固定状態は復元しない。Startは無効のままであり、再度preflightと条件固定が必要である。

Start前は次の順で確認する。

1. URL queryと画面入力値が正式trialの条件と一致することを確認する。
2. 画面の「入力中のtrial ID」を読み、run番号とattempt番号を含む全体を確認する。
3. 「trial条件を確認・固定」を押す。
4. preflight成功後の「今回固定したtrial ID」をユーザーが読み上げて確認する。
5. CodeXも固定trial IDを報告し、同一であることを確認する。
6. ユーザーの「録音準備完了」を待つ。
7. ユーザー自身がtrial ID付きStartを1回だけ押す。

条件固定時のpreflightはJSONLとWebMの重複を読み取り専用で確認する。Start時にも固定snapshotでpreflightを再実行し、成功するまで`getUserMedia()`を呼ばない。duplicateやstorage errorの場合、マイク取得・録音・保存へ進まず、run番号やattempt番号を自動変更しない。

### 承認境界

各trialの手順は次の順序に固定する。

```text
1. CodeXがtrial条件と録音時間を提示
2. CodeXがStart前で停止
3. ユーザーがマイクと発話内容を準備
4. ユーザーが「録音準備完了」と返信
5. ユーザー自身が固定trial ID付きStartを1回押す
6. Start直後から指定時間発話
7. 「録音終了」表示で発話を終了
8. server result表示まで待機
9. ユーザーが「計測完了」と返信
10. CodeXがraw成果物と計測値を確認
11. 次trialへ進む前にユーザー承認
```

録音中は画面の経過時間と残り時間を確認しながら発話を続ける。「録音終了」「音声を保存・解析しています」と表示されたら発話を終了し、画面操作を行わず完了表示とserver resultを待つ。valid／invalid／failedのいずれの場合も、次trialへ進むには新たなユーザー承認が必要である。

Start時preflightが成功してマイク取得へ進んだ時点で、その画面の録音開始権は消費される。permission拒否、MediaRecorder error、invalid、valid、保存エラーのいずれでも、同じ画面ではStartを再度有効にしない。確認・承認後、正式なqueryを持つ新しい画面で次の操作を行う。

### 操作上の禁止事項

- ユーザーの準備完了前にStartを押さない。
- CodeXはユーザーに無断でStartを押さない。
- 手動停止操作を行わない。
- Startを二重に押さない。
- 録音中にtabまたはwindowを切り替えない。
- 無断で再録音しない。
- attempt番号を無断で変更しない。
- invalid trialを無断で再試行しない。
- 複数trialを連続実行しない。
- duplicate時にrun番号またはattempt番号を自動変更しない。

## 録音条件とclock

production録音処理と同じく、`navigator.mediaDevices.getUserMedia({ audio: true })`を使用する。MIME typeは次の順に`MediaRecorder.isTypeSupported()`で選択し、対応候補がなければbrowser defaultを使用する。

1. `audio/webm;codecs=opus`
2. `audio/webm`
3. browser default

event listenerは`recorder.start()`より前に登録し、`recorder.start()`にはtimesliceを渡さない。`start` eventを録音開始基準とし、その瞬間の`performance.now()`をoriginとして`recording_started_ms = 0`を記録する。OS時刻、1秒表示タイマー、DB時刻は停止誤差計算に使わない。

`start` event後に選択profileの`setTimeout`を開始する。timeout callbackでは、入った直後に`stop_requested_ms`を記録し、`recorder.stop()`の直前に`recorder_stop_called_ms`を記録してから停止する。同じoriginから`last_dataavailable_ms`、`stop_event_ms`、Blob構築直後の`blob_completed_ms`を記録する。

画面の録音カウンターも`start` eventで開始し、同じ`recordingOrigin`から`performance.now()`で得た経過時間だけを表示する。表示更新は200ms間隔で、経過秒はprofile秒を上限、残り秒は0を下限とする。このカウンターはユーザー向け表示専用であり、自動停止用`setTimeout`、raw timestamp、duration解析、停止誤差計算には使用しない。自動停止要求後は表示更新を解除し、録音中表示を「録音終了」「音声を保存・解析しています」へ切り替える。

録音開始時と終了時の`document.visibilityState`、録音中の`visibilitychange`回数も記録する。開始・終了が`visible`でない場合、または録音中にvisibilityが変化した場合、そのtrialはinvalidとなる。

## trial ID

サーバーは検証済み入力から次の形式でtrial IDを生成する。

```text
{environment_id}-p{profile3桁}-r{run2桁}-a{attempt2桁}
```

例: `env-a-p060-r01-a01`

同じtrial IDのJSONL recordまたはWebMが既に存在する場合はHTTP 409 `duplicate_trial_id`とし、既存record・音声を上書きしない。

画面は入力値から生成したtrial ID previewをStart前から常時表示する。「trial条件を確認・固定」のpreflight成功後は、4項目とtrial IDを変更不能なsnapshotとして保持し、録音metadata、自動停止profile、UIカウンターはこのsnapshotだけを参照する。editable formを録音metadataの生成元にしない。URLは固定snapshotのqueryへfull reloadなしで正規化する。

preflight後も、保存POSTにおける重複確認を最終的な正本防御として維持する。preflightと保存の間には競合の余地があるため、保存時にHTTP 409となる可能性は残る。その場合も再録音せず、ユーザー承認へ戻る。JSONLの不正、読取不能、shared lock失敗はavailableと楽観判断せず、sanitizedなHTTP 500 `storage_failed`として停止する。preflightはdirectory、JSONL、WebM、WAVを作成せず、Analyzer、ffmpeg、ffprobe、DBを呼び出さない。

## raw保存先

正規base pathは`storage_path('app/local/t000-06')`である。Feature testだけはconfigで`storage/framework/testing`配下へ差し替え、正規保存先を汚さない。

```text
storage/app/local/t000-06/
├─ raw-audio/{trial_id}.webm
├─ raw/measurements.jsonl
└─ temporary/{trial_id}.wav
```

- 元WebMはffprobe正本durationの再計算用に保持する。
- temporary WAVは解析成功・失敗にかかわらずAnalyzerの`finally`で削除する。
- `storage/app/.gitignore`により`storage/app/local`はGit管理外である。raw音声、JSONL、temporary WAVをstage・commitしない。
- raw音声はT000-06の最終承認後、完了記録前の別ステップで削除する。今回のhelperは削除しない。
- JSONLはUTF-8、1行1trialで、排他lock下に追記する。user ID、氏名、メール、session/submission ID、transcript、発話内容、Secrets、絶対パス、ffmpeg/ffprobe command全文は保存しない。

## duration解析と計算式

元WebMの正本値は、ffprobeの`format.duration`から得る`webm_duration_seconds`である。numeric、finite、0より大きい値だけを受理する。

照合用WAVはffmpegでPCM signed 16-bit little-endian、16000Hz、monoへ変換する。WAVのffprobe結果から`duration_ts`と分数形式の`time_base`を取得し、fallbackせず次を計算する。

```text
wav_duration_seconds = duration_ts × time_base
duration_method_difference_seconds = abs(webm_duration_seconds - wav_duration_seconds)

stop_request_delay_seconds = stop_requested_ms / 1000 - profile_seconds
stop_call_delay_seconds = (recorder_stop_called_ms - stop_requested_ms) / 1000
recorder_tail_seconds = webm_duration_seconds - stop_requested_ms / 1000
total_overrun_seconds = webm_duration_seconds - profile_seconds
```

計算不能な値は`null`とする。再集計可能な精度を保持し、このhelperでは0.01秒切り上げやtechnical margin確定を行わない。

## valid / invalid判定

サーバーはクライアント申告のvalid値を受け付けず、音声・timestamp・visibility・duration解析結果から判定する。複数原因がある場合は次の優先順で、最初の理由をsnake_caseの`invalid_reason`へ保存する。

1. `microphone_permission_denied`
2. `media_recorder_unsupported`
3. `media_recorder_error`
4. `blob_empty`
5. `blob_missing`
6. `storage_failed`
7. `timestamp_missing`
8. `timestamp_order_invalid`
9. `tab_visibility_changed`
10. `ffprobe_failed`
11. `ffmpeg_failed`
12. `wav_duration_failed`

HTTP 409の`duplicate_trial_id`は既存trialを変更せず拒否するため、新しいJSONL行は追加しない。入力schema自体が不正な場合もHTTP 422とし、trial IDを安全に生成できないためraw trialにはしない。

valid trialは、MediaRecorder errorなし、全必須timestampあり・順序正常、0より大きい保存済みBlob、開始終了ともvisible、visibilitychange 0、許可profile、WebM/WAV両duration取得成功、重複なしをすべて満たす必要がある。

## 後続作業との境界

technical marginはまだ未確定である。実測後は全profileの有効試行における最大`total_overrun_seconds`と、測定方式間の最大差を判断材料とし、根拠値を下回らないよう0.01秒単位で切り上げるが、このhelperでは集計・確定しない。

将来の本番pre-Azure判定式は`D <= P + M`であり、Dは一時保存された元WebMのffprobe duration、Pはsubmissionへ固定保存される`evaluation_profile_seconds`、MはT000-06で確定する共通technical marginである。この判定、submission/DB保存、Azure送信制御、本番録音UI反映は後続実装対象である。
