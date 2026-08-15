# CLAUDE.md

> このファイルは Claude / Claude Code がこのリポジトリで作業するための規約・前提・現状を定義する。
> 作業開始時に必ず読むこと。
>
> **作成: 2026-08-15（ChatGPT + Codex から Claude / Claude Code への開発引継ぎに伴う現状分析の結果）**
> **基準コミット: `58a33b1`（`develop`）**

---

## 0. 最初に知るべき5つのこと

1. **作業ブランチは `develop`。`main` は README のみで 258 コミット遅れている。** `main` を現状把握の材料にしない。
2. **このリポジトリは「設計文書が先行し、実装が追随する」構造で進んでいる。** 設計文書（`docs/`）は 2026-08-14 まで更新されているが、`database/` と `app/Models/` は **2026-06-29 で停止**している。両者の差は「バグ」ではなく**未着手の後続タスク**である。
3. **仕様を勝手に決めない。** 未確定事項は `docs/OPEN_ISSUES.md` の OI-xxx で管理され、確定はユーザー承認を必要とする。
4. **1回の作業依頼＝原則1タスク（Task ID 単位）。** タスクは `docs/TASKS.md` に 111 件定義されている（66 件完了 / 45 件未着手）。
5. **完了済みタスクの記述を現行仕様へ書き換えない。** 完了記録は当時の履歴であり、現行仕様との差分は後続の補正タスクで回収する設計になっている。

---

## 1. プロジェクト概要

日本語スピーチ練習アプリ（`modernspeech` / `manabuzo-speech`）の MVP。
ユーザーが出題に対して音声で回答し、Azure AI Speech の STT 結果をもとに **Stage-A 採点**（文字数 × 発話時間）を行う。

- フェーズ①: MVP 作成とテスト環境の検証 ← **現在ここ。詳細設計の対象はこの範囲のみ**
- フェーズ②: 本番環境構築（`R1`）
- フェーズ③: 運営（`F1` を含む将来拡張）

### 技術スタック

| レイヤー | 技術 |
|---|---|
| フロント | Vue 3 + Inertia.js（SSR なし）+ Tailwind CSS 3 |
| アプリ | Laravel 11 / PHP 8.2+ |
| 音声評価 | Python 3.11+ / FastAPI + Azure Speech SDK |
| DB | PostgreSQL 16（UTF-8） |
| Queue | Laravel Queue（`database` ドライバ） |
| 課金 | Stripe（**未実装**。`laravel/cashier` は未導入） |
| インフラ | さくら VPS 単一構成 / Nginx |

### ドメイン

- 本番 `speech.manabuzo.jp`（`main`）/ 開発 `dev.manabuzo.jp`（`develop`）

---

## 2. 文書体系 — 正本（canonical）ルール

**この体系を壊さないことが、このリポジトリで最も重要な規約である。**

| 文書 | 責務（正本の範囲） |
|---|---|
| `README.md` | プロジェクト全体像・フェーズ定義の入口文書 |
| `docs/ARCHITECTURE.md` | システム設計判断のハブ文書。通信方式・ジョブ・エラー契約・セキュリティ |
| `docs/DB_SCHEMA.md` | DB 設計の正本（18テーブル / 5カテゴリ） |
| `docs/STAGE_A_SCORING.md` | Stage-A 採点表・境界・version・再採点 semantics の正本 |
| `docs/DESIGN.md` | UI/UX 設計の正本 |
| `docs/OPERATIONS.md` | 運用手順（MVP 向け最小運用） |
| `docs/OPEN_ISSUES.md` | **未確定事項の唯一の管理台帳**（OI-xxx） |
| `docs/TASKS.md` | 実装タスクの実行計画（仕様書の代替ではない） |
| `docs/CONSISTENCY_CHECK.md` | 文書間整合の確認結果。primary spec にしない |
| `docs/verification/**` | 検証証跡（non-canonical）。判断履歴であり仕様正本ではない |

### 厳守事項

- **仕様本文を `TASKS.md` へ複製しない。** タスクには正本文書名と OI ID の参照だけを書く。
- **未確定事項を設計本文へ書かない。** `OPEN_ISSUES.md` に 1 ID = 1 項目で起票し、他文書からは ID 参照のみ。
- **`docs/verification/**` を仕様の根拠として引用しない。** 判断履歴として参照するに留める。
- 文書を更新したら、影響する正本を横断的に同期する（過去に T000-08 / T000-09 で実施された作業）。

---

