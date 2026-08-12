# OI-031 Stage-A採点仕様 Decision Record

## 1. 記録の位置づけ

本書は、T000-07で行ったOI-031の判断対象、確定結果、supersededとなった旧案、および後続タスクへの申し送りを記録するDecision Recordである。現在有効な採点仕様は[Stage-A Scoring Specification](../../STAGE_A_SCORING.md)を正とする。

採点仕様判断はユーザー承認済みであり、`docs/STAGE_A_SCORING.md`はStage-A採点仕様の正本として、また本Decision RecordはT000-07／OI-031の判断記録として、2026-08-12にユーザー最終承認された。これによりT000-07は完了とする。後続のT000-08では正本文書横断補正を行い、T000-09はT000-08直後に実施する。

## 2. OI-031の判断対象

OI-031では次を決定対象とした。

- `character_score`のprofile別bandとpoint
- `time_score`の時間帯、endpoint rule、component cap
- `character_score`と`time_score`の統合方式
- pass／fail条件と結果保存
- 境界包含と小数比較
- `scoring_version`と更新対象
- provisional scoringの可否
- Azureなし再採点の意味
- 再採点を決定論的に再現するために必要な保存事実

## 3. 確定結果の要約

- `character_score`はOI-029準拠の`character_count`と、submissionへ固定保存された`evaluation_profile_seconds`からprofile別bandで決定する。
- `time_score`は録音制御上の経過時間`T`と、最初に録音停止を確定させたmanual／auto stop reasonから決定する。
- `final_score = min(character_score, time_score)`とする。
- 両componentの最低合格点は60で、`final_score >= 60`をpassとする。
- `character_score = 0 OR time_score = 0`を採点failとし、`final_score = 0`、`evaluation_result = fail`、submissionは`completed`とする。
- 採点failではevaluationを作成し、最低条件を満たさないcomponentだけを0にする。
- OI-030上限超過はAzureへ送信せず、Stage-A採点およびevaluation作成を行わない「採点対象外」とする。
- 422 `speech_unrecognized`はSTT認識不可であり、「採点対象外」と同一視しない。
- 境界比較前に`T`を丸めない。scoreは`0`、`60`、`70`、`80`、`100`の離散値とし、採点上の丸めを行わない。
- 初期versionは`stage-a-scoring-v1`とする。
- Stage-A productionでcharacter-only等の暫定採点を許可しない。
- 再採点はhistorical submissionの保存済み事実で採点計算だけを再実行し、Azure STTを再実行しない。

profile別の全採点表、境界規則、代表例、version更新規則は正本を参照する。

## 4. supersededとなった旧案

以下は過去に議論された候補であり、現在の確定仕様では使用しない。日付、承認者、議論順は推測で補完しない。

| 旧案 | 現在の扱い |
|---|---|
| 40秒profileで50秒帯まで採点する | superseded。40秒のprofile上限auto stopとOI-030のproduction上限判定を前提とする現行仕様では成立しない。 |
| 10秒profileに11秒帯を設ける | superseded。profile上限到達は録音制御上のauto stop endpoint ruleで扱う。 |
| 60秒profileの61秒以上を採点failとする | superseded。profile上限到達はauto stop endpoint ruleで採点し、WebM上限超過はOI-030により採点対象外とする。 |
| 90秒profileの91秒以上を採点failとする | superseded。同上。 |
| 120秒profileの121秒以上を採点failとする | superseded。同上。 |
| 時間帯と文字数を1行のAND条件として直接score決定する | superseded。現在は`character_score`と`time_score`を独立計算し、`min()`で統合する。 |
| 10秒profileで40文字以上を100点とする旧character_score候補 | superseded。現行のprofile別bandは正本を参照する。 |
| 60秒profileで300文字以上を100点とする旧character_score候補 | superseded。現行のprofile別bandは正本を参照する。 |
| 90秒profileで450文字以上を100点とする旧character_score候補 | superseded。現行のprofile別bandは正本を参照する。 |
| character-onlyの暫定採点 | superseded。productionでは最初から`character_score`と`time_score`の正式統合仕様を使用する。 |

これらの旧案をlegacy comment、fixture、過去タスク記述からcurrent specへ戻してはならない。

## 5. OI-029／OI-030／OI-031の責務境界

### 5.1 OI-029

- `character_count`の算出方式を定義する。
- versionは`ja-jp-character-count-v1`とする。
- OI-031はこの確定済みcountを入力として使用し、算出algorithmを再定義しない。

### 5.2 OI-030

- `D`を元WebMのffprobe durationとして定義する。
- `P`をsubmissionへ固定保存された`evaluation_profile_seconds`として扱う。
- `D <= P + 0.07`を上限内、`D > P + 0.07`を上限超過とする。
- production共通technical margin `0.07秒`はAzure送信前上限判定にだけ使用する。

### 5.3 OI-031

- 保存済み事実を`character_score`、`time_score`、`final_score`、resultへ変換するStage-A scoring規則を定義する。
- WebM `D`をtime_scoreに使用しない。
- `0.07秒`を`T`、UI timer、ユーザー回答時間、profile上限へ加算しない。

