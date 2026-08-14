# T000-09 TASKS Responsibility Migration

> **種別:** verification artifact（非canonical）
>
> **対象:** T000-09 Phase 1〜3の分類・設計・文書反映証跡
>
> **基準commit:** `93b70d6589d4da5a495e8ce88cf1225dd1d13d37`
>
> **作業branch:** `codex/t000-09-task-responsibility-migration`
>
> **作成日:** 2026-08-13

## 1. Objective

`docs/TASKS.md`を「実行計画」、既存canonicalを「仕様正本」へ戻す。task purpose/dependency/action/completion/evidenceを失わず、恒久仕様本文と未確定事項全文をTASKSから参照へ整理する。仕様値の変更、application implementation、MVP完了判定は行わない。

## 2. Approval summary

- Phase 1: 共通部およびChapter 0〜17のA〜G分類、current validity/dependency/scope reviewを完了
- Phase 1 cross-cutting: MVP capability、M1/M2/R1/F1、本番リリース境界、T017 final gateをreview
- Phase 2-A: TASKS/canonical責務、evidence保護、MVP/R1/F1、4 block設計を承認
- Phase 2-B: Block 1〜4の具体修正案を承認
- Phase 2-C: 統合案とreview補正を承認
- Phase 3: 本artifact記載のdocs-only change setへ反映

## 3. Responsibility mapping

| Content | Destination | TASKS residual |
|---|---|---|
| task purpose / dependency / action / completion / test | `TASKS.md` | 本文を保持 |
| permanent architecture | `ARCHITECTURE.md` | canonical参照 |
| DB/persistence/deletion | `DB_SCHEMA.md` | canonical参照 |
| UI/UX/journey | `DESIGN.md` | canonical参照 |
| Queue/Scheduler/cleanup/log/recovery | `OPERATIONS.md` | canonical参照 |
| Stage-A scoring formula/boundary/version | `STAGE_A_SCORING.md` | canonical参照（本Phaseでは無変更） |
| unresolved decision | `OPEN_ISSUES.md` | OI ID + decision/progress gate |
| cross-document status | `CONSISTENCY_CHECK.md` | 結果参照のみ。primary specにしない |
| phase/R1 overview | `README.md` | high-level handoff |
| completed result/evidence | `TASKS.md` + existing verification artifact | date/PR/commit/result/environment/approval/artifactを追跡可能に保持 |

新規canonical documentは作成していない。本artifactは判断・移行証跡であり、仕様正本ではない。

## 4. Historical evidence protection

- completed taskの目的、完了日、PR、commit、test/build/browser/Azure/Queue/DB evidence、当時未確認事項をcurrent仕様へ書き換えない
- historical `二者択一`、設定5項目、Feature Flag/comment等は当時の記録として保持する
- current差分はT002-06 / T002-07 / T004-04 / T005-04 / T006-03 / T007-06 / T008-05 / T009-06 / T010-04 / T011-03等の後追加taskで回収する
- artifactがないunique evidenceはTASKSから削除しない

## 5. Later-added metadata

上記10taskへ、状態とは別軸で次を登録した。

- 追加区分、TASKS追加日、追加commit、追加起点、確定根拠
- 追加前章状態、位置づけ、進行制約、blocker区分
- 共通追加履歴: `2026-08-03T10:03:41+09:00`、commit `bf15014b8486cf16c17a10a98eae3dd256f4a753`、merge `fecf1f284d283ba8384ee768e0ca4de6cf719697`、PR #55

## 6. New OI inventory

| OI | Responsibility | Execution path |
|---|---|---|
| OI-109 | withdrawal時のpending/processing submission・running Queue race | T015-01-01 → T015-02 / T015-04 |
| OI-110 | withdrawal orchestration partial failure/compensation | T015-01-01 → T015-02 / T015-04 |
| OI-111 | withdrawal completion notification timing（DESIGN.mdへ反映） | user decision gate → T015-02-01 → T015-04。decisionがhard delete後通知を要求する場合のみT015-03-02 dependencyを追加 |
| OI-112 | Stage-A cross-layer error contract | T000-10 → T007-06 → T008-05 → T009-06 → T013-08/09 |