## 3. 変更してはならない不変条件（Invariant Manifest）

以下はユーザー承認済みの確定仕様。**変更提案する場合は必ずユーザーの明示的承認を得ること。**

| # | 項目 | 確定値 |
|---|---|---|
| 1 | 問題形式 | 内部値 `single_prompt` / `two_choice`。仕様説明「二テーマ選択」、UI 表示「2択」 |
| 2 | 評価プロファイル | `10 / 40 / 60 / 90 / 120` 秒（この5値のみ） |
| 3 | 文字数規則（OI-029） | 採点元は各 final segment の `NBest[0].Lexical`。`ja-jp-character-count-v1`。**Lexical 取得不能時に Display 等へ fallback しない** |
| 4 | 上限判定（OI-030） | technical margin `0.07` 秒。`D <= P + 0.07` を上限内、`D > P + 0.07` を上限超過 |
| 5 | 採点式（OI-031） | `final_score = min(character_score, time_score)`。合格閾値 `60`。version `stage-a-scoring-v1` |
| 6 | 退会保持期間 | soft delete 後 30 日で hard delete |
| 7 | Stripe | 単一プラン Standard / 月額660円（税込）/ 7日トライアル / カードのみ / 期末解約 |
| 8 | Stage-A の範囲 | Stage-B 用 4 項目（pronunciation / fluency / comment / overall_score）は NULL・非生成・非表示 |
| 9 | ユーザー設定 | `question_format_preference` / `timer_display_mode` の **2 項目のみ** |
| 10 | 処理経路 | browser → Laravel → database Queue → Python/ffmpeg → Azure → **Laravel 側で採点** → DB / API / UI |
| 11 | 音声ファイル | 非永続。即時削除 + 回復削除。バックアップ対象外 |
| 12 | 履歴 | 完了済みタスクの証跡を現行仕様へ改変しない |

### 追加の禁止事項

- `questions.question_type` は**不採用**。復活させない（`has_model_answer` は採用）。
- Azure の `overall_score` を `final_score` の代用にしない。
- OI-030 の `0.07` 秒を UI タイマー、auto stop 時刻、`time_score` 用の `T`、各種 timeout へ加算・転用しない。
- Python から Laravel の DB へ直接アクセスしない。Python はステートレス、応答は HTTP レスポンスのみ（コールバック禁止）。
- 再採点時に Azure STT を再実行しない。保存済みの事実値を使う。
- 採点条件は submission に固定保存された値を使う。question / user の**現在値を再取得しない**。

---

## 4. 現在地

### 進捗（`docs/TASKS.md` ベース）

- **111 タスク中 66 件完了（59.5%）/ 45 件未着手**
- 完了しているのは T000（実装前確定）〜 T013 の一部まで
- **T014（Stripe / 法務）・T015（退会）・T016（管理画面）・T017（MVP最終確認）は 0%**

### ワークグループ実行順

`WG-A → WG-B → WG-C → WG-D → WG-E → WG-F → WG-G`

| WG | 内容 | 通常実行順（先頭のみ抜粋） |
|---|---|---|
| WG-A | Stage-A 実装補正 | **T002-06** → T002-07 → T004-04 → T011-03 → T005-04 → T006-03 → T007-06 → T008-05 → T010-04 → T009-06 |
| WG-B | 検証・運用受入 | T012-06 → T013-05 → T013-08 → … |
| WG-C | OI-012 Go/No-Go | T013-12 |
| WG-D | Stripe / 法務 | T014-01 → … |
| WG-E | 退会 | T015-01 → … |
| WG-F | 管理画面 / UI | T016-01 → … |
| WG-G | MVP 最終判定 | T017-01 |

**次に着手すべきタスクは `T002-06`（音声仕様 migration・backfill 設計）。** 依存タスク T000-08 / T000-09 は完了済み。

ただし着手可否の hard constraint は各タスクの「依存タスク」であり、WG の順序と衝突する場合は**個別依存を優先**する。

### 未解消の高優先度 OI

| OI | 内容 | 処理タスク |
|---|---|---|
| OI-006 | 422 認識不可時の UX 詳細 | T009-06 |
| OI-010 | continuous recognition 安定性 | T013-11 |
| OI-012 | Pronunciation Assessment PoC Go/No-Go | T013-12 |
| OI-018 | Git ブランチ戦略（production 側） | R1 |
| OI-025 | デザイントークン確定 | T016-06 |
| OI-027 | Stripe Webhook 最小イベント範囲 | T014-06-01 |
| OI-028 | 管理画面 MVP スコープ | T016-02-01 |
| OI-104 | 管理者 seed の初期パスワード管理 | T016-01-01 |
| OI-107 | `raw_azure_response` 500KB 超過時の保持方針 | T008-05 |
| OI-109 / OI-110 | 退会時の競合制御・部分失敗補償 | T015-01-01 |

