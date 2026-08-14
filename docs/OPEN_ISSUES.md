# OPEN_ISSUES.md

> **目的: 未確定事項の唯一の管理台帳**
> **最終更新: 2026-08-13（T000-09 Phase 3: 対象フェーズ管理と OI-109〜OI-112 の登録）**

---

## 本文書の位置づけ

本文書は、プロジェクト全体（フェーズ①〜③）を通して使う未確定事項の管理台帳である。
業務判断待ち、PoC 結果待ち、運用詳細未定、費用上限未定などの項目は、設計本文に混在させずここで一元管理する。

### 管理原則

- **1 ID = 1 管理項目。** 他文書へ全文重複させない
- 各設計文書からは ID 参照のみ行う（例: 「OI-012 で管理」）
- 確定した項目は「解消済み」セクションへ移動し、ID の連続性を維持する
- 新規追加時は末尾に採番する
- 未解消項目は、優先度とは別に対象フェーズを `MVP` / `R1` / `F1` / `MVP/R1 split` で管理する
- `R1` は本番リリースまでに必要な項目、`F1` は本番後の将来拡張を表す。MVP完了判定へ混在させない
- `MVP/R1 split` は、MVPテスト環境で必要な最小判断と、本番固有の判断を同じID内で明示的に分離する
- 優先度は対象フェーズ内での優先度であり、`R1` の「中」をMVP結合テスト前の必須条件とは読まない

### 優先度基準

| 優先度 | 定義 |
|---|---|
| **高** | 対象フェーズのrelease / acceptanceまでに確定必須。未確定だと対象フェーズの実装・受入をblockする |
| **中** | 対象フェーズの実装開始は可能だが、対象フェーズのintegration / operational acceptance前には確定が必要 |
| **低** | 対象フェーズ内で後続判断可能だが、設計時に認識しておくべき |

### 対象フェーズ管理

| 対象フェーズ | 意味 |
|---|---|
| `MVP` | フェーズ①の実装・テスト・MVP判定までに処理する |
| `R1` | フェーズ②の本番構築・本番リリースまでに処理する |
| `F1` | 本番後の将来拡張として管理する |
| `MVP/R1 split` | MVP最小条件と本番固有条件を分け、各側のtask/gateで処理する |

### MVP実装前の確認観点

CodeX での MVP 実装に入る前に、以下の観点で本台帳を確認する。

- DB マイグレーション、Seeder、認証フロー、音声評価E2Eに影響する項目は実装ブロック要因として扱う
- Feature Flag OFF のまま実装可能な項目と、PoC 後に有効化判断が必要な項目を区別する
- 対象フェーズの実装開始は可能でも、同フェーズのintegration / operational acceptance前に確定が必要な項目は優先度「中」以上で管理する
- 実装順序のみの課題は本台帳ではなく、実装タスク側で扱う

---

## 未確定事項一覧

### アーキテクチャ・設計

| ID | カテゴリ | 内容 | 影響範囲 | 対象フェーズ | 確定予定 | 優先度 |
|---|---|---|---|---|---|---|
| OI-001 | アーキ | Scheduler の具体的な用途リスト（CleanupTempFilesJob 以外の定期ジョブを含む最終構成） | OPERATIONS.md 定期運用 | MVP/R1 split | 運用整理時 | 中 |
| OI-002 | アーキ | FastAPIのconfigured endpoint/port管理。MVP non-productionでは環境設定値とLaravel client / Python起動 / health checkの一致を確認し、productionで標準化する値・proxy構成は後続判断とする | ARCHITECTURE.md §4, T013-06, Nginx 設定, FastAPI 起動設定 | MVP/R1 split | MVP E2E前 / production構築時 | 中 |
| OI-003 | 運用 | Queue ドライバ database → Redis 移行トリガー条件の数値精緻化 | ARCHITECTURE.md §2, OPERATIONS.md | MVP/R1 split | 性能検証時 | 中 |

### 仕様・UX

| ID | カテゴリ | 内容 | 影響範囲 | 対象フェーズ | 確定予定 | 優先度 |
|---|---|---|---|---|---|---|
| OI-006 | UX | STT 認識不可時（422）のユーザー向け UX 方針の詳細。現状は「再提出案内」のみ定義。再録音導線、ユーザー向け説明文言、エラー表示方針を実装前に確定する | DESIGN.md, フロントエンド実装, 音声提出フロー | MVP | 実装前 | 高 |