## 7. New task inventory

1. T000-10 — OI-112 error contract
2. T013-11 — OI-010 continuous recognition stability
3. T013-12 — OI-012 Pronunciation PoC Go/No-Go
4. T014-06-01 — OI-027 Webhook event decision
5. T015-01-01 — OI-109/OI-110 withdrawal orchestration decision
6. T015-02-01 — withdrawal completion notification
7. T015-03-01 — OI-105 hard delete executor decision
8. T015-03-02 — actual hard delete implementation/test
9. T016-01-01 — non-production admin provisioning
10. T016-02-01 — OI-028 admin scope decision
11. T016-05 — OI-024 navigation decision/implementation/browser acceptance
12. T016-06 — OI-025 tokens decision/implementation/browser acceptance

Conditional admin child taskはOI-028の結果により0〜複数とし、本Phaseでは採番していない。既存task削除0、renumber 0。

### High / Medium OI owner-path check

| OI | MVP/R1/F1 path |
|---|---|
| OI-001 / OI-021 | T012-06でMVP minimum、production detailはR1 |
| OI-002 | T013-06でconfigured endpointを確認、production standardはR1 |
| OI-003 / OI-008 | T013-07 measurement + user decision、production Redis triggerはR1 |
| OI-006 | T009-06 user decision + implementation/test → T013-09 browser evidence |
| OI-010 | T013-11 → T013-09 |
| OI-012 | T013-12。Stage-B implementationはF1 |
| OI-014 / OI-019 / OI-020 | README Phase② + T017/R1 handoff → separate production TASKS |
| OI-103 | production release前にinvoice制度対応要否を判断するR1。custom PDF / invoice UI拡張はF1候補 |
| OI-018 | MVP branch workflowはTASKS共通rule、production release/deployはR1 |
| OI-024 / OI-025 | T016-05 / T016-06 |
| OI-027 | T014-06-01 → T014-07/08 |
| OI-028 | T016-02-01 → conditional children → T016-03 |
| OI-104 | T016-01-01でMVP側、production SecretsはR1 |
| OI-105 | T015-03-01 → T015-03-02/04 |
| OI-107 | T008-05 decision gate → T013-08/09 |
| OI-109 / OI-110 | T015-01-01 → T015-02/04 |
| OI-111 | user decision gate → T015-02-01/04 |
| OI-112 | T000-10 → T007-06/T008-05/T009-06 → T013-08/09 |

未解消High / Medium OIの処理先未割当は0件。R1項目はMVP taskへ混在させず、MVP後に作るseparate production TASKSへ引き渡す。

## 8. Dependency before / after highlights

| Task | Before | After concept |
|---|---|---|
| T007-06 | T000-05 / T000-08 / T002-07 | + T000-10 error contract |
| T013-05 | T008-04 / T012-01 / T013-02 | T008-05 / T012-06 |
| T013-07 | T009-02 / T013-02 / T013-06 | T013-06 / T009-06 / T013-08 |
| T013-09 | T013-08 | T013-08 / T013-11 |
| T013-10 | T013-09 | T013-05 / T013-07 / T013-09 |
| T014-07 | T014-06 / abstract OI-027 gate | T014-06 / T014-06-01 |
| T015-04 | T015-03 | T015-03-02 / T015-02-01 / T014-08 |
| T016-04 | T016-02 | T016-02 / T016-01-01 |
| T016-03 | T016-02 / abstract OI-028 gate | T016-04 / T016-02-01 + conditional child evidence |
| T017-01 | 12 hard dependencies | hard dependencyなし。7系列をMVP `complete` prerequisiteとしてreview |

## 9. MVP / R1 / F1 boundary

- M1: existing/current MVP implementation and evidence pathで閉じる
- M2: decision、implementation、test、dependency、owner、completion gateの不足をTASKSへ明示
- R1: production infrastructure/deployment、Azure production、Stripe live、email provider、formal question data、final legal copy、OI-103 invoice制度対応要否、monitoring/backup/Secrets/admin production、Git/release。MVP後のseparate production TASKSへhandoff
- F1: Stage-B、Pronunciation implementation、Fluency、LLM comment、annual plan、custom PDF / invoice UI拡張、learning dashboard、additional admin roles/permissions、未採用admin feature、re-consent
- OI-012のGo/No-Go decisionはM2、Stage-B implementationはF1として分離