---

## 5. 設計と実装の既知の乖離

**引継ぎ時点で最も重要な情報。** 以下は「未着手タスクの結果」であり、勝手に修正すると WG-A の補正タスクと衝突する。**必ず該当タスクの枠内で扱うこと。**

### 5.1 Stage-A 採点が一切実装されていない（最大のギャップ）

`docs/STAGE_A_SCORING.md` で確定した採点ロジックは **Laravel にも Python にも 1 行も存在しない**。
`character_score` / `time_score` / `final_score` / `scoring_version` / `evaluation_profile_seconds` の実装ヒットは 0 件。

不足している DB カラム:

| テーブル | 不足カラム | 担当タスク |
|---|---|---|
| `questions` | `prompt_text_1` / `prompt_text_2`、`prompt_text` の nullable 化 | T002-06 / T002-07 |
| `submissions` | `prompt_snapshot` / `evaluation_profile_seconds` | T002-06 / T002-07 / T006-03 |
| `evaluations` | `character_count` / `character_score` / `time_score` / `final_score` / `evaluation_result` / `scoring_version` | T002-06 / T002-07 / T008-05 |

その他:
- `evaluations.characters_per_minute` が `integer`（設計は `numeric(8,2)`）。モデルの cast も `integer`
- CHECK 制約が大幅に不足（値域・NOT NULL・btrim 系）
- `user_learning_settings` に廃止済み 3 項目（`speech_duration_seconds` / `force_stop_enabled` / `transcript_display_enabled`）が残存 → T011-03
- `UserLearningSetting` / `SettingsController` が設計外の値 `count_up` を許可している → T011-03

### 5.2 フロントエンドが採点に必要な事実値を収集していない

本番録音経路（`Composables/useAudioRecorder.js` + `Stores/useRecordingStore.js` + `Components/Recording/RecordingPanel.vue`）に以下が無い:

- プロファイル上限による**自動停止がない**（無制限に録音できる）
- **stop reason（manual / profile_limit）を記録していない** — `time_score` の算出に必須
- 経過時間が `setInterval` の整数カウントのみで、`19.999` / `20.000` の境界を判定できない
- 最低録音時間による提出ゲートがない
- `Settings.vue` の録音秒数選択肢が `30/60/90/120/180` で、確定プロファイル `10/40/60/90/120` と不一致
- `Settings.vue` のタイマー表示に設計外の `count_up` が並んでいる（確定値域は `count_down` / `hidden` のみ）
- `Settings.vue` の `two_choice` ラベルが「二者択一」（確定した UI 表示は「2択」、仕様説明は「二テーマ選択」）

**必要なロジックは検証ハーネス `resources/js/Pages/Verification/T00006MediaRecorder.vue` + `resources/js/verification/t00006RecordingUi.js` に既に実装済み**（`performance.now()` 基点の計測、`setTimeout` による自動停止、プロファイル 10/40/60/90/120）。T005-04 はこれを本番経路へ移植する作業になる。

### 5.3 Python 側の未実装

- Pronunciation Assessment / Fluency Assessment: コードなし
- Stage-A 事実値契約（`character_count` / Lexical 分離）: 未実装 → T007-06
- `question_id` / `expected_duration` / `feature_flags` は受け取るが**未使用**（`python/app/routes/evaluate.py` に明示的な破棄コードあり）
- `audio_duration_seconds` は常に `null` 固定

### 5.4 退会フロー・Stripe の全面未実装

README / OPERATIONS の 4 段階「Stripe 解約 → 音声削除 → soft delete → 30日後 hard delete」のうち、実装で追えるのは音声削除の一部と soft delete の受け皿（カラム・トレイト・部分UNIQUE）のみ。
`$user->delete()` / `forceDelete()` の呼び出しは **0 件**。`laravel/cashier` も未導入。

### 5.5 設計文書側が古い / 記載漏れの箇所