### 音声・Azure AI Speech

| ID | カテゴリ | 内容 | 影響範囲 | 対象フェーズ | 確定予定 | 優先度 |
|---|---|---|---|---|---|---|
| OI-010 | 音声 | Azure Speech SDK の continuous recognition の安定性検証（長時間音声での途切れ・タイムアウト・再試行影響）。長時間音声での検証結果は音声評価E2E成立の前提として扱う | 音声評価サービス全体, 音声提出E2E | MVP | PoC 時 | 高 |
| OI-012 | 事業判断 | Pronunciation Assessment PoC の実施タイミングと Go/No-Go 基準の最終合意。PoC 完了までは Feature Flag OFF を維持し、PoC 後に有効化可否を判断する | Feature Flag 運用, 費用試算, 結果表示 | MVP | 実装前 | 高 |
| OI-014 | コスト | Azure AI Speech の月額予算上限値の確定 | 費用管理, アラート設定 | R1 | 運用準備時 | 中 |
| OI-021 | 運用 | 音声残存ファイル削除用 CleanupTempFilesJob の実行頻度および削除対象条件。即時削除失敗時の回復手段として、実行頻度だけでなく削除対象の経過時間条件も確定する | Scheduler, ストレージ管理, 音声一時ファイル削除 | MVP/R1 split | 実装前 | 中 |

### セキュリティ・認証

| ID | カテゴリ | 内容 | 影響範囲 | 対象フェーズ | 確定予定 | 優先度 |
|---|---|---|---|---|---|---|
| OI-007 | セキュリティ | 内部通信トークン（X-Internal-Token）のローテーション方針・頻度 | Laravel ⇔ Python 通信 | R1 | セキュリティ運用整理時 | 低 |
| OI-013 | セキュリティ | Azure API キーのローテーション運用手順（Key1 / Key2 切替の具体的手順） | 音声評価サービス | R1 | 運用準備時 | 低 |

### 性能

| ID | カテゴリ | 内容 | 影響範囲 | 対象フェーズ | 確定予定 | 優先度 |
|---|---|---|---|---|---|---|
| OI-004 | 監視 | ログファイルの物理配置パスの最終確定 | OPERATIONS.md 点検手順 | R1 | 実装時 | 低 |
| OI-008 | 性能 | Queue database ドライバ利用時のポーリング間隔 3秒の妥当性検証。同時アクセス数、ポーリング頻度、DB 負荷の観点で検証し、必要に応じて間隔・最大回数・Redis 移行条件を見直す | フロントエンド, DB 性能, Queue 運用 | MVP | 性能検証時 | 中 |

### リリース・運用

| ID | カテゴリ | 内容 | 影響範囲 | 対象フェーズ | 確定予定 | 優先度 |
|---|---|---|---|---|---|---|
| OI-018 | リリース | Git ブランチ戦略。MVPではtask branch → PR → review → `develop`統合を運用し、本番のmain反映・release・deployment・rollbackは後続で確定する | 開発フロー全体, CodeX実装作業 | MVP/R1 split | MVP workflowは運用中、production flowはR1 | 高 |
| OI-019 | 監視 | 障害通知チャネルの最終決定（メール / Slack / その他） | OPERATIONS.md 障害対応 | R1 | 運用準備時 | 中 |
| OI-020 | バックアップ | 外部バックアップ保管先、暗号化方式、復旧責任者の最終確定 | OPERATIONS.md バックアップ運用 | R1 | 運用準備時 | 中 |

### 事業・法務（DB_SCHEMA.md 由来）