## 10. T017 final-review structure

- hard dependencyなし。未完了状態でもreviewを開始し`incomplete`を正式記録可能
- MVP `complete` prerequisite: T013-01 / T013-10 / T013-12 / T015-04 / T016-03 / T016-05 / T016-06
- resultは`complete` / `incomplete`。`条件付き完了`は使用しない
- review task completionとMVP acceptance resultを分離
- blocker解消後に再review可能。previous resultを保持しlatest resultとuser judgmentを記録

## 11. Invariant manifest

1. question format: `single_prompt` / `two_choice`、仕様説明「二テーマ選択」、UI「2択」
2. profiles: 10 / 40 / 60 / 90 / 120
3. OI-029: `NBest[0].Lexical`、`ja-jp-character-count-v1`
4. OI-030: `D <= P + 0.07`
5. OI-031: `final_score = min(character_score, time_score)`、threshold 60、`stage-a-scoring-v1`
6. withdrawal retention: soft delete後30日
7. Stripe: Standard、月額660円（税込）、7日trial、card only、period-end cancellation
8. Stage-A: Stage-B用4項目はNULL・非生成・非表示
9. settings: `question_format_preference` / `timer_display_mode`
10. architecture: browser → Laravel → database Queue → Python/ffmpeg → Azure → Laravel scoring → DB/API/UI
11. audio: non-persistent、terminal cleanup + recovery cleanup、backup対象外
12. history: completed evidenceをcurrent仕様へ改変しない

## 12. Phase 3 changed files

- `docs/OPEN_ISSUES.md`
- `docs/ARCHITECTURE.md`
- `docs/DB_SCHEMA.md`
- `docs/DESIGN.md`
- `docs/OPERATIONS.md`
- `docs/TASKS.md`
- `README.md`
- `docs/CONSISTENCY_CHECK.md`
- `docs/verification/t000-09/TASKS_RESPONSIBILITY_MIGRATION.md`

`docs/STAGE_A_SCORING.md`は変更していない。application code、migration、test、build、API、browser、Azure、Stripe、Queue、DB、VPS操作は実施していない。

## 13. Static validation record（initial Phase 3 pass）

initial Phase 3 pass終了時の静的確認結果:

- task ID: 111件、duplicate 0
- dependency edge: 203件、missing target 0、cycle 0
- OI definition / reference: 43 / 43、missing reference target 0
- new mandatory task: 12件、duplicate 0
- new OI: 4件、duplicate 0
- later-added metadata: 10 task
- MVP `complete` prerequisite: 7系列、T017 hard dependency: 0
- canonical orphan: 0（移動・縮約内容は既存canonicalまたは本artifactのmappingへ接続）
- historical evidence orphan: 0（completed historyは削除していない）
- High / Medium OI owner/path orphan: 0
- M1 path orphan: 0、M2 path orphan: 0（conditional admin childはOI-028決定後に生成）
- critical invariant violation: 0
- `STAGE_A_SCORING.md` diff: 0行
- Markdown fenced block: 対象9ファイルすべてbalanced
- `git diff --check`: pass

未実施のE2Eやimplementation evidenceは記録していない。

## 14. Phase 3 correction pass

ChatGPT actual diff reviewで承認された11点だけを局所補正した。Phase 3全体の再設計、task / OI追加、task削除・renumber、application implementationは行っていない。