`character_count_version`と`scoring_version`は、それぞれ文字数算出規則と採点規則を識別する別versionである。

## 6. UI方針とbackend防御の判断

- 手動STOPは録音中いつでも可能とする。
- 通常UIでは最低採点時間未満でSTOPした録音を提出させず、破棄／再録音へ進める。
- 最低時間未満でSTOPしただけの状態は、不合格でも採点対象外でもなく、submission未作成の状態である。
- profile上限到達ではauto stopする。
- backendはフロント制御だけを信用せず、最低時間未満のsubmissionが到達してSTT認識に成功した場合は採点failとしてevaluationを作成する。
- manual stopとして確定できるのは、manual STOP要求を処理する時点で`T < P`の場合だけとする。
- manual STOP要求を処理する時点で`T >= P`の場合はmanual stopとして確定せず、profile上限到達済みとして`profile_limit`とauto-stop endpoint ruleを適用する。
- manual stopとauto stopが競合した場合も、上記規則を満たしたうえで最初に録音停止状態を確定した処理のstop reasonを正とし、WebM durationから推測し直さない。

## 7. 再採点に関する判断

- 再回答／再提出は、新しい録音、新しいsubmission、新しいAzure STT結果による別評価である。
- 再採点は、同じhistorical submissionの保存済み事実値を使う採点計算の再実行である。
- 再採点ではAzure STT、transcript、認識事実を変更しない。
- submissionへ保存されたprofileを使い、現在のquestion設定を再取得しない。
- 実際に使用した`scoring_version`を保存する。
- MVPではOI-031だけを理由に再採点履歴専用テーブルを新設せず、現行の1 submission = 0..1 evaluation方針を前提に同一evaluationの派生scoreを更新する方向を後続設計へ渡す。
- old／new version、score、resultを追跡できる運用ログ要件を後続へ渡す。
- 必要事実が保存されていないhistorical evaluationは推測で補完せず、再採点不能として扱う。
- Azureを再実行した処理を「再採点」と呼ばない。
- historical bulk rescoreの自動実行、実行者、実行時期はscoring algorithmの責務外であり、T000-07の完了Blockerではない。T000-08で運用責務へ引き渡す。

## 8. 後続タスクへの申し送り

### 8.1 T000-08

- `docs/STAGE_A_SCORING.md`をStage-A採点正本として関連正本文書から参照する。
- `ARCHITECTURE.md`、`DB_SCHEMA.md`、`DESIGN.md`、`OPERATIONS.md`等には、各文書責務に必要な部分だけを反映する。
- 仕様全文を各文書へ重複コピーしない。
- historical bulk rescoreの実行triggerは採点仕様ではなく運用責務として扱い、`OPERATIONS.md`等への配置を整理する。具体的な実行者、実行時期、自動／手動方式はここでは決定しない。

### 8.2 T002-06／T002-07

再採点とtime_score再現に必要な永続事実について、最終DB設計とmigration責務を決定する。

- `character_count_version`
- time_score用の録音制御上の経過時間
- stop reason／profile limit reached相当の事実
- 境界判定を再現できる精度

具体的DBカラム名、型、保存場所はOI-031で確定していない。

### 8.3 T005-04

- 手動STOPを常時可能にする。
- 最低採点時間未満のSTOP後は提出不可とし、再録音導線を提供する。
- profile上限でauto stopする。
- `0.07秒`をUI回答時間へ加算しない。
- T000-07を依存／参照対象にする。

### 8.4 T006-03

- Queue dispatch前に、採点・再採点に必要なsubmission事実を保存できるwriter責務をT002-06の承認済み設計に従って実装する。

### 8.5 T008-05

- 正本の`stage-a-scoring-v1`に従い、`character_score`、`time_score`、`final_score`、`evaluation_result`、`scoring_version`をLaravel側で算出・保存する。
- 通常pass、採点fail、backend最低時間fail、OI-030上限超過、422、system errorを混同しない。

### 8.6 T013-08

自動テストで次を検証する。

- 全境界
- 最低時間未満のbackend防御
- UI最低時間未満提出不可contract
- manual／auto stop
- pass／fail／採点対象外

### 8.7 T013-09

実ブラウザで次を検証する。

- 最低時間前でもSTOP可能
- 最低時間未満では提出不可かつ再録音可能
- 最低時間到達後は提出可能
- profile上限auto stop
- 正常pass／fail／422／上限超過

## 9. 後続へ渡す事項

### 9.1 OI-031で未確定の実装・保存設計

- DBカラム名、型、精度、保存場所
- stop reasonの保存構造
- `character_count_version`の保存構造
- API error contract

これらを本Decision Recordから独断で確定してはならない。

### 9.2 OI-031対象外の運用責務

- historical bulk rescoreの実行trigger

このtriggerはscoring algorithmの仕様未確定項目ではなく、T000-07の完了Blockerでもない。T000-08で`OPERATIONS.md`等の適切な運用文書への配置を整理する。

### 9.3 正本文書への横断反映

- 正本文書間の横断反映

T000-08で各正本文書の責務に必要な範囲だけを反映する。