| ID | カテゴリ | 内容 | 影響範囲 | 対象フェーズ | 確定予定 | 優先度 |
|---|---|---|---|---|---|---|
| OI-101 | 事業 | 年額プランの導入時期と価格 | Stripe 設定, subscriptions | F1 | MVP 後 | 低 |
| OI-102 | 請求 | PDF 領収書テンプレート要否（現状は Stripe 自動送信に委譲） | Stripe 設定 | F1 | MVP 後 | 低 |
| OI-103 | 法務 | インボイス制度対応の要否（国内販売要件に応じて判断）。production release前に要否を判断し、法的に不要と正式判断した場合は追加implementationを要求しない。custom PDF / invoice UI等の追加拡張はF1になり得る | Stripe 設定, 法務ページ | R1 | production release準備時 | 低 |
| OI-104 | 運用 | 管理者 seed の初期パスワード管理方式（.env / 手動入力 / Secret 管理を比較）。non-production credential投入とproduction Secret運用を分離する | AdminUserSeeder, 初期セットアップ | MVP/R1 split | non-production実装前 / production構築前 | 高 |
| OI-105 | 運用 | 30日後 hard delete 実行主体（バッチ or 手動運用）。soft delete 後30日経過ユーザーの hard delete を、バッチで行うか手動運用で行うかは退会フローとSchedulerに影響する | 退会フロー, Scheduler | MVP | 実装前 | 中 |
| OI-106 | 仕様 | 規約更新時の再同意フロー（MVP 対象外、将来対応） | consents テーブル | F1 | MVP 後 | 低 |
| OI-107 | 非機能 | `raw_azure_response` が 500KB を超える場合の保持方針（切り捨て / 要約 / 未保存）。500KB超過時の扱いは評価保存処理に影響するため、実装前に方針を確定する | evaluations テーブル, 評価保存処理 | MVP | 実装前 | 高 |
| OI-108 | 設計 | 利用規約 / プライバシーポリシー最新バージョンの永続管理方式（専用テーブル追加要否） | consents, アプリ設定 | F1 | MVP 後 | 低 |

### UI設計・DESIGN.md 由来

| ID | カテゴリ | 内容 | 影響範囲 | 対象フェーズ | 確定予定 | 優先度 |
|---|---|---|---|---|---|---|
| OI-024 | UI設計 | ナビゲーション最終構成。ボトムナビに含める項目、ハンバーガーメニューの採否、PC サイドバーとの項目対応を確定 | DESIGN.md §6, フロントエンド実装 | MVP | 実装前 | 中 |
| OI-025 | UI設計 | デザイントークンの確定。カラーパレット（役割ベースの色定義）、タイポグラフィ（フォントファミリー・サイズ体系）、アイコン体系（ライブラリ選定含む）に加え、Tailwind 実装時に必要な最小トークン値を確定する | DESIGN.md §3, フロントエンド実装, Tailwind 設定 | MVP | 実装前 | 高 |
| OI-026 | 仕様 | 学習管理画面（統計カード, カレンダー, 連続日数）は MVP 対象外。将来実装時の残タスクとして記録。実装時には集計設計・表示項目定義が必要 | DESIGN.md, DB設計（集計テーブル追加の可能性） | F1 | MVP後 | 低 |

### 課金・管理画面（MVP実装前レビュー由来）

| ID | カテゴリ | 内容 | 影響範囲 | 対象フェーズ | 確定予定 | 優先度 |
|---|---|---|---|---|---|---|
| OI-027 | 課金 | MVPで処理対象とする Stripe Webhook イベントの最小範囲。候補は `customer.subscription.created` / `customer.subscription.updated` / `customer.subscription.deleted` / `invoice.payment_failed`。署名検証、Queue処理、契約状態同期への影響を含めて確定する | Stripe Webhook, Queue, subscriptions, 契約状態同期 | MVP | 実装前 | 高 |
| OI-028 | 管理画面 | MVP 管理画面で実装する最小範囲の確定。候補はユーザー閲覧、問題管理の最小CRUD、提出/評価閲覧、Stripe契約状態閲覧。初期実装で多機能化しないためのスコープ確認項目として管理する | DESIGN.md 管理画面, admin ロール, フロントエンド実装 | MVP | 実装前 | 高 |

### 退会・Stage-A横断契約（T000-09レビュー由来）