1. T013-05の固定pathを除き、T012-06で確定するconfigured audio storage path参照へ変更
2. T013-02のStripe前順序をhistorical recordへ限定し、current T014 execution gateから除外
3. OPEN_ISSUES priorityを対象フェーズ相対へ変更
4. Stripe Webhook idempotency key / persistence contractをT014-06 / T014-07前のtechnical clarificationへ変更
5. Queue authorized retry / duplicate / stale / processing retry / terminal / exhaustionをT008-05前のtechnical clarificationへ変更
6. OI-111のDESIGN.md反映と、decision結果に応じたT015-03-02 conditional dependencyを追加
7. T013-09 test viewpointを5 outcome classへ同期し、T013-06 readiness gateをT013-07 / T013-11 / T013-09へ同期
8. ffmpeg記述をsystem binary + subprocessというcurrent implementation factへ限定
9. OI-103をR1へ移し、custom PDF / invoice UI拡張だけをF1候補として分離
10. hard delete executorをbatch前提にせず、OI-105結果に応じたScheduler / approved manual operationへ分離
11. DESIGN.mdのOI-022 / OI-023参照を解消済みhistoryへ変更し、current primary sourceを明示

correction pass後も、new mandatory task 12件、new OI 4件、task削除0、renumber 0、critical invariantは変更していない。

### Correction pass static validation

- task ID: 111件、duplicate 0
- dependency edge: 203件、missing target 0、cycle 0
- historical task deletion: 0、new mandatory task: 12件、renumber: 0
- OI definition / reference: 43 / 43、duplicate 0、missing reference target 0
- High / Medium OI owner/path orphan: 0
- canonical orphan: 0、historical evidence orphan: 0
- M1 path orphan: 0、M2 path orphan: 0
- critical invariant violation: 0
- `STAGE_A_SCORING.md` diff: 0行
- Markdown fenced block: 対象9ファイルすべてbalanced
- `git diff --check`: pass

未実施のE2Eやimplementation evidenceは追加していない。Phase 3のuser approval、commit、push、PRは未実施である。

## 15. Final approval / merge / cleanup record

### User approval

- Phase 3 final actual diff: user approved
- GitHub PR actual diff: user approved for merge

### Final approved patch

- patch: `t000-09-phase3-micro-correction-actual-20260813-192631.diff`
- SHA-256: `8FF0BEF5398011C1981EF48355D5F23ACDC4EB389A5589DEDD4165524B8CD39C`
- patch本体はrepositoryへ追加していない

### Git evidence

- implementation commit: `d59396fd595115d4233d6d89110f58ec5cfe412a`
- PR: `#72 docs: reorganize MVP task responsibilities`
- merge commit: `03e2c712b30abc1f44fc4e41a9d1aed5307bf90f`
- base: `develop`
- PR changed files: 9
- PR commit count: 1
- PR diff: 1004 insertions / 227 deletions

### GitHub review result

- actual GitHub PR diffをreviewし、approved Phase 3 final diffとの一致を確認した
- PRがmergeableであることを確認後、ユーザーがmergeを承認した
- PR #72は`develop`へ正常にmergeされた

### Post-merge

- local `develop`: `03e2c712b30abc1f44fc4e41a9d1aed5307bf90f`
- `origin/develop`: `03e2c712b30abc1f44fc4e41a9d1aed5307bf90f`
- ahead / behind: `0 / 0`
- working tree: clean
- local task branch: normally deleted
- remote task branch: deleted
- force delete: No
- force push: No

### Validation summary

- existing task deletion: 0
- task renumber: 0
- new mandatory task: 12
- new OI: 4
- missing dependency target: 0
- dependency cycle: 0
- High / Medium OI owner/path orphan: 0
- M1 / M2 orphan: 0
- critical invariant violation: 0
- canonical orphan: 0
- historical evidence orphan: 0
- `STAGE_A_SCORING.md`: unchanged
- application implementation: none

### Final result

TASKS.mdと正本文書の責務分離、canonical未配置0件、重要historical evidence消失0件、actual diffのユーザー承認、PR #72の`develop` mergeを確認し、T000-09の目的とcompletion conditionを満たした。これはT000-09の文書責務整理完了記録であり、MVP、T017、downstream taskの完了判定ではない。

## 16. Final completeness incident / correction record

### Audit result

- Final Completeness Audit: HOLD
- audit中のrepository変更: なし
- PR #73: Draft / Open / 未merge
- §15はcompletion recordを作成した時点のhistorical recordとして保持する。後続auditによりcurrent completion decisionはHOLDへ戻した

