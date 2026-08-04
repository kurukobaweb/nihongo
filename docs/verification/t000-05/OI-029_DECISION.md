# OI-029 Azure文字数算出・正規化方式

## 1. 決定の適用範囲

この文書は、T000-05で承認された `ja-JP` Stage-A文字数算出規則を記録する。production実装、DB変更、正本文書への横断反映、実ブラウザE2Eは後続タスクで行う。OI-030およびOI-031は本決定の対象外である。

## 2. 表示用transcript

- source: 各final recognition segmentの `SpeechRecognitionResult.text`
- segment join: ASCII半角空白1文字
- 用途: ユーザー表示、`evaluations.transcript`

segment間の半角空白はAzureが返したseparatorではなく、アプリケーションが表示用に追加するseparatorである。

## 3. 採点用文字列

採点元は、各final recognition segmentの次のfieldとする。

```text
result.json["NBest"][0]["Lexical"]
```

`Display`、`ITN`、`MaskedITN`、`result.text`は `character_count` の算出元に使用しない。

理由:

- Displayは自動句読点と表示変換を含む
- ITNおよびMaskedITNは数字や記号を圧縮する
- `二千二十六年`から`2026年`、`百パーセント`から`100%`など、実発話量に対する文字数差が生じる
- Lexicalは今回取得できた候補の中で最も発話語形に近い

## 4. character_countアルゴリズム

各final segmentを次の順序で処理する。

1. `NBest[0].Lexical`を取得する
2. Unicode NFC正規化を適用する
3. Unicode whitespaceを除外する
4. Unicode category `P*`を除外する
5. Unicode category `C*`を除外する
6. 処理済みsegmentをseparatorなしで結合する
7. Python Unicode code point数を数える

含めるcategory:

```text
L*
M*
N*
S*
```

除外するcategory:

```text
P*
C*
whitespace
```

計数単位は、NFC正規化後のUnicode code pointとする。Python実装では処理後文字列に対する `len()` 相当である。これは現行productionの `len(transcript)` を継続するという意味ではない。

## 5. segment結合

| 用途 | separator |
|---|---|
| 表示用 | ASCII半角空白1文字 |
| 採点用 | separatorなし |

採点用をseparatorなしとするのは、Azureのsegment分割数によって `character_count` が変動しないようにするためである。

## 6. Unicode正規化

- 採用: NFC
- 不採用: NFKC

理由:

- 今回の実Azure出力ではNFCによる変更はなかった
- NFKCは全角記号や全角英数字などの互換変換を行う
- 日本語の濁点・半濁点の分解差はNFCで抑制する
- NFC後も合成されないMarkは各code pointとして数える

## 7. 採点元field欠損時

次のいずれかを取得できない場合、Displayまたは `result.text` へfallbackしない。

```text
result.json
NBest
NBest[0]
NBest[0].Lexical
```

- 分類: Stage-A response contract failure
- `character_count`: 算出しない
- `422 speech_unrecognized`: 別分類

## 8. Version

character count version:

```text
ja-jp-character-count-v1
```

定義:

```text
source=NBest[0].Lexical
normalization=NFC
exclude=whitespace,P*,C*
include=L*,M*,N*,S*
segment_separator=none
count_unit=Unicode code point
```

このversionは `scoring_version` と分離する。後続DB設計では `evaluations.character_count_version` を候補とするが、T000-05ではカラム追加もproduction実装も行わない。

## 9. 実Azure観測結果

- `result.text`、`DisplayText`、`NBest[0].Display`の表示3候補は全segmentで一致した
- Detailed outputでは `SpeechRecognitionResult.json` から `result.json` を取得できた
- `SpeechServiceResponse_JsonResult` propertyからJSONは取得できなかった
- C03で日付、時刻、価格のITN変換を確認した
- C04でAI、API、URLおよび2.0の表示変換を確認した
- C04のAB12C3は期待形にならなかった
- C05で `+`、`=`、`%` を確認した
- C06は冒頭の認識差が大きく、Unicode認識精度の証跡としては弱い
- C07は2回ともsegment数、順序、offset、durationを含む同一segment境界だった
- C07-run01のscope guardは「大阪」に対する認識結果「大坂」をunexpected textとして検知したが、Azure recognition自体は成功しており、再実行していない
- segment間空白はAzure由来ではなく、アプリケーションの表示用結合で追加された
- combining markは実Azure出力では取得できなかった

## 10. 後続実装・テスト申し送り

### T007-06

- Detailed outputを使用する
- `SpeechRecognitionResult.json`から `result.json` を取得する
- 表示用transcriptと採点用Lexicalを分離する
- `character_count`を `ja-jp-character-count-v1` 規則で算出する
- `character_count_version` 相当をresponse contractへ含める
- Lexical欠損時にDisplay、ITN、MaskedITN、`result.text`へfallbackしない
- 匿名化済みDetailed fixtureで自動テストする
- 複数segment、数字、英字、記号、Unicode、Lexical欠損、422、5xxをテストする

### T013-09

実ブラウザ、実Azure、実DBを使用し、次を比較する。

```text
読み上げ予定文
Azure実認識文
Python response transcript
evaluations.transcript
status API transcript
結果画面表示文
```

判定規則:

- Azure実認識文とアプリ表示文が異なる: アプリ実装不具合候補
- 読み上げ予定文とAzure実認識文が異なる: STT認識精度の観測結果
- 欠落、重複、不要な空白: Python、保存、表示処理の不具合候補

実ブラウザ録音からLaravel、Queue、Python、Azure、DB、polling、結果画面までを一連で確認する。T000-05の証跡だけをもってE2E完了とはしない。