| ID | カテゴリ | 内容 | 影響範囲 | 対象フェーズ | 確定予定 | 優先度 |
|---|---|---|---|---|---|---|
| OI-109 | 退会 | 退会時のpending / processing submissionおよび実行中Queue Jobとの競合制御。wait / reject / job cancel / force fail / audio handlingのいずれを採るかは未確定 | ARCHITECTURE.md, DB_SCHEMA.md, OPERATIONS.md, T015-02, T015-04 | MVP | T015-02前 | 高 |
| OI-110 | 退会 | Stripe解約予約、audio cleanup、sessions削除、soft deleteからなる退会オーケストレーションの部分失敗・補償・中断境界 | ARCHITECTURE.md, DB_SCHEMA.md, OPERATIONS.md, T015-02, T015-04 | MVP | T015-02前 | 高 |
| OI-111 | 通知 | 退会完了通知の送信timing。soft delete成立時、外部処理完了時等の具体条件は未確定 | ARCHITECTURE.md, DESIGN.md, OPERATIONS.md, T015-02-01 | MVP | 通知実装前 | 中 |

---

## 解消済み

以下は方針確定済みの項目。ID 参照の連続性確保のため記録を残す。

| ID | 状態 | 内容 | 確定先 |
|---|---|---|---|
| OI-005 | 解消済み | Azure SDK Python 版での WebM/Opus 直接入力可否 | ARCHITECTURE.md §9.2「WAV 変換方式を採用」 |
| OI-009 | 解消済み（2026-07-31 確定） | 評価プロファイルの値域は10 / 40 / 60 / 90 / 120秒とする。10秒スピーチチャレンジでは問題データ上の初期評価プロファイルを10秒、それ以外の問題では60秒とする。問題データへ初期評価プロファイルを明示保存し、問題を開いた時点ではその値を選択済みとして表示する。表示時にタグ文字列から10秒スピーチチャレンジかどうかを動的判定しない。録音画面では10 / 40 / 60 / 90 / 120秒のプルダウンから変更可能とする。提出時には録音開始前に最終選択されていた評価プロファイルをsubmissionへ保存し、保存した評価プロファイルを録音上限、採点、結果表示へ使用する。Queue処理や結果表示時に現在の問題設定や現在のユーザー設定を再取得して採点条件を変更しない。設定画面には全問題共通のスピーチ時間設定を持たない | DB_SCHEMA.md, ARCHITECTURE.md, DESIGN.md, 音声評価処理、後続補正タスク |
| OI-016 | 解消済み（2026-07-09 確定） | MVPでは、Google OAuthログイン時に既存メールアドレス一致ユーザーを自動リンクしない。既存メール一致かつ `google_id` 未紐づけの場合は `google_id` を保存せず、通常ログイン導線へ安全停止する。新規Googleユーザー作成と既存 `google_id` ユーザーのGoogleログインはMVP対象とする。手動アカウント連携UIはMVP対象外とし、将来の拡張候補とする。 | T003-04 / T013-01 |
| OI-017 | 解消済み（2026-06-08 確定、実装前に確定済み） | MVPでは remember me は採用しない。セッション有効期限は 120分 とする。`SESSION_EXPIRE_ON_CLOSE` は `false` とし、ブラウザ終了時の強制ログアウトは行わない。`users.remember_token` は Laravel 標準カラムとして維持するが、MVP UIでは remember me チェックボックスを表示しない。 | T003-05で本方針に従い、`config/session.php` / ログイン画面を確認し、必要最小限の反映を実施済み |
| OI-022 | 解消済み（2026-07-31 確定） | 問題形式の内部値は `single_prompt` / `two_choice` を維持する。`single_prompt` は1つの設問について1件のスピーチを提出する形式、`two_choice` は2つのテーマから話したい方を1つ選び、選んだテーマについて1件のスピーチを提出する形式とする。`two_choice` は正解・不正解を選択する問題ではなく、正解番号、正解・不正解、テーマごとの得点を持たず、テーマ別の評価結果も管理しない。提出・評価は常に1提出・1評価とする。表記は、DB・API・コードの内部値を `two_choice`、正本文書上の内部仕様説明を「二テーマ選択」、ユーザー向けメニュー表示を「2択」、問題画面の案内を「2つのテーマから、話したい方を選んでください。」とする。設問文は `questions.prompt_text` / `questions.prompt_text_1` / `questions.prompt_text_2` に保存する。`single_prompt` は `questions.prompt_text` を使用し、`questions.prompt_text_1` / `questions.prompt_text_2` は使用しない。`two_choice` は `questions.prompt_text_1` / `questions.prompt_text_2` を使用し、二テーマ選択専用テーブルは追加しない。提出時には実際に使用された設問文だけを `submissions.prompt_snapshot` へ保存する。`selected_topic_id`、テーマ専用ID、テーマ専用テーブル、テーマ別結果管理は追加しない。DB、API、UI、migration、テストは後続補正が必要であり、現時点で実装補正済みとは扱わない | `OPEN_ISSUES.md`で方針確定し、`DB_SCHEMA.md` / `ARCHITECTURE.md` / `DESIGN.md`へcurrent canonicalを反映済み。application / API / UI / migration / testの未反映差分はT004-04等の後続correction taskで扱う |
| OI-023 | 解消済み（2026-07-31 確定） | 学習設定の保存先は `user_learning_settings` テーブル方式を維持し、保存項目は「出題方式」「タイマー表示方式」の2項目とする。`users` JSONB方式は採用しない。旧設定のうち「スピーチ時間」は設定画面から廃止し、問題ごとの初期評価プロファイルを録音画面で選択する。「強制終了ON/OFF」はユーザー設定を廃止し、評価プロファイル別の録音上限監視を常に有効とする。「文字起こし表示ON/OFF」はユーザー設定を廃止し、文字起こしを常時表示する。T011-02の過去の完了履歴は維持する。今回は確定仕様の台帳上の配置補正であり、設定UI、API、Model、validation、DB、migration、テストは後続補正が必要で、現時点で実装補正済みとは扱わない | `OPEN_ISSUES.md`で方針確定し、`DB_SCHEMA.md` / `ARCHITECTURE.md` / `DESIGN.md`へcurrent 2-setting canonicalを反映済み。historical T011-02との差分を含むapplication / API / UI / migration / testの補正はT011-03で扱う |
| OI-029 | 解消済み（ユーザー承認済み） | 表示用transcriptと採点用文字列を分離し、採点元を各final segmentの `NBest[0].Lexical` とする。NFC正規化後にwhitespace / `P*` / `C*`を除外する `ja-jp-character-count-v1` を採用し、Lexical取得不能時はDisplay等へfallbackせずStage-A response contract failureとする | 判断履歴は `docs/verification/t000-05/OI-029_DECISION.md`。production実装、最終DB保存構造、migration、API error contractは後続タスクの責務 |
| OI-030 | 解消済み（ユーザー承認済み） | MVP対象 `env-a` / `env-b` / `env-c` / `env-d` のproduction共通technical marginを `0.07秒` とする。元WebMのdurationを`D`、submissionへ固定保存したprofileを`P`とし、Azure送信前に `D <= P + 0.07` を上限内、`D > P + 0.07` を上限超過と判定する。marginは回答時間、UI timer、auto stop、time_score、timeout等へ加算しない | 判断履歴・計測詳細は `docs/verification/t000-06/OI-030_DECISION.md`。production実装、最終DB保存構造、API error contractは後続タスクの責務 |
| OI-031 | 解消済み（2026-08-12 承認済み） | Stage-A採点仕様を確定した。`final_score = min(character_score, time_score)`、pass thresholdは60、初期scoring versionは `stage-a-scoring-v1` とし、character-only暫定採点を禁止する。再採点ではAzure STTを再実行せず、historical submissionの保存済み事実を使用する | 現行仕様の正本は `docs/STAGE_A_SCORING.md`、判断履歴は `docs/verification/t000-07/OI-031_DECISION.md`。最終DB保存構造、migration、実装、bulk rescoreの具体運用は後続タスクの責務 |
| OI-112 | 解消済み（2026-08-14 ユーザー承認済み） | Stage-A cross-layer error contractを確定した。Evaluationありのpass / failは`completed`、Evaluationなしterminal outcomeは`failed`、retry中は`processing`とし、新submission statusは追加しない。failureはtop-level category、subtype、safe message、safe user action、terminal classificationからなるstable machine-readable semantic contractで区別し、internal diagnosticはpublic API / UIから分離する。pre-Azureは`pre_azure_upper_limit` / `not_scored`、backend timeoutは`timeout` / `connect`・`read`・`azure`、genericは`system_failure` / `unexpected`、Lexical missingは`stage_a_fact_failure` / `lexical_missing`とする。Lexical missingはHTTP `500` / response status `error`、non-retryable、terminal `failed`、Evaluationなし、Result不可、fallbackなしとし、`speech_unrecognized`へ統合しない。retryはsemantic classification / retry policyをprimary、HTTP statusをsupporting / fallbackとし、initial 1 attempt＋max 3 retries＝max 4 total attempts、backoff 30 / 60 / 120秒とする。connect timeoutは最初のretry前に`/health`を確認し、無応答なら即terminal `failed`とする。frontend polling timeoutはfrontend-local stateで、backend submissionを`failed`へ変更せずbackend Jobを停止せず、later status re-fetchを可能とする。OI-030の`D > P + 0.07`ではAzure未呼出し、採点・Evaluationなし、`failed`＋`not_scored`、retryなしとする | canonical detailは`ARCHITECTURE.md` / `DB_SCHEMA.md` / `DESIGN.md` / `OPERATIONS.md`へ反映済み。T007-06 / T008-05 / T009-06でimplementationし、T013-08 / T013-09でautomated / runtime verificationする。本項は仕様解消であり、implementation verifiedを意味しない |

