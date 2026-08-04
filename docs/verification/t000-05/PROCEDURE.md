# T000-05 Azure Speech 文字列検証手順

## 1. 位置付け

この文書は、T000-05（OI-029）の仕様判断に使用した実 Azure Speech 検証の手順と、sanitization 方針を記録する。検証の目的は次のとおり。

- Azure Speech `ja-JP` の実出力から OI-029 の判断材料を取得する
- 表示用 transcript 候補と採点用文字列候補を比較する
- 空白、句読点、数字、英字、記号、Unicode 正規化、segment 結合を確認する
- 実測事実と仕様判断を再現可能にする

この検証は、production 実装済み、結合テスト済み、または E2E 成立を意味しない。OI-030 および OI-031 の仕様も確定しない。

## 2. 実行環境

| 項目 | 値 |
|---|---|
| Python | `3.12.10` |
| Azure Speech SDK | `1.50.0` |
| Azure region label | `Japan East` |
| language | `ja-JP` |
| configuration | endpoint 優先構成 |
| output format | `Detailed`（C01 Simple pilot を除く） |
| recognition mode | continuous recognition |
| audio input | 16 kHz / 16-bit / mono PCM WAV |
| Docker Desktop | `4.55.0` |
| Docker Engine | `29.1.3` |
| ffmpeg image | `jrottenberg/ffmpeg:6.1-alpine` |

endpoint 実値、key、token、request ID、session ID はtracked成果物へ保存しない。

## 3. Azure 呼び出し数

| 対象 | 呼び出し数 |
|---|---:|
| C01 Simple | 1 |
| C01 Detailed | 1 |
| C02〜C06 | 各1、合計5 |
| C07 | 同一音声で2 |
| 累計 | 9 |
| retry | 0 |

## 4. Sanitization

- Secrets、endpoint 実値、URLを保存しない
- request ID と session ID は presence flag または alias だけを保存する
- 未加工音声、未加工 Azure JSON、実行helper、call ledgerは `storage/app/local/t000-05/` 以下へ保存し、Git管理外とする
- tracked成果物は allowlist 方式で生成する
- 個人情報を含まない固定録音文だけを使用する
- 音声 SHA-256 は再現性情報として保存できる
- 固定文および個人情報を含まない Azure 認識文は比較証跡として保存できる

## 5. 実施手順

1. Git状態、Python、SDK、Docker、ffmpeg、Secretsの設定有無をpreflightで確認した
2. C01をSimple outputで1回実行した
3. Azure Portalで対象Speechリソースへのメトリック計上を確認した
4. 同一C01音声をDetailed outputで1回実行した
5. C02〜C07の固定録音とGit ignore状態を確認した
6. 各音声を16 kHz / 16-bit / mono PCM WAVへ変換し、SHA-256とdurationを確認した
7. Detailed output、continuous recognition、`SpeechRecognitionResult.json`を使用してC02〜C06を各1回、C07を同一音声で2回実行した
8. retry、fallback、予定外caseの実行を行わなかった
9. 未加工証跡とsanitized summaryをlocal-only領域へ保存した
10. 保存済みJSONだけを使い、文字列field、segment結合、NFC/NFKC、Unicode category、文字数候補をオフライン比較した

## 6. 証跡の対応

- Sanitized run一覧: `docs/verification/t000-05/azure-stt-runs.sanitized.jsonl`
- 文字列比較: `docs/verification/t000-05/character-string-comparison.csv`
- 承認済み仕様判断: `docs/verification/t000-05/OI-029_DECISION.md`
- 境界値: `docs/verification/t000-05/character-count-boundary-cases.csv`
- Git管理外の原証跡: `storage/app/local/t000-05/`