### Confirmed findings

#### KF-01: new mandatory task provenance metadata適用漏れ

- T000-09で追加したnew mandatory task: 12件
- correction前のlater-added provenance coverage: 0 / 12
- existing later-added mandatory correction task: 10件中10件でstateとprovenance metadataを別軸管理
- 原因: 承認済みlater-added metadata ruleをnew mandatory task 12件へ適用していなかった
- correction: 12件それぞれへ追加区分、TASKS追加日、added commit / PR / merge commit、追加起点、追加前章状態、位置づけを追加した。current stateの`未着手`は変更していない

対象task:

- T000-10
- T013-11
- T013-12
- T014-06-01
- T015-01-01
- T015-02-01
- T015-03-01
- T015-03-02
- T016-01-01
- T016-02-01
- T016-05
- T016-06

#### KF-02: DESIGN current settings不整合

- `DESIGN.md` §7-5にhistorical T011-02時点の旧5設定がcurrent設定として残っていた
- 完了済みT011-02をfuture actionとして扱う記述が残っていた
- current canonicalの2設定（`question_format_preference` / `timer_display_mode`）およびT011-03 correctionと不整合だった
- correction: current設定表を2項目へ同期し、`user_learning_settings`方式、historical T011-02の5設定保存基盤、T011-03によるcurrent correctionを明示した

### False-positive correction

#### KF-03: Stage-A profile scoring ranges

- Final Completeness Auditの指示に、current canonicalと異なるprofile scoring rangesが監査条件として含まれていた
- current Stage-A scoring canonicalは`docs/STAGE_A_SCORING.md`であり、T000-07 / OI-031 Decision Recordでユーザー最終承認済みである
- audit条件側の誤りであり、scoring canonicalのcorrectionは不要と判断した
- `docs/STAGE_A_SCORING.md`、`docs/verification/t000-07/OI-031_DECISION.md`、scoring band、time_score、character_score、profile値は変更していない

### Completion state

- PR #72のimplementation、user approval、merge、post-merge cleanupのhistorical factsは有効である
- T000-09 completion decision: HOLD
- 本correctionのactual diff reviewとuser approval後、独立したFinal Completeness re-auditを行う
- Final Completeness re-auditでFAIL 0 / UNVERIFIED 0となるまでT000-09をcompleteにしない
- current stopping point: correction applied / independent re-audit pending

## 17. Post-correction content & consistency review

### Review method / result

- review date: 2026-08-14
- review candidate: PR #73 candidate HEAD `a866d306a601ec28ff5ddaf1d9a93908fd2e0cd0`と未commit Incident Correctionの合成状態
- review result: HOLD
- review中のrepository変更: なし
- T012-06のlater-added provenance漏れに関するユーザー指摘を契機として、既知FindingだけでなくTASKS本文0〜17章とcanonical全体を内容ベースで再確認した
- new mandatory 12件のprovenance correctionは12 / 12、DESIGN current settings correctionは2項目で妥当だった

### Review procedure / recurrence prevention

1. TASKS本文を章・task単位で読み、state、role、dependency、canonical、OI、gate、historical/current位置づけを理解する
2. baselineとcurrentをtask単位でsemantic比較する
3. TASKSのcurrent記述をcanonical documentsと内容照合する
4. later-addedまたはhistorical/currentに疑義があるtaskだけGit historyでaddition originを確認する
5. task / dependency / OI件数等のmachine validationは最後の補助として用い、semantic reviewを代替させない
6. 既知Finding以外の不整合も独立Findingとして記録する
7. completion確定前に独立再監査でFAIL 0 / UNVERIFIED 0を要求する

### Confirmed findings and correction

#### F-01: additional later-added provenance omission

- affected: T000-09、T012-06、T013-06、T013-08、T013-09、T013-10
- correction: 6件へ追加区分、TASKS追加日、addition commit / PR / merge、追加起点、追加前章状態、位置づけを追加した
- T013-06はoriginal logical task `T013-06-pre`のadditionと、後続の`T013-06`へのrenumber historyを分離して記録した
- T013-07はlogical load-test taskのrenumberであり、本6件のlater-added logical taskとしてmetadataを追加していない