### OI-011: コメントテンプレートの具体的文面

> 履歴注記: 以下はT010-02当時に確定したtemplate commentの履歴である。現行Stage-A productionではtemplate commentを生成・保存・表示しない。Stage-Bの具体実装方式は後続タスクで決定する。

- 状態: 解消済み
- 確定日: 2026-06-25
- 位置づけ: MVP初期値
- 目的:
  - T010-02 `TemplateCommentGenerator` 実装に必要な初期コメント文面を用意する
- 方針:
  - T010-02では、設問本文・模範解答・設問別制限時間には依存しない
  - コメントは、速度カテゴリ × 実発話時間カテゴリの汎用フィードバックとする
  - 実際の設問内容への適合性、模範解答との差分、内容評価はT010-02の対象外
  - 文面はMVP初期値であり、実データ確認後に修正可能
  - 実装時は `config/comment_templates.php` に反映し、DBスキーマには固定しない
- カテゴリ:
  - speed: `slow` / `appropriate` / `fast`
  - duration: `short` / `medium` / `long`
- fallback:
  - 評価に必要な情報が一部不足しているため、今回は全体的なコメントのみ表示します。録音内容を確認し、もう一度提出するとより詳しい評価ができます。
- テンプレート:
  - slow × short
    1. ゆっくり丁寧に話せています。短い発話なので、次は少し長めに話す練習をしてみましょう。
    2. 発音を確認しながら落ち着いて話せています。次は同じ速さで、もう一文加えてみましょう。
    3. 聞き取りやすさを意識できています。短く終わらず、理由や例を少し足すとさらに良くなります。
  - slow × medium
    1. 落ち着いた速さで話せています。少し間が長くなりやすいので、文と文のつながりを意識しましょう。
    2. 丁寧に話せています。次は同じ内容を、少しだけテンポよく話す練習をしてみましょう。
    3. 内容は伝わりやすいです。自然な会話に近づけるため、短い間を減らすことを意識しましょう。
  - slow × long
    1. 長い内容を最後まで話せています。全体的にゆっくりなので、重要な部分を保ちながら少しテンポを上げましょう。
    2. 丁寧さがあります。長い発話では、文の区切りを意識して、聞き手が追いやすい流れにしましょう。
    3. 粘り強く話せています。次は同じ内容を少し短い時間で話す練習をすると、流暢さが上がります。
  - appropriate × short
    1. ちょうどよい速さで話せています。短い発話なので、次は理由や具体例を一つ加えてみましょう。
    2. 自然なテンポで話せています。もう少し内容を広げると、より評価しやすくなります。
    3. 聞き取りやすい速さです。次は同じテンポで、少し長い文に挑戦しましょう。
  - appropriate × medium
    1. 速さと長さのバランスが良く、聞き取りやすい発話です。この調子で内容の具体性を高めましょう。
    2. 自然なテンポで話せています。文のつながりをさらに意識すると、より流暢に聞こえます。
    3. 全体として安定した発話です。次は語彙や表現の幅を少し増やしてみましょう。
  - appropriate × long
    1. 長い内容を自然な速さで話せています。構成を意識すると、さらに伝わりやすくなります。
    2. 十分な長さを保ちながら、聞き取りやすいテンポで話せています。次は結論を明確にしましょう。
    3. 安定して話せています。長い発話では、話題の切り替わりを少しはっきりさせると良くなります。
  - fast × short
    1. 短い発話ですが、少し速く聞こえます。次は一語ずつはっきり発音することを意識しましょう。
    2. テンポよく話せていますが、短い中でも少し急いで聞こえます。落ち着いて話す練習をしましょう。
    3. 反応は速いです。次は速さを少し抑えて、発音の明瞭さを意識しましょう。
  - fast × medium
    1. 内容は伝わりますが、少し速めです。大事な語句の前後で少し間を取ると聞き取りやすくなります。
    2. 勢いよく話せています。次は速さを少し抑えて、文の終わりをはっきりさせましょう。
    3. 発話量は十分です。聞き手に伝わりやすくするため、重要な部分をゆっくり言う練習をしましょう。
  - fast × long
    1. 長い内容を話せていますが、全体的に速めです。文の区切りで少し間を取ると、より伝わりやすくなります。
    2. 発話量は多く、積極的に話せています。次は速さを調整して、聞き取りやすさを高めましょう。
    3. たくさん話せている点は良いです。長い発話では、急ぎすぎず、要点ごとに区切ることを意識しましょう。

