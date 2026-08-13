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