- `DB_SCHEMA.md §10.1` の Seeder 一覧に、実在する最大の `MvpQuestionSeeder`（カテゴリ5・タグ7・問題50）が載っていない。逆に未実装の `AdminUserSeeder` が載っている
- `DB_SCHEMA.md §4-2-3` の CHECK 一覧に `difficulty` の CHECK 記載が漏れている（実装側にはある）
- `DB_SCHEMA.md §4-1-3` の見出し番号が重複（`sessions` は `4-1-4` が正）
- `ARCHITECTURE.md` / `DB_SCHEMA.md` の「Laravel Cashier v15+ 準拠」は実態と乖離（実装は Cashier 非依存の独自 `customers` テーブル）
- `README.md §9` の「実装コード: 未着手」は事実と異なる（実際は develop で大部分が実装済み）

---

## 6. 設計に対する明確な実装欠陥

5章と異なり、**これらはタスクの未着手ではなく実装バグ・設定不整合**。修正時は該当タスクを確認し、無ければ新規タスク起票を提案すること。

| # | 内容 | 場所 |
|---|---|---|
| B-1 | `speech_rate.assessment` を読んでいるが Python は `characters_per_minute` しか返さない → `evaluations.speed_assessment` が本番経路で常に NULL、結果画面が常に「未記録」 | `app/Jobs/ProcessSpeechEvaluationJob.php:256-260` ⇔ `python/app/services/azure_stt.py:163-172` |
| B-2 | `retry_after`（90秒）< Python read timeout（120秒）→ ジョブ二重実行と Azure 二重課金のリスク。`$timeout` 指定もなし | `config/queue.php:42` ⇔ `config/services.php:44` |
| B-3 | `ARCHITECTURE.md §5.3` が要求する `ShouldBeUnique`（キー=`submission_id`、ロック240秒）が未実装 | `app/Jobs/ProcessSpeechEvaluationJob.php` |
| B-4 | `verified` ミドルウェアが `/dashboard` にしか付いていない。メール未認証でも `POST /api/submissions` を直叩きできる | `routes/web.php:41-67` |
| B-5 | 音声提出 API に `throttle` が無い（1リクエスト = Azure STT 1回の課金） | `routes/web.php:43` |
| B-6 | `putFileAs()` の戻り値を検査していない。`'throw' => false` のため保存失敗が握り潰される | `app/Http/Controllers/SubmissionController.php:29-33` |
| B-7 | `CleanupTempFilesJob` がスケジューラ未登録（`withSchedule` なし）。かつ `audio_path` を NULL 化しないため実行のたびに全提出をフルスキャンし、ログが単調増加する | `bootstrap/app.php` / `app/Jobs/CleanupTempFilesJob.php` |
| B-8 | `pending` / `processing` のまま残った提出の音声は永久に回収されない（cleanup 対象条件が completed/failed のみ） | `app/Jobs/CleanupTempFilesJob.php:22-32` |
| B-9 | `config('features')` を丸ごとフロントへ共有。`enable_admin_console` 等の内部情報が露出 | `app/Http/Middleware/HandleInertiaRequests.php:30-32` |
| B-10 | `TemplateCommentGenerator::resolveTemplate()` が `reset($templates)` で常に 1 案目のみ返す（3 案の選択未実装） | `app/Services/Comment/TemplateCommentGenerator.php:117-125` |
| B-11 | `RecordingPanel.vue` の「結果表示画面は後続タスクで実装します」の文言が実装（自動遷移済み）と矛盾 | `resources/js/Components/Recording/RecordingPanel.vue:47,253` |
| B-12 | `config/features.php` の 11 フラグのうち 9 個が参照ゼロ | `config/features.php:9-17` |

---

## 7. 作業ルール

### 7.1 Git

- **MVP のワークフロー: task branch → PR → review → `develop` 統合**
- `main` への反映、production deploy、release / rollback は **R1（OI-018）**。MVP では触らない
- ブランチ名の慣例: `codex/<task-id小文字>-<内容>`（例: `codex/t002-06-audio-spec-migration`）。Claude での作業は同様の形式で問題ない
- **CI は存在しない**（`.github/` なし）。テストはローカルで実行して結果を PR に記載する

### 7.2 タスクの進め方

1. `docs/TASKS.md` で対象 Task ID を特定し、`依存タスク` がすべて完了しているか確認する
2. `参照仕様書` に挙がっている正本文書を読む
3. `実装してはいけないこと` を必ず確認する（越境防止のために書かれている）
4. **1 回の依頼で原則 1 タスク**。1 タスクは 1〜3 時間以内に収める
5. Laravel / Vue / Python / Stripe を 1 タスクで横断しすぎない
6. 未確定事項に突き当たったら**実装を広げず**、OI として起票するか原因特定タスクへ切り替える
7. 完了時は `docs/TASKS.md` の該当タスクへ完了記録（日付・PR・commit・変更ファイル・テスト結果）を追記する

