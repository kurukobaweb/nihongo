# T000-06-02 env-b 実機計測結果

## 対象環境

- environment ID: `env-b`
- 端末: Windows 物理PC
- browser: Chrome stable
- runtime commit SHA: `243f1a5eeac52086efef14b8a407e6cb67f5f774`
- 計測日: 2026-08-10

## 計測結果

標準9 valid trialを実施した。

- 10秒: 1件
- 40秒: 1件
- 60秒: 1件
- 90秒: 5件
- 120秒: 1件
- valid: 9 / 9
- visibility change: 全件 0
- MIME type: 全件 `audio/webm;codecs=opus`

最大値:

- `duration_method_difference_seconds`: `0.060016`
  - trial: `env-b-p090-r01-a01`
- `total_overrun_seconds`: `0.0001049999999978`
  - trial: `env-b-p040-r01-a01`

基準値 `0.07秒` を超えるtrialはなかった。

90秒profileの追加4件でも大きなtrial間変動は確認されず、valid / invalidの不安定化、visibility変化、MIME type / codec差も確認されなかった。

以上により、env-bは25 valid trialへの拡張条件に該当しないと判断し、標準9 valid trialで終了することをユーザー承認済みとする。

## 基準環境保護確認

基準環境 env-a の既存証跡は計測後も不変であることを確認した。

- JSONL records: `26`
- JSONL bytes: `31820`
- SHA-256: `47EA09A6DDEADA0883A73A4711BFB4C7855FA746E868B18A902361D51BFF3AF3`
- raw WebM: `0`
- temporary WAV: `0`

基準環境の既存trialおよびtracked sanitized CSVは変更していない。

## tracked証跡

env-b計測値は次へ保存した。

`docs/verification/t000-06/media-recorder-measurements.env-b.sanitized.csv`

## raw成果物

env-bのraw WebMはT000-06-05の横断集計および証跡確認が完了するまで保持する。

現時点では削除しない。

## 判定

- T000-06-02 env-b 実機計測: 完了候補
- 25 trial拡張: 不要
- production共通technical margin: 未確定
- 共通technical marginの最終確定: T000-06-05で実施