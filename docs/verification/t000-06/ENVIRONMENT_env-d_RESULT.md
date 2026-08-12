# T000-06-04 env-d 実機計測結果

## 対象環境

- environment ID: `env-d`
- 端末: iPhone物理端末
- browser: Safari
- 入力: iPhone内蔵マイク
- runtime commit SHA: `874ad87593c160a4970f868360ec4c71c0c008cb`
- 計測日: 2026-08-10 / 2026-08-12

env-a / env-b / env-cの正式計測はcommit `243f1a5eeac52086efef14b8a407e6cb67f5f774`で実施した。env-dではSafari生成WebMのduration互換対応が必要となったため、ユーザー承認の上でPR #66の修正を適用したcommit `874ad87593c160a4970f868360ec4c71c0c008cb`を正式計測runtimeとした。

PR #66では、既存の数値`format.duration`を優先する経路を維持し、`format.duration`を取得できないWebMだけffprobe packetの`pts_time + duration_time`最大値からWebM durationを算出するfallbackを追加した。したがってChrome系の既存duration取得経路は変更していない。

## Safari固有の事前確認

正式trial開始前に次を確認した。

- 10秒 / 40秒のattempt 1はSafariのマイク権限が許可されておらず、`NotAllowedError` / `microphone_permission_denied`となったため正式trialから除外した。音声は保存されていない。
- マイク権限許可後の10秒attempt 2はWebM/OpusのBlob生成に成功したが、ffprobeの`format.duration`が`N/A`となり既存analyzerで`ffprobe_failed`となったため正式trialから除外した。
- attempt 2のWebMは手動ffprobeでOpus音声として読み取り可能であり、packet timestampからdurationを再構成できることを確認した。
- 上記を受けてPR #66のSafari互換fallbackを追加・適用し、以後のattempt 3を正式trialとした。

これらはSafari互換性の切り分け記録であり、正式9件のtechnical margin集計には含めない。

## 正式trialの選定

Safari互換修正後に取得したattempt 3の9件だけを正式trialとした。

- 10秒: 1件
- 40秒: 5件
- 60秒: 1件
- 90秒: 1件
- 120秒: 1件
- valid: 9 / 9
- visibility change: 全件0
- 開始・終了visibility: 全件`visible`
- requested MIME type: 全件`audio/webm;codecs=opus`
- actual MIME type: 全件`audio/webm; codecs=opus`
- codec: Opus

actual MIME typeはChrome系の`audio/webm;codecs=opus`と空白表記が異なるが、媒体形式とcodecは同一である。

5 profile screeningでは40秒run 1の`total_overrun_seconds`が正式候補中の最大根拠値となったため、40秒を追加4件測定して標準9 valid trialとした。

## 計測結果

最大値:

- `duration_method_difference_seconds`: `0.0075000000000074`
  - trial: `env-d-p090-r01-a03` / `env-d-p120-r01-a03`（同値）
- `total_overrun_seconds`: `0.023000000000003`
  - trial: `env-d-p040-r01-a03`

平均値:

- `duration_method_difference_seconds`: `0.007444444444445055`
- `total_overrun_seconds`: `0.002555555555556113`

正式9件はいずれも基準値`0.07秒`未満で、全件valid、visibility change 0だった。40秒の追加4件も`duration_method_difference_seconds`が約`0.0075秒`、`total_overrun_seconds`が`0秒`で安定した。

Safariでは`format.duration=N/A`となるWebMが存在する環境固有挙動を確認したが、互換fallback適用後の正式trialではmargin根拠値が安定し、共通marginを超える兆候を確認しなかった。MIME表記差は空白のみでcodecは同じOpusである。以上を踏まえ、25 valid trialへ拡張せず標準9 valid trialで終了することをユーザー承認済みとする。

## 基準環境保護確認

基準環境の既存証跡はenv-d計測後も不変であることを確認した。

- JSONL records: `26`
- JSONL bytes: `31820`
- SHA-256: `47EA09A6DDEADA0883A73A4711BFB4C7855FA746E868B18A902361D51BFF3AF3`
- raw WebM: `0`
- temporary WAV: `0`

基準環境の既存trialおよびtracked `docs/verification/t000-06/media-recorder-measurements.sanitized.csv` は変更していない。

## tracked証跡

正式9件の再計算可能な判断用数値は次へ保存した。

`docs/verification/t000-06/media-recorder-measurements.env-d.sanitized.csv`

sanitized CSVは正式9件のtrial identity、valid判定、visibility、MIME type、Blobサイズ、WebM/WAV duration、停止時刻系列、測定方式間差、total overrunを記録する。raw音声、実URL、IPアドレス、Secrets、絶対パスはtracked証跡へ含めない。

## raw成果物

env-dのlocal JSONLおよびraw WebMは、T000-06-05の横断集計と証跡確認が完了するまで保持する。現時点では削除しない。

## 判定

- T000-06-04 env-d 実機計測: 完了
- Safari WebM duration互換対応: PR #66で適用済み
- 25 valid trial拡張: 不要
- production共通technical margin: 未確定
- 共通technical marginの最終確定: T000-06-05で実施