### 7.3 `docs/TASKS.md` の記載規約

タスク見出しは `### <Task ID>: <タイトル>`。以下のフィールドを必ず持つ。

```
- [ ] 状態: 未着手          ← 値は「完了（日付＋根拠）」か「未着手」の2値のみ
- 種別:
- 目的:
- 参照仕様書:
- 変更対象:
- 依存タスク:
- 実装内容:
- 実装してはいけないこと:
- 完了条件:
- テスト観点:
- CodeX投入時の注意:        ← Claude で作業する場合も既存フィールド名は変えない
```

補助フィールド:

- `Blocker区分`: `Blocker` / `Pre-merge` / `Pre-E2E` / `Pre-MVP` / `Pre-implementation` の5値
- 後追加タスクには `TASKS追加日`（ISO 8601）を必ず付す
- 既存タスクの削除・renumber は行わない（`existing task deletion: 0` / `renumber: 0` を維持）
- 依存関係は必ず既存 Task ID を指す（missing target 0 / cycle 0 を維持）

### 7.4 判定語彙

- MVP acceptance result は `complete` / `incomplete` の**2値のみ**。**「条件付き完了」は使用しない**
- known limitation は `R1` / `F1` または明示的な non-blocking handoff として管理する
- 再 review では previous result を履歴として残し、latest result を current acceptance result とする

### 7.5 やってはいけないこと

- 実 Secrets をリポジトリへ書く
- 未確定事項を勝手に確定する（OI を消す・断定的に実装する）
- 完了済みタスクの状態・完了日・証跡を書き換える
- UI タスクで DB 設計や未確定事項を確定する
- `docs/TASKS.md` に仕様本文を複製する

---

## 8. リポジトリ構成

```
app/
  Console/Commands/CleanupTempFilesCommand.php   # audio:cleanup-temp-files
  Contracts/CommentGeneratorInterface.php
  Dto/                                           # EvaluationResult, CommentResult, PythonEvaluation*
  Http/Controllers/                              # Dashboard, Question, Result, Settings, Submission, Auth/*
    Verification/T00006MediaRecorderController.php  # 検証専用（local/testing のみ）
  Jobs/ProcessSpeechEvaluationJob.php            # 音声評価の中核
  Jobs/CleanupTempFilesJob.php
  Models/                                        # 11モデル
  Services/PythonEvaluationClient.php            # Laravel → Python HTTP クライアント
  Services/TemporaryAudioFileCleaner.php
  Services/Comment/TemplateCommentGenerator.php  # 現行 Stage-A では使用しない想定
config/
  features.php          # Feature Flag（11件、うち参照されているのは4件）
  comment_templates.php
  services.php          # SPEECH_SERVICE_URL / timeout / Azure / Google
  t000-06.php           # 検証専用
database/migrations/    # 8ファイル / 18テーブル。2026-06-29 で更新停止
database/seeders/       # Category, Tag, Question, MvpQuestion（AdminUserSeeder は未実装）
docs/                   # 正本文書群（2章参照）
python/
  app/main.py           # GET /health
  app/routes/evaluate.py# POST /evaluate
  app/services/azure_stt.py       # Azure Speech SDK（628行、最大ファイル）
  app/services/audio_conversion.py# ffmpeg subprocess で WebM→WAV
  tests/                # pytest 47件（Azure SDK は全面フェイク）
resources/js/
  Pages/                # Welcome(placeholder), Dashboard, Questions/Index, Settings,
                        # Submissions/Result, Auth/*, Verification/T00006MediaRecorder
  Components/Recording/RecordingPanel.vue
  Composables/          # useAudioRecorder, usePolling, useFeatureFlag
  Stores/               # useRecordingStore, useSubmissionPollingStore（Pinia ではなく reactive シングルトン）
tests/                  # Feature 17本 / Unit 5本 / JavaScript 1本
```

**注意**: `ARCHITECTURE.md §7.2` は Pinia store を前提に書かれているが、**実装は Pinia を使っていない**（モジュールスコープの `reactive()` シングルトン）。`useToastStore` / `useCountdown` も未実装。

---

## 9. 開発コマンド

