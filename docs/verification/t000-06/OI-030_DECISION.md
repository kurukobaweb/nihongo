# OI-030 MediaRecorder停止誤差・technical margin決定

## 1. 決定の適用範囲

この文書は、T000-06で実施した実ブラウザ計測に基づき、10 / 40 / 60 / 90 / 120秒の全評価profileへ共通適用するMediaRecorderのtechnical marginを記録する。

- 判定対象はMediaRecorderが生成した元WebMをffprobeで取得した `format.duration` である。
- technical marginはAzure送信前のduration上限判定に使用する。
- productionコード、DB変更、Azure送信制御の実装は後続タスクで行う。
- OI-030の `OPEN_ISSUES.md` 上の台帳移動と正本文書への横断反映はT000-08で行う。
- OI-029の文字数算出仕様およびOI-031の採点仕様は変更しない。

## 2. 確定値

```text
technical margin:
0.07秒

判定式:
D <= P + 0.07

上限超過:
D > P + 0.07
```

各記号は次のとおりである。

- `D`: 元WebMをffprobeで取得したduration
- `P`: submissionへ固定保存する `evaluation_profile_seconds`
- `M`: MediaRecorderのtechnical marginである0.07秒

境界一致は上限内とする。0.07秒は録音処理上のtechnical marginであり、ユーザーへの追加回答時間、UIカウンター、自動停止時刻、Azure処理時間、QueueまたはHTTP timeoutへ加算しない。

## 3. 計測環境

- environment ID: `env-a`
- OS: Windows（保存されたuser agentでは `Windows NT 10.0; Win64; x64`）
- 使用ブラウザ: CodeX内蔵ブラウザ
- 保存されたブラウザ識別情報:
  - `Chrome/150.0.0.0`: 20 trial
  - `Chrome/151.0.0.0`: 5 trial
- 正確なCodeX内蔵ブラウザ製品version: 既存の保存証跡には未記録
- MIME type: 全25件 `audio/webm;codecs=opus`
- duration正本: 元WebMのffprobe `format.duration`
- WAV形式: PCM signed 16-bit little-endian / 16000Hz / mono
- profile: 10 / 40 / 60 / 90 / 120秒
- 各profile: 正式5 trial
- 合計: 正式25 trial

ブラウザ識別情報はJSONLに保存されたuser agentから確認した。新しい録音、外部アクセス、追加のブラウザ計測による補完は行っていない。

## 4. 正式trialと除外trial

| profile | 正式trial数 |
|---:|---:|
| 10秒 | 5 |
| 40秒 | 5 |
| 60秒 | 5 |
| 90秒 | 5 |
| 120秒 | 5 |
| 合計 | 25 |

- 正式25 trialはJSONLに各1件存在する。
- 正式25件はすべて `valid = true`、`invalid_reason = null` である。
- invalid trialは0件である。
- 正式集計から除外したtrialは `env-a-p010-r01-a01` である。
- 除外理由は、正式run 1として採用しない意図しない初回trialである。
- 正式な10秒run 1は `env-a-p010-r01-a02` である。

正式25 trialの再計算可能なtracked証跡は[正式25 trial sanitized計測値](./media-recorder-measurements.sanitized.csv)に記録する。

## 5. 計測結果概要

```text
正式trial:
25件

valid:
25件

invalid:
0件

最大total_overrun_seconds:
0.0006560000000064292秒

最大overrun trial:
env-a-p120-r01-a01

最大duration_method_difference_seconds:
0.060114000000005774秒

最大方式間差trial:
env-a-p040-r04-a01

total_overrun_seconds平均:
-0.0194264799999996996583秒

total_overrun_seconds中央値:
-0.00010600000000238197秒

duration_method_difference_seconds平均:
0.05862648000000142164秒

最大stop_request_delay_seconds:
0.012399999976160814秒

該当trial:
env-a-p060-r02-a01

最大stop_call_delay_seconds:
0.00010000002384185791秒

該当trial:
env-a-p090-r05-a01
```

集計はJSONLの小数表現をDecimalとして読み、表示用に丸める前の値から再計算した。

## 6. profile別結果

