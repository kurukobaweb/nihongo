# Stage-A Scoring Specification

## 1. 文書の位置づけ

本書は、現在有効なStage-A採点仕様の正本である。実装・テスト・再採点設計では本書を参照し、過去の候補やlegacy commentを採点根拠として使用しない。

- scoring version: `stage-a-scoring-v1`
- character count version: `ja-jp-character-count-v1`
- 判断記録: [OI-031 Decision Record](verification/t000-07/OI-031_DECISION.md)
- character count規則: [OI-029 Decision Record](verification/t000-05/OI-029_DECISION.md)
- Azure送信前上限判定: [OI-030 Decision Record](verification/t000-06/OI-030_DECISION.md)

`character_count_version`と`scoring_version`は別概念として管理する。

## 2. Stage-Aの責務と結果分類

PythonはStage-Aの事実値を返し、Laravelが本書に従って採点する。Azureの`overall_score`を`final_score`の代用にしない。

Stage-Aに関係するユーザー向け分類は次の3つである。

### 2.1 合格

- Stage-A採点を実行する。
- evaluationを作成する。
- `final_score >= 60`とする。
- `evaluation_result = pass`とする。
- submissionを`completed`とする。

### 2.2 不合格

- Stage-A採点を実行する。
- evaluationを作成する。
- 最低条件を満たさないcomponentが存在する。
- `final_score = 0`とする。
- `evaluation_result = fail`とする。
- submissionを`completed`とする。

### 2.3 採点対象外

- Stage-A採点自体を実行しない。
- evaluationを作成しない。
- 代表例は、OI-030のAzure送信前上限超過である。

422 `speech_unrecognized`はSTT認識不可を表し、「採点対象外」と同義ではない。採点不合格、採点対象外、STT認識不可、system errorを混同しない。

## 3. 評価profile

評価profileは次の5値とし、単位は秒とする。

```text
10
40
60
90
120
```

採点にはsubmissionへ固定保存された`evaluation_profile_seconds`を使用する。Queue処理時または再採点時に現在のquestion設定やuser設定を再取得し、採点条件を変更してはならない。

## 4. character_score

`character_score`は、OI-029に従って算出した`character_count`と、submissionへ固定保存された`evaluation_profile_seconds`から決定する。

| profile | fail / 0点 | 60 | 70 | 80 | 100 |
|---:|---:|---:|---:|---:|---:|
| 10 | 0–19 | 20–29 | 30–39 | 40–49 | 50+ |
| 40 | 0–124 | 125–149 | 150–174 | 175–199 | 200+ |
| 60 | 0–89 | 90–134 | 135–179 | 180–249 | 250+ |
| 90 | 0–199 | 200–239 | 240–279 | 280–399 | 400+ |
| 120 | 0–319 | 320–359 | 360–399 | 400–549 | 550+ |

ルール:

- 最低合格component scoreは60とする。
- profile別の最低文字数未満は`character_score = 0`とする。
- `character_score`は100を上限とし、100点帯を超える文字数でも減点しない。
- actual durationやCPMから`character_score`を計算しない。
- character count規則はOI-029を正とする。
- character count versionは`ja-jp-character-count-v1`とする。

## 5. time_score