#### F-02: Chapter 12 current purpose

- historical T012-01〜T012-05のT013-02前準備と、later-added current follow-up T012-06を章注記で分離した
- T012-06はT012-01のcleanup semanticsを再決定せず、OI-021のnon-production MVP条件を確定してT013-05へ接続する
- production path / volume / permissions / cadence / monitoring等はR1へ維持した

#### F-03: admin access behavior

User decision:

- unauthenticated: existing auth middleware / login flowに従ってloginへ遷移
- authenticated `users.role = user`: HTTP 403
- authenticated `users.role = admin`: admin route / minimal admin shellへのアクセスを許可

ARCHITECTURE.md、DESIGN.md、T016-01、T016-04へ同じtest oracleを反映した。OI-028の管理機能scope、role種類、permission table、provisioning、navigationは変更していない。

#### F-04: canonical consistency ledger tense

- CONSISTENCY_CHECK.mdのT011-02をcurrent実装前提とする表現を、historical 5-setting implementation / T011-03 current 2-setting correctionへ同期した
- DB_SCHEMA.md §11.3 A-05〜A-08は、canonical反映済み状態とapplication follow-upを分離した
- table / column / type / constraint / default / relation / index / deletion semantics / scoring / migration designは変更していない

#### F-05: OI-017 tense

- resolved OI-017の確定先を、完了済みT003-05による確認・必要最小限の反映済みというhistorical completion factへ時制同期した
- remember me、session lifetime、`SESSION_EXPIRE_ON_CLOSE`、`remember_token`、UI方針は変更していない

### Completion state after extended correction

- T000-09 completion decision: HOLD
- extended correction applied
- independent actual diff review / user approval / final re-audit pending
- FAIL 0 / UNVERIFIED 0を確認するまでT000-09をcompleteにしない
- current stopping point: correction applied / actual diff review pending

## 18. Actual diff review finding / micro-correction

### Review evidence / result

- reviewed patch SHA-256: `9CB9F02551F9B9A841212E478B93DA5C932BBE16FAD993FED8A8AF1C9EFE4370`
- actual diff review result: HOLD
- CodeXによるactual diff export時のPotential issuesは`None`だったが、ChatGPTによるdiff本文のdirect reviewで追加Findingを検出した
- review中のrepository変更: なし
- micro-correction: 適用済み
- actual diff re-review: completed

### Findings

1. T013-06 provenance metadataで、original addition時点のhistorical snapshotとcurrent task state / current roleを混同していた
2. resolved OI-022 / OI-023の確定先が、current canonical反映済み・application correction未完了という現在状態に対してstaleだった
3. CONSISTENCY_CHECK.mdの`要確認（OI 依存）`見出しが、resolved decision後のimplementation / migration / actual environment確認というsection本文の現在責務に対してstaleだった
4. actual diff review Finding、再発防止、User TASKS.md Direct Review Gateのchronology記録が必要だった

### Root cause / recurrence prevention

Root causeは、historical task stateをcurrent task stateの判断へ混入させたことである。

1. correction開始時にcurrent working treeのTASKS stateを固定する
2. completed taskをprotected stateとして扱い、state・完了日・completion evidenceを変更しない
3. Git historyはprovenance / historical snapshot / renumber historyの確認に限定する
4. historicalな`未着手`をcurrent task stateへ伝播させない
5. `追加時点の章状態（historical snapshot）`とcurrentの`位置づけ`を分離する
6. correction後にcompleted-state regressionを独立確認する
7. task / dependency / OI件数等のmachine validationはsemantic reviewの補助として用いる

### Final Completeness Audit Finding Register