| profile | 正式件数 | WebM duration最小 | WebM duration最大 | total overrun最小 | total overrun最大 | 正のtotal overrun最大 | duration method difference最大 |
|---:|---:|---:|---:|---:|---:|---:|---:|
| 10 | 5 | `9.960055` | `9.990123` | `-0.03994499999999945` | `-0.00987699999999947` | `0` | `0.05994499999999903` |
| 40 | 5 | `39.959886` | `39.960562` | `-0.04011400000000265` | `-0.039437999999996975` | `0` | `0.060114000000005774` |
| 60 | 5 | `59.940429` | `60.000457` | `-0.059570999999998264` | `0.00045699999999726515` | `0.00045699999999726515` | `0.060106000000004656` |
| 90 | 5 | `89.940046` | `90.000218` | `-0.059954000000004726` | `0.00021800000000382624` | `0.00021800000000382624` | `0.06006299999999953` |
| 120 | 5 | `120.000007` | `120.000656` | `0.000006999999996537554` | `0.0006560000000064292` | `0.0006560000000064292` | `0.059993000000005736` |

## 7. technical margin導出

```text
overrun_basis
= max(0, 最大total_overrun_seconds)
= 0.0006560000000064292秒
```

```text
overrun_only_candidate
= 0.01秒単位で切り上げ
= 0.01秒
```

```text
method_difference_basis
= 最大duration_method_difference_seconds
= 0.060114000000005774秒
```

```text
method_difference_candidate
= 0.01秒単位で切り上げ
= 0.07秒
```

```text
combined_basis
= max(overrun_basis, method_difference_basis)
= 0.060114000000005774秒
```

```text
technical margin
= combined_basisを0.01秒単位で切り上げ
= 0.07秒
```

補助比較として、両根拠値を加算すると次になる。

```text
overrun_basis + method_difference_basis
= 0.0607700000000122032秒
```

加算方式でも0.01秒単位の切り上げ結果は0.07秒となる。ただし、正式採用した導出は、承認済みの保守的統合方式 `max(overrun_basis, method_difference_basis)` である。

## 8. 境界値例

```text
P = 60秒

D = 60.069999秒
→ 上限内

D = 60.070000秒
→ 境界一致のため上限内

D = 60.070001秒
→ 上限超過
→ Azure送信前に拒否
```

| profile P | 上限 `P + 0.07` |
|---:|---:|
| 10 | `10.07秒` |
| 40 | `40.07秒` |
| 60 | `60.07秒` |
| 90 | `90.07秒` |
| 120 | `120.07秒` |

判定前にdurationを0.01秒へ丸めない。ffprobe durationを取得した精度のまま `D <= P + 0.07` と比較する。

## 9. Azure送信前判定との関係

- duration判定はAzure送信前に行う。
- `D > P + 0.07` の音声はAzureへ送信しない。
- `D`は元WebMのffprobe durationである。
- `P`はsubmissionに固定保存された `evaluation_profile_seconds` である。
- 判定時にUI入力値や現在のquestion設定を再参照しない。
- エラーコード、HTTP status、ユーザー向け文言は本決定だけでは新規確定しない。
- production実装と自動テストは後続タスクで行う。

## 10. raw成果物の扱い

```text
正式計測後のJSONL record:
26件

正式trial:
25件

正式対象外trial:
1件

JSONL size:
31,820 bytes

JSONL SHA-256:
47EA09A6DDEADA0883A73A4711BFB4C7855FA746E868B18A902361D51BFF3AF3

raw WebM:
ユーザー承認後に26件削除済み

temporary WAV:
0件
```

- raw WebMはGit管理外であり、2026-08-05に削除した。
- JSONLはlocal再集計証跡として維持する。
- tracked証跡には `media-recorder-measurements.sanitized.csv` を使用する。
- tracked成果物へ音声内容、transcript、個人情報、Secrets、絶対パスを含めない。
- sanitized CSVは削除したraw音声の代替ではなく、承認済み数値判断を再確認するための証跡である。

## 11. 補足事象

`env-a-p120-r05-a01` の条件固定時、初回操作が画面へ反映されず、状態確認後の再操作で固定に成功した。

- 録音開始前の操作事象である。
- fixed trial IDは一致した。
- 録音は1回である。
- valid trialである。
- rawおよび集計値への影響はない。
- 原因は未確認である。
- UI不具合とは確定しない。
- T000-06のtechnical margin決定を阻害しない。

## 12. 後続申し送り

- productionの上限判定を実装する。
- submission snapshotの `evaluation_profile_seconds` を使用する。
- ffprobe duration取得失敗時の扱いは、後続実装で既存エラー契約と整合させる。
- 境界一致、境界直前、境界直後の自動テストを追加する。
- 上限超過時にAzureへ送信されないことを確認する。
- OI-030の `OPEN_ISSUES.md` 上の台帳移動と正本文書への横断反映はT000-08で行う。
- T000-07は本決定値を時間判定の前提として参照できる。
- T000-06の証跡だけでproduction実装完了とは扱わない。