### OI-015: 速度判定の閾値

> 現行注記: `slow` / `appropriate` / `fast` の補助分類自体は維持するが、Stage-Aの`final_score`へ反映せず、現行Stage-Aでtemplate comment生成へ使用しない。

- 状態: 解消済み
- 確定日: 2026-06-25
- 位置づけ: MVP初期値
- 判定対象:
  - `characters_per_minute`
- カテゴリ:
  - `slow`
  - `appropriate`
  - `fast`
- MVP初期閾値:
  - `slow`: `characters_per_minute < 180`
  - `appropriate`: `180 <= characters_per_minute <= 320`
  - `fast`: `characters_per_minute > 320`
- 境界値:
  - 179以下: `slow`
  - 180以上320以下: `appropriate`
  - 321以上: `fast`
- 方針:
  - T010-02では、この値を `config/comment_templates.php` に反映する
  - DBスキーマには固定しない
  - migration / Seeder は変更しない
  - 実Azure / 実音声評価データ確認後に調整可能

### T010-02用 音声長カテゴリ

- 状態: MVP初期値として確定
- 判定対象:
  - `duration_seconds`
- カテゴリ:
  - `short`
  - `medium`
  - `long`
- MVP初期閾値:
  - `short`: `duration_seconds < 30`
  - `medium`: `30 <= duration_seconds < 90`
  - `long`: `duration_seconds >= 90`