`time_score`は加点ではなく、`character_score`に対する上限として使用する。`T`の正式定義とmanual stop／auto stopの扱いは[6. 録音時間とstop reason](#6-録音時間とstop-reason)に従う。

### 5.1 10秒profile

| 条件 | time_score |
|---|---:|
| `T < 8` | 0 |
| `8 <= T < 9` | 70 |
| `9 <= T < 10`かつmanual stop | 80 |
| profile上限によるauto stop | 100 |

### 5.2 40秒profile

| 条件 | time_score |
|---|---:|
| `T < 20` | 0 |
| `20 <= T < 30` | 70 |
| `30 <= T < 40`かつmanual stop | 80 |
| profile上限によるauto stop | 100 |

### 5.3 60秒profile

| 条件 | time_score |
|---|---:|
| `T < 20` | 0 |
| `20 <= T < 30` | 60 |
| `30 <= T < 40` | 70 |
| `40 <= T < 50` | 80 |
| `50 <= T < 60`かつmanual stop | 100 |
| profile上限によるauto stop | 80 |

### 5.4 90秒profile

| 条件 | time_score |
|---|---:|
| `T < 50` | 0 |
| `50 <= T < 60` | 60 |
| `60 <= T < 70` | 70 |
| `70 <= T < 80` | 80 |
| `80 <= T < 90`かつmanual stop | 100 |
| profile上限によるauto stop | 80 |

### 5.5 120秒profile

| 条件 | time_score |
|---|---:|
| `T < 80` | 0 |
| `80 <= T < 90` | 60 |
| `90 <= T < 100` | 70 |
| `100 <= T < 110` | 80 |
| `110 <= T < 120`かつmanual stop | 100 |
| profile上限によるauto stop | 80 |

## 6. 録音時間とstop reason

### 6.1 `T`の正式定義

`T`はtime_scoreに使用する「録音制御上の経過時間」である。

- manual stopの場合、録音開始から、手動STOPが録音制御上確定した時点までの経過時間を使用する。
- profile上限によるauto stopの場合、WebM durationの数値ではなく、profile上限へ到達したという録音制御上の事実を使用し、各profileのendpoint ruleを適用する。

次の値をtime_scoreに使用してはならない。

- WebMのffprobe duration `D`
- Azureが認識したsegment duration合計
- UI表示のために丸めた秒数

### 6.2 manual stopとauto stopの競合

- `P`はsubmissionへ固定保存される`evaluation_profile_seconds`とする。
- manual STOP要求を処理する時点で`T < P`の場合にだけ、manual stopとして確定できる。
- manual STOP要求を処理する時点で`T >= P`の場合はmanual stopとして確定せず、profile上限到達済みとして`profile_limit`を正とし、auto-stop endpoint ruleを適用する。
- manual STOPとprofile上限処理が競合する場合も、上記の`T < P`／`T >= P`規則を満たしたうえで、最初に録音停止状態を確定した処理のstop reasonを正とする。
- 後からWebM durationを見てstop reasonを推測し直さない。
- 同じhistorical submissionの採点を再現できるdurableな事実として保存する。

したがって、10秒profileで`T >= 10`のmanual stopという未定義帯は設けない。具体的なJavaScript API、timer実装、state machine構造は本書では確定しない。

stop reasonを表す最終DBカラム名、型、保存場所は本書では確定しない。T002-06以降のDB設計で決定する。

## 7. UI制御とbackend防御

### 7.1 通常UI

- 手動STOP自体は録音中いつでも可能とする。
- 最低採点時間未満でSTOPした録音は通常UIから採点提出させず、破棄／再録音へ進める。
- 最低採点時間以上なら通常の提出確認へ進める。
- profile上限到達ではauto stopする。

| profile | 最低採点時間 |
|---:|---:|
| 10 | 8秒 |
| 40 | 20秒 |
| 60 | 20秒 |
| 90 | 50秒 |
| 120 | 80秒 |

最低時間未満でユーザーが通常UIからSTOPしただけの状態は、不合格でも採点対象外でもない。submission未作成の録音破棄／再録音状態である。

### 7.2 backend防御

フロント制御だけを信用しない。最低時間未満のsubmissionが異常経路で到達し、STT認識自体が成功した場合は次のとおり処理する。

- `time_score = 0`
- `character_score`は認識済み`character_count`から通常どおり計算する。
- `final_score = 0`
- `evaluation_result = fail`
- evaluationを作成する。
- submissionを`completed`とする。

最低条件を満たさないcomponentだけ0にし、他方の有効なcomponent scoreは通常どおり計算・保存する。どちらか1つでもcomponentが0なら`final_score = 0`とする。

例:

```text
10秒profile / 7.9秒 / 50文字
character_score = 100
time_score = 0
final_score = 0
evaluation_result = fail
```

```text
10秒profile / profile上限auto stop / 19文字
character_score = 0
time_score = 100
final_score = 0
evaluation_result = fail
```

## 8. final_scoreとpass／fail

正式統合式は次のとおりとし、加算方式は使用しない。

```text
final_score = min(character_score, time_score)
```

pass条件:

```text
final_score >= 60
```

fail条件:

```text
character_score = 0
OR
time_score = 0
```

failの場合:

```text
final_score = 0
evaluation_result = fail
```

代表例:

| profile・入力 | character_score | time_score | final_score | result |
|---|---:|---:|---:|---|
| 10秒 / 8秒 / 50文字 | 100 | 70 | 70 | pass |
| 10秒 / 9秒 / 50文字 | 100 | 80 | 80 | pass |
| 10秒 / 9.5秒 manual stop / 30文字 | 70 | 80 | 70 | pass |
| 10秒 / profile上限auto stop / 50文字 | 100 | 100 | 100 | pass |

## 9. OI-030 technical marginとの分離

OI-030で確定したproduction共通technical marginは`0.07秒`である。Azure送信前判定は次のとおりとする。

```text
D <= P + 0.07  : 上限内
D > P + 0.07   : 上限超過
```

- `D`は元WebMのffprobe durationである。
- `P`はsubmissionへ固定保存された`evaluation_profile_seconds`である。

`D > P + 0.07`の場合はAzureへ送信せず、Stage-A採点を実行せず、evaluationを作成しない。ユーザー向け分類は「採点対象外」とする。

technical marginを次へ加算してはならない。

- ユーザー回答時間
- UI timer
- 手動STOP可能時間
- time_score用`T`
- profile上限
- Queue timeout
- HTTP timeout

WebM duration `D`をtime_scoreに使用してはならない。

## 10. 小数境界とscore表現

録音制御上の経過時間はtime_score境界との比較前に丸めない。境界一致は次の帯へ入る。

例として60秒profileでは、`19.999...`は20秒未満のため`time_score = 0`、`20.000...`は20秒以上のため`time_score = 60`となる。`30.000...`は30秒以上40秒未満の帯へ入る。

通常UIでは最低時間未満の録音を提出できないため、最低時間未満のscore例はbackend防御テストとして扱う。

Stage-A v1のscoreは次の離散値だけを使用する。

```text
0
60
70
80
100
```

`character_score`、`time_score`、`final_score`に採点上の丸め処理は行わない。DB上で`60.00`等と保存される場合、それはDB表現であり採点丸めではない。

## 11. scoring_version

初期Stage-A scoring versionは`stage-a-scoring-v1`とする。各evaluationには、採点時に実際に使用した`scoring_version`を保存する。同じ採点入力からscoreまたはresultが変わり得る変更ではversionを更新する。

version更新対象:

- character_score band／point
- time_score band／cap
- 最低合格点
- pass／fail条件
- 採点対象／対象外境界
- `final_score`統合式
- 境界包含規則
- 小数比較／丸め規則
- profile上限到達の解釈
- 採点入力値の意味

versionを更新しない例:

- UI文言だけの変更
- CSS、layout
- logging
- score／resultを変えないrefactor、SQL、PHP、テスト変更
- performance改善

単純な世代versionとして管理し、SemVerを必須としない。文字数算出方式自体の変更で過去scoreが変わり得る場合は、再現性のため`character_count_version`と`scoring_version`双方の扱いを明確にする。

## 12. 暫定採点の禁止

Stage-A productionでは文字数のみの暫定採点を許可しない。Stage-A採点を行う場合は最初から次の正式統合仕様を使用する。

```text
character_score
+ time_score
-> min()
-> final_score
```

character-only production mode、time_score無効化mode、provisional scoring用Feature Flagを追加しない。

## 13. 再回答／再提出と再採点

### 13.1 再回答／再提出

ユーザーがもう一度話す操作である。

- 新しい録音と新しいsubmissionを作成する。
- Azure STTを再実行し、新しい認識事実を得る。
- 過去submissionとは別評価とする。

### 13.2 再採点

同じhistorical submissionに保存された事実値を使い、採点計算だけを再実行する。

- Azure STTを再実行しない。
- transcriptおよび元の認識事実を変更しない。
- 保存済み事実値から`character_score`、`time_score`、`final_score`、resultを再計算する。
- 再採点に実際に使用した`scoring_version`を保存する。
- submissionは`completed`のままとする。
- 再採点時に現在のquestion設定を再取得せず、submissionへ保存されたprofileを使用する。
- MVPでは再採点履歴専用テーブルをOI-031だけを理由として新設しない。
- 現行の1 submission = 0..1 evaluation方針を前提に、派生scoreを同一evaluation上で更新する方向を後続設計へ渡す。
- old／new version、score、resultを追跡可能な運用ログ要件として後続へ渡す。

再採点を自動bulk実行するか、誰がいつ実行するかという運用triggerはscoring algorithmの責務外であり、T000-07の完了Blockerではない。T000-08で`OPERATIONS.md`等の適切な運用文書へ引き渡し、配置先を整理する。

## 14. 再採点に必要な保存事実

再採点を決定論的に再現するため、意味上少なくとも次の事実を永続化できる必要がある。

1. `character_count`
2. `character_count_version`
3. `evaluation_profile_seconds`
4. time_score境界を再現できる精度の録音制御上の経過時間
5. profile上限auto stopへ到達したか、または同等のstop reason事実

制約:

- 具体的DBカラム名・型は本書で確定しない。
- `numeric(8,2)`等の型・精度へ独断で固定しない。
- `19.999`／`20.000`等の境界判定を再現できる精度を確保する。
- `audio_duration_seconds`だけからauto stop／manual stopを後付け推測しない。
- 必要事実が保存されておらず再現不能なhistorical evaluationを推測で補完しない。
- Azureを再実行した処理を「再採点」と呼ばない。
- 再現不能なhistorical evaluationは再採点不能として扱う。

character count algorithm自体が変わる場合、保存済み`character_count`だけでは新algorithmへ変換できないことがある。Azure再実行は禁止し、必要な元Lexical事実が保存されていなければ新versionへ再採点不能とする。

## 15. 代表境界例

「profile limit」は録音制御上のauto stopを表す。`D > P + 0.07`はOI-030のAzure送信前判定で採点対象外となり、evaluationを作成しない。最低採点時間未満の例は、通常UI経路ではなくbackend防御／境界仕様確認用である。

### 15.1 10秒profile

| 入力 | character | time | final | 結果 |
|---|---:|---:|---:|---|
| 7.999秒 / 50文字 | 100 | 0 | 0 | fail |
| 8.000秒 / 20文字 | 60 | 70 | 60 | pass |
| 8.999秒 / 50文字 | 100 | 70 | 70 | pass |
| 9.000秒 / 50文字 | 100 | 80 | 80 | pass |
| 9.999秒 manual / 50文字 | 100 | 80 | 80 | pass |
| profile limit auto stop / 50文字 | 100 | 100 | 100 | pass |
| profile limit auto stop / 19文字 | 0 | 100 | 0 | fail |
| `D > 10.07` | — | — | — | 採点対象外、evaluationなし |

### 15.2 40秒profile

| 入力 | character | time | final | 結果 |
|---|---:|---:|---:|---|
| 19.999秒 / 200文字 | 100 | 0 | 0 | fail |
| 20.000秒 / 125文字 | 60 | 70 | 60 | pass |
| 29.999秒 / 200文字 | 100 | 70 | 70 | pass |
| 30.000秒 / 200文字 | 100 | 80 | 80 | pass |
| 39.999秒 manual / 200文字 | 100 | 80 | 80 | pass |
| profile limit auto stop / 200文字 | 100 | 100 | 100 | pass |
| profile limit auto stop / 124文字 | 0 | 100 | 0 | fail |
| `D > 40.07` | — | — | — | 採点対象外、evaluationなし |

### 15.3 60秒profile

| 入力 | character | time | final | 結果 |
|---|---:|---:|---:|---|
| 19.999秒 / 250文字 | 100 | 0 | 0 | fail |
| 20.000秒 / 90文字 | 60 | 60 | 60 | pass |
| 29.999秒 / 250文字 | 100 | 60 | 60 | pass |
| 30.000秒 / 250文字 | 100 | 70 | 70 | pass |
| 40.000秒 / 250文字 | 100 | 80 | 80 | pass |
| 50.000秒 / 250文字 | 100 | 100 | 100 | pass |
| 59.999秒 manual / 250文字 | 100 | 100 | 100 | pass |
| profile limit auto stop / 250文字 | 100 | 80 | 80 | pass |
| profile limit auto stop / 89文字 | 0 | 80 | 0 | fail |
| `D > 60.07` | — | — | — | 採点対象外、evaluationなし |

### 15.4 90秒profile

| 入力 | character | time | final | 結果 |
|---|---:|---:|---:|---|
| 49.999秒 / 400文字 | 100 | 0 | 0 | fail |
| 50.000秒 / 200文字 | 60 | 60 | 60 | pass |
| 60.000秒 / 240文字 | 70 | 70 | 70 | pass |
| 70.000秒 / 280文字 | 80 | 80 | 80 | pass |
| 80.000秒 / 400文字 | 100 | 100 | 100 | pass |
| 89.999秒 manual / 400文字 | 100 | 100 | 100 | pass |
| profile limit auto stop / 400文字 | 100 | 80 | 80 | pass |
| profile limit auto stop / 199文字 | 0 | 80 | 0 | fail |
| `D > 90.07` | — | — | — | 採点対象外、evaluationなし |

### 15.5 120秒profile

| 入力 | character | time | final | 結果 |
|---|---:|---:|---:|---|
| 79.999秒 / 550文字 | 100 | 0 | 0 | fail |
| 80.000秒 / 320文字 | 60 | 60 | 60 | pass |
| 90.000秒 / 360文字 | 70 | 70 | 70 | pass |
| 100.000秒 / 400文字 | 80 | 80 | 80 | pass |
| 110.000秒 / 550文字 | 100 | 100 | 100 | pass |
| 119.999秒 manual / 550文字 | 100 | 100 | 100 | pass |
| profile limit auto stop / 550文字 | 100 | 80 | 80 | pass |
| profile limit auto stop / 319文字 | 0 | 80 | 0 | fail |
| `D > 120.07` | — | — | — | 採点対象外、evaluationなし |