| ID | Finding | Status | 解消確認 |
|---|---|---|---|
| F-001 | T013-11 / OI-010 acceptance design owner不足 | `RESOLVED` | 承認済みcorrectionをactual diff直接レビューし、ユーザー承認済み |
| F-002 | T015-02-01 / OI-111 decision owner不足 | `RESOLVED` | 承認済みcorrectionをactual diff直接レビューし、ユーザー承認済み |
| F-003 | TASKS全体のID順と実行順の説明不足 | `RESOLVED` | 承認済みcorrectionをactual diff直接レビューし、ユーザー承認済み |
| F-004 | verification artifactの正式進行順とcurrent handoffの不一致 | `RESOLVED` | 承認済みcorrectionをactual diff直接レビューし、ユーザー承認済み |
| F-005 | T014〜T017のcurrent taskへのlater-added provenance rule過剰適用 | `RESOLVED` | correction actual diffをChatGPTが直接レビューしてtechnical PASSと判定し、ユーザー承認済み |

#### F-005: T014〜T017 provenance metadata correction

- chapter: T014〜T017
- issue: T000-09監査時、later-added provenance ruleを14〜17章の未着手current taskへ過剰適用し、`追加区分`、`追加commit`、`追加起点`、`追加前章状態`、`位置づけ`等をtask本文へ追加した
- impact: current taskとhistorical provenanceが混在し、current developですでに存在するtaskが今回追加されたtaskであるかのように誤読可能だった
- fact: PR #72で新規task blockとして登録された14〜16章のtaskは、T014-06-01、T015-01-01、T015-02-01、T015-03-01、T015-03-02、T016-01-01、T016-02-01、T016-05、T016-06の9件
- approved correction: 上記9件は`TASKS追加日: 2026-08-13T19:37:47+09:00`のみ保持し、詳細provenanceはTASKS本文から除去する
- historical evidence: 詳細な追加commit / PR / 起点 / historical snapshotはverification artifactおよびGit履歴で保持する
- actual diff review: ChatGPT direct review completed / technical result `PASS`
- changed files: `docs/TASKS.md`、`docs/verification/t000-09/TASKS_RESPONSIBILITY_MIGRATION.md`
- reviewed diff: `C:\temp\T000-09-post-direct-review-provenance-correction.diff`
- size: 10,833 bytes
- SHA-256: `db17ca61fb1b093b85130816320d8c9413725f3573081a625437f9a9944301bc`
- TASKS diff: 0 insertions / 45 deletions。指定9 taskから5 provenance metadata行ずつを削除した変更のみ
- invariant check: 9件の`TASKS追加日`、T015-02-01 decision phase、OI-111 timing / dependency条件を含む実装内容補正を維持。dependency、task ID、task title、task state、completed task regressionはいずれも変更0件
- protected file check: `docs/STAGE_A_SCORING.md`変更なし
- diff check: `git diff --check` PASS
- user judgment: correction diff approved
- status: `RESOLVED`

- reviewed diff: `C:\temp\T000-09-post-finding-correction.diff`
- size: 59,526 bytes
- SHA-256: `801d812b4ed7b49d127d8f1e5d9e6316e1fc8a13790a450834367320b831abd6`
- stat: 8 files changed, 384 insertions(+), 69 deletions(-)

### User TASKS.md Direct Review Gate

正式な後続順序は次のとおりとする。

1. Startup Audit Workflow
2. TASKS全文監査
3. Finding Register確定
4. ユーザー判断
5. correction plan
6. ユーザー承認
7. CodeX correction
8. actual diff直接レビュー
9. ユーザー承認
10. Status Sync
11. Status Sync diff確認
12. ユーザー承認
13. correction commit
14. push / PR #73 update
15. STOP
16. ユーザーがGitHub上の `docs/TASKS.md` を直接確認
17. ユーザー承認
18. final independent re-audit
19. completion record finalization
20. final PR review
21. merge判断

push後は必ずUser TASKS.md Direct Review Gateで停止し、final re-auditへ自動進行しない。

### Current state

- T000-09 completion decision: HOLD
- Finding correction: F-001〜F-005 resolved
- correction diff: F-001〜F-004 direct review completed / user approved; F-005 direct review completed / technical PASS / user approved
- final independent re-audit: not yet executed
- Status Sync: completed
- F-005 Status Sync: completed
- User TASKS Direct Review Gate: HOLD / F-005 correction diff user-approved / remote reflection and GitHub re-review pending
- current stopping point: User TASKS Direct Review Gate HOLD / remote reflection and GitHub re-review pending
