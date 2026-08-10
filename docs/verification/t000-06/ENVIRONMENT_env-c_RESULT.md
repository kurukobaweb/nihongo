# T000-06-03 env-c 実機計測結果

## 対象環境

- environment ID: `env-c`
- 端末: Android物理端末
- browser: Chrome stable
- 入力: Android内蔵マイク
- runtime commit SHA: `243f1a5eeac52086efef14b8a407e6cb67f5f774`
- 計測日: 2026-08-10

## 正式trialの選定

Android内蔵マイクで取得したattempt 3の9件だけを正式trialとした。

- 10秒: 1件
- 40秒: 1件
- 60秒: 1件
- 90秒: 1件
- 120秒: 5件
- valid: 9 / 9
- visibility change: 全件0
- 開始・終了visibility: 全件`visible`
- MIME type: 全件`audio/webm;codecs=opus`

次は正式9件から除外した。

- Bluetoothイヤフォンのマイクを使用したtrial
- 内蔵マイクとの切り分け用に取得したattempt 2のtrial
- environment IDを誤って`env-a`としたtrial
- visibility change等によりinvalidとなったtrial

除外trialは正式9件へ混入させず、local raw証跡側で保持する。

## 計測結果

最大値:

- `duration_method_difference_seconds`: `0.060274000000007`
  - trial: `env-c-p120-r01-a03`
- `total_overrun_seconds`: `0.00003900000000101`
  - trial: `env-c-p120-r05-a03`

平均値:

- `duration_method_difference_seconds`: `0.05987555555555622`
- `total_overrun_seconds`: `-0.03543111111111067`

正式9件はいずれも基準値`0.07秒`未満だった。Android内蔵マイクでは大きなtrial間変動、visibility変化、画面ロック、background移行、画面回転、通知・着信割込み、Blob保存失敗、MIME typeまたはcodec差を確認しなかった。

Bluetoothイヤフォン利用時に確認した大きな差は入力経路の切り分け対象とし、Android内蔵マイクの正式結果およびproduction共通technical marginの根拠値には含めない。

以上により、25 valid trialへの拡張条件には該当しないと判断し、標準9 valid trialで終了することをユーザー承認済みとする。

## 基準環境保護確認

基準環境の既存証跡は計測後も不変であることを確認した。

- JSONL records: `26`
- JSONL bytes: `31820`
- SHA-256: `47EA09A6DDEADA0883A73A4711BFB4C7855FA746E868B18A902361D51BFF3AF3`
- raw WebM: `0`
- temporary WAV: `0`

基準環境の既存trialおよびtracked sanitized CSVは変更していない。

## tracked証跡

正式9件の再計算可能な判断用数値は次へ保存した。

`docs/verification/t000-06/media-recorder-measurements.env-c.sanitized.csv`

sanitized CSVは正式9件のtrial identity、valid判定、visibility、MIME type、WebM/WAV duration、測定方式間差、total overrunを記録する。生timestampとBlobサイズを含む詳細値はlocal JSONLを正とし、tracked文書へ推測値を補わない。

## raw成果物

env-cのlocal JSONLおよびraw WebMは、T000-06-05の横断集計と証跡確認が完了するまで保持する。現時点では削除しない。

## 判定

- T000-06-03 env-c 実機計測: 完了
- 25 valid trial拡張: 不要
- production共通technical margin: 未確定
- 共通technical marginの最終確定: T000-06-05で実施