```bash
# --- Laravel ---
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve
php artisan queue:work                 # Queue Worker（音声評価に必須）
php artisan audio:cleanup-temp-files   # 一時音声の掃除（スケジューラ未登録のため手動）

# --- テスト（CI が無いので必ずローカルで実行する） ---
php artisan test                       # または vendor/bin/phpunit
vendor/bin/pint                        # Laravel Pint（コード整形）

# --- フロント ---
npm install
npm run dev
npm run build
npm run test:t000-06-ui                # node:test。UI テストはこの1本のみ

# --- Python 音声評価サービス ---
cd python
pip install -r requirements.txt
uvicorn app.main:app --host 127.0.0.1 --port 8100
pytest
```

### 前提

- **ffmpeg がシステムにインストールされていること**（`subprocess` から直接呼ぶ。pydub 等の wrapper は使わない）
- PostgreSQL 16 が起動していること（`phpunit.xml` の sqlite 設定はコメントアウトされており、テストも PostgreSQL を使う）
- Python サービスが `127.0.0.1:8100` で起動していること（`SPEECH_SERVICE_URL`）
- `SPEECH_SERVICE_INTERNAL_TOKEN` が Laravel / Python 双方で一致していること（不一致は 401）

---

## 10. 主要フロー（現行実装）

```
[録音] resources/js/Stores/useRecordingStore.js
   ↓ POST /api/submissions (FormData: question_id, audio)
[受付] app/Http/Controllers/SubmissionController.php::store()
   ├ storage/app/audio/{Y}/{m}/{uuid}.webm へ保存
   ├ submissions INSERT (status=pending)
   ├ ProcessSpeechEvaluationJob::dispatch()
   └ HTTP 202 + submission_id
   ↓
[Queue] app/Jobs/ProcessSpeechEvaluationJob.php::handle()
   ├ status → processing
   ├ app/Services/PythonEvaluationClient.php::evaluate()
   │    ↓ POST http://127.0.0.1:8100/evaluate (X-Internal-Token, multipart)
   │  [Python] python/app/routes/evaluate.py
   │    ├ ffmpeg で WebM/Opus → WAV(16kHz/16bit/mono)
   │    ├ Azure STT（continuous recognition / ja-JP / japaneast）
   │    └ transcript + recognized_duration + speech_rate を返す
   ├ evaluations へ updateOrCreate
   ├ status → completed
   └ 一時音声ファイル削除
   ↓
[ポーリング] 3秒間隔・最大60回 GET /api/submissions/{id}/status
   ↓ completed かつ result_url があれば自動遷移
[結果] app/Http/Controllers/ResultController.php → Pages/Submissions/Result.vue
```

**リトライ**: `$tries = 3`、backoff `[30, 60, 120]` 秒。retryable なエラーのみ再試行。
**設計上の目標値**（`ARCHITECTURE.md §5.2`）は initial 1 + max 3 retries = max 4 total attempts。

---

## 11. Claude Code での推奨作業手順

1. `git fetch && git checkout develop && git pull` で最新化する
2. 対象 Task ID を `docs/TASKS.md` で確認し、依存関係と `実装してはいけないこと` を読む
3. `参照仕様書` の正本文書を読む（`docs/verification/**` は判断履歴として補助的に参照）
4. 作業ブランチを切る
5. 実装 → `php artisan test` / `pytest` / `npm run build` を通す
6. `docs/TASKS.md` の該当タスクへ完了記録を追記する
7. PR を作成し、`develop` へ向ける
8. 仕様の判断が必要になったら**止めてユーザーに確認する**（勝手に OI を確定しない）

### 未着手ブランチの扱い

`origin/codex/*` に 10 本のブランチが残っているが、`codex/t016-04-task-dependency-fix`（+1 commit）を除きすべて `develop` にマージ済みで ahead 0。基本的に無視してよい。

---

## 12. 参考: 直近の作業履歴

| PR | 内容 | 状態 |
|---|---|---|
| #70 | OI 確定後の正本文書横断同期（T000-08） | merged |
| #72 | MVP タスク責務の再編（T000-09） | merged |
| #73 | T000-09 完了記録 | merged |
| #74 | Stage-A cross-layer error contract 確定（T000-10 / OI-112） | merged |
| #75 | ワークグループ実行管理の追加 | merged（`develop` HEAD `58a33b1`） |

直近 4 か月の作業はほぼすべて**設計文書の確定と整合**であり、アプリケーションコードは 2026-06-29 以降ほとんど変更されていない。**Claude 側の最初の実装作業は WG-A（T002-06 から）になる。**