- 境界値:
  - 30秒未満: `short`
  - 30秒以上90秒未満: `medium`
  - 90秒以上: `long`
- 方針:
  - これは「実際に話した長さ」の分類である
  - 設問ごとの制限時間または推奨時間ではない
  - `questions.recommended_duration_seconds` とは別扱いにする
  - T010-02では `durationSeconds` を使った汎用コメント生成に限定する

### T010-02で扱わないこと

T010-02 の初期コメント生成では、以下は扱わない。

- 実際の設問本文
- 模範解答
- 設問ごとの推奨回答時間
- 設問ごとの制限時間
- 回答内容が設問に合っているかの判定
- 内容評価
- LLMによる自由記述コメント
- Azure OpenAI / OpenAI 連携

これらは、将来の内容評価コメント、模範解答比較、またはLLMコメント生成を扱うタスクで必要になった時点で別途確定する。

T010-02では、`durationSeconds` と `charactersPerMinute` を使った汎用テンプレートコメント生成に限定する。T010-02実装は次ステップで扱う。

---

## 確定前提（本台帳の対象外）

以下は確定事項として扱い、本台帳には含めない。変更が必要な場合は本台帳に新規 ID を起票する。

- Azure AI Speech 前提: japaneast / Standard (S0) / MVP 確定利用 STT / PoC 対象 Pronunciation Assessment
- PostgreSQL 16 / UTF-8
- `questions.has_model_answer: boolean` 採用
- `questions.question_type` 不採用
- 音声ファイル非永続保存
- 退会フロー4段階: Stripe 解約 → 音声削除 → soft delete → 30日後 hard delete
- Stripe: 単一プラン Standard / 月額660円（税込）/ 7日間トライアル / クレジットカードのみ
