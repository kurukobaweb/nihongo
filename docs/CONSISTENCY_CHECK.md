# 整合性確認メモ

> **最終更新: 2026-08-12**
> **対象: T000-08 OI-029〜OI-031・Stage-A／Stage-B責務反映後の文書間整合性確認**
> **確認範囲: T000-08ではOI-029〜OI-031およびStage-A／Stage-B責務に関する差分整合を再確認した。T000-08対象外の既存整合確認記録は全面再評価せず、一般的な文書整理・仕様正本切り分けは後続タスクの責務とする。**

---

## 確認対象文書

| 文書 | 状態 | 備考 |
|---|---|---|
| `README.md` | T000-08更新 | 入口文書へStage-A採点正本と現行状態を反映 |
| `OPEN_ISSUES.md` | T000-08更新 | OI-029〜OI-031を解消済みへ移動 |
| `DB_SCHEMA.md` | T000-08更新 | 採点入力・OI状態・未確定DB構造の境界を反映 |
| `ARCHITECTURE.md` | T000-08更新 | Python／Laravel責務、OI-030送信前判定、Stage-A／B境界を反映 |
| `DESIGN.md` | T000-08更新 | 録音制御、結果分類、Stage-A結果表示を反映 |
| `OPERATIONS.md` | T000-08更新 | historical rescoreとStage-A／B運用境界を反映 |
| `STAGE_A_SCORING.md` | 採点正本 | Stage-A採点表・境界・version・再採点semantics |
| `CONSISTENCY_CHECK.md` | 今回更新対象 | 各仕様書の最新版に合わせて整合確認結果を更新 |

---

## 1. README.md との整合

### 確認済み

- README.md は、プロジェクト全体の目的と現在の詳細設計対象を示す入口文書としての役割を維持している
- §3「現在の詳細設計スコープ」の文書一覧は、現在の文書構成と大きな矛盾はない
- §5「ドキュメント構成」の各文書の役割記述は、各正本の責務分離と整合している
- §5 DESIGN.md の説明は「MVP UI/UX 設計の正本」として、DESIGN.md の位置づけと整合している
- §6「文書の読み順」は、README → ARCHITECTURE → STAGE_A_SCORING → DB_SCHEMA → OPEN_ISSUES → OPERATIONS → DESIGN → TASKS の順であり、入口文書として有効
- §7「未確定事項の扱い」の「OPEN_ISSUES.md に集約」方針は、現行の未確定事項管理方針と整合している
- §8「運用方針」の「ARCHITECTURE.md はハブ文書として維持する」表現は、ARCHITECTURE.md の本文書位置づけと整合している

### 要確認・申し送り

- T000-08対象範囲ではなし。README.mdは詳細進捗を複製せず、`TASKS.md`へ委譲する入口文書として維持する

---

## 2. ARCHITECTURE.md と DB_SCHEMA.md の整合

### 確認済み

- ARCHITECTURE.md §13「DB エンティティ概要」の18テーブル / 5カテゴリは、DB_SCHEMA.md §2「全テーブル一覧」と一致している
- ARCHITECTURE.md §13.1 のカテゴリ構成は、DB_SCHEMA.md §2 の User / Learning / Stripe / System / Legal の5カテゴリと一致している
- `questions.question_type` 不採用、`has_model_answer` 採用は両文書で一貫している
- `questions.question_format` は DB_SCHEMA.md に反映済みである
- `questions.question_format` は問題形式を表す分類軸として扱われている
- `questions.question_format` の値域は `single_prompt` / `two_choice` として確定済みである
- `questions.question_format` のUI表示ラベルは `single_prompt` = `単体問題`、`two_choice` = `二者択一` として確定済みである
- `question_format` と `has_model_answer` は別概念として両文書で整理済みである
- `question_format` は問題形式、`has_model_answer` は模範解答有無であり、混同しない方針が両文書で一致している
- `submissions.id` = UUID v4 は両文書で一貫している
- `evaluations` と `submissions` の 1対1 関係は両文書で一貫している
- 音声ファイル非永続保存方針は両文書で一貫している
- CleanupTempFilesJob は、即時削除失敗時の回復手段として両文書で整合している
- 音声一時ファイルはバックアップ対象外として両文書で整合している
- Cashier v15+ 準拠（`customers` の `billable_id` + `billable_type`）は両文書で一貫している
- ユーザー設定5項目の保存先は OI-023 で確定済みであり、`user_learning_settings` テーブル方式として整理されている
- `users` JSONB方式は不採用として、ARCHITECTURE.md §13 / DB_SCHEMA.md §2 ともに 18テーブル / 5カテゴリへ更新されている
- Stripe Webhook 対象イベントの最小範囲は OI-027 管理であり、ARCHITECTURE.md / DB_SCHEMA.md ともに確定済みイベント一覧としては扱っていない
- 管理画面MVP範囲は OI-028 管理であり、追加権限テーブルは現時点で追加しない方針で整合している

### DB_SCHEMA.md §11 との整合

| ID | 対象 | 内容 | 状態 |
|---|---|---|---|
| A-01 | `ARCHITECTURE.md §13` | 18テーブル構成の反映 | **解消済み** — ARCHITECTURE.md §13.1 と DB_SCHEMA.md §2 が一致 |
| A-02 | `ARCHITECTURE.md §13` | `has_model_answer` 方針の反映 | **解消済み** — `question_format` とは別概念として整理済み |
| A-03 | `ARCHITECTURE.md §13` | Cashier v15+ の `billable_id + billable_type` 反映 | **解消済み** — ARCHITECTURE.md §13.1 と DB_SCHEMA.md Stripe 定義が一致 |
| A-04 | `ARCHITECTURE.md §11` | 音声一時保存方針の反映 | **解消済み** — 音声非永続保存、即時削除、バックアップ対象外で一致 |
| A-05 | `ARCHITECTURE.md §13` | `questions.question_format` 追加の反映 | **解消済み** — DB_SCHEMA.md 反映済み。値域は `single_prompt` / `two_choice` として確定済み |
| A-06 | `ARCHITECTURE.md §13` | ユーザー設定保存先の反映 | **解消済み** — OI-023確定済み。`user_learning_settings` テーブル方式で統一し、18テーブル / 5カテゴリへ更新 |

### 要確認（OI 依存）

- `questions.question_format` の具体値は `single_prompt` / `two_choice` として確定済み。CHECK 制約値域はT002-05で反映する
- ユーザー設定5項目の保存方式は OI-023 で `user_learning_settings` テーブル方式に確定済み
- T011-02はこの確定方針を前提に、migration / model / 保存API / UI保存処理の実装へ進める

---

## 3. ARCHITECTURE.md と OPEN_ISSUES.md の整合

### 確認済み

- ARCHITECTURE.md 本文中の「OI-xxx で管理」参照は OPEN_ISSUES.md の ID と整合している
  - §4: FastAPI ポート → OI-002
  - §6: continuous recognition → OI-010
  - §6: Pronunciation Assessment PoC Go/No-Go → OI-012
  - §9: continuous recognition → OI-010
  - §9: PoC Go/No-Go → OI-012
  - §10: Google OAuth 自動リンク → OI-016
  - §10: Stripe Webhook 対象イベント → OI-027
  - §10: 管理画面 MVP 範囲 → OI-028
  - §13: `questions.question_format` 値域 → OI-022
  - §13: ユーザー設定5項目の保存先 → OI-023
  - §14: バックアップ外部保管先 → OI-020
- ARCHITECTURE.md §13 の `question_format` は、OPEN_ISSUES.md OI-022 の管理対象と整合している
- ユーザー設定保存先は、OPEN_ISSUES.md OI-023 の確定方針（`user_learning_settings` テーブル方式、`users` JSONB方式不採用）と整合している
- Stripe Webhook 対象イベントは、OPEN_ISSUES.md OI-027 の管理対象と整合している
- 管理画面MVP範囲は、OPEN_ISSUES.md OI-028 の管理対象と整合している
- OPEN_ISSUES.md の「確定前提（本台帳の対象外）」一覧は、ARCHITECTURE.md §15 の設計原則と整合している

### ID 体系

- ARCHITECTURE.md 由来: OI-001〜OI-021
- DESIGN.md 由来: OI-022〜OI-026
- MVP実装前レビュー由来: OI-027〜OI-028
- DB_SCHEMA.md 由来: OI-101〜OI-108

---

## 4. ARCHITECTURE.md と OPERATIONS.md の整合

### 確認済み

- OPERATIONS.md §3.3 の FastAPI タイムアウト時対応は、ARCHITECTURE.md §4.3 のタイムアウト設計と整合している
- OPERATIONS.md §4.5 の Feature Flag 反映は、ARCHITECTURE.md §6 を設計正本として扱っている
- Feature Flag 反映手順は、`.env` 更新 + Laravel 設定キャッシュのクリアまたは再生成を前提としており、ARCHITECTURE.md §6.3 の管理方式と整合している
- Queue Worker / アプリケーションプロセスが新しい設定を参照することを確認する運用手順は、Feature Flag の設計意図と整合している
- 現行Stage-AではFeature Flagにかかわらず発音・流暢さ・comment・`overall_score`を生成・表示せず、Stage-B用4カラムをNULLとする方針がARCHITECTURE.md、DB_SCHEMA.md、DESIGN.mdで一致している
- OPERATIONS.md §5 のバックアップ対象は ARCHITECTURE.md §14.4 と一致している
- 音声一時ファイルはバックアップ対象外として、ARCHITECTURE.md §11 / §14.4 と OPERATIONS.md §5 で整合している
- OPERATIONS.md §1.2 の処理経路（提出→評価→保存→削除、Stripe Webhook、退会フロー）は ARCHITECTURE.md §5 の非同期ジョブ設計と整合している
- Stripe Webhook 対象イベントは OI-027 管理に留められており、OPERATIONS.md で確定済みイベント一覧として扱っていない
- 管理画面MVP範囲は OI-028 管理に留められており、OPERATIONS.md で範囲を拡張していない
- CleanupTempFilesJob は、即時削除失敗時の回復手段として扱われている
- 音声一時ファイルは、永続保存・バックアップ復元の対象ではなく、残存時は削除により整合性を回復する方針で一致している
- Secrets 管理対象に Stripe、Azure AI Speech、内部通信トークン、DB、APP_KEY、Google OAuth、Feature Flag 設定値が含まれ、ARCHITECTURE.md の外部連携・内部通信設計と整合している
- 監視対象に `failed_jobs`、Stripe Webhook、Azure API、音声削除、422 音声認識不可の傾向調査が含まれ、ARCHITECTURE.md の非同期ジョブ・外部連携・音声一時ファイル方針と整合している

### 責務分離の確認

| 関心事 | ARCHITECTURE.md | OPERATIONS.md |
|---|---|---|
| ログ | §12.2 で方針（レベル・ローテーション） | §1 / §7 で点検・監視対象 |
| バックアップ | §14.4 で対象・日次・保持期間 | §5 で確認項目・復旧順序 |
| 監視 | 設計判断の詳細対象外 | §7 でMVP最小構成 |
| Feature Flag | §6 で設計・キー・管理方式 | §4.5 で反映手順・確認観点 |
| タイムアウト | §4.3 で設計値 | §3.3 で障害時対応 |
| Stripe Webhook | §10.5 でセキュリティ方針、OI-027 参照 | §1 / §3 / §4 / §7 で点検・障害対応 |
| 管理画面 | §10.2 で admin ロール・OI-028 参照 | §1 / §4 / §8 で運用点検対象 |
| 音声削除 | §11 で一時保存・即時削除・バックアップ対象外 | §2 / §3 / §5 で残存確認・回復手段 |

重複記述は排除できている。

---

## 5. OPEN_ISSUES.md と OPERATIONS.md の整合

### 確認済み

- OPERATIONS.md 本文中の OI 参照は OPEN_ISSUES.md の ID と整合している
  - OI-001: Scheduler の具体的なジョブ一覧の最終構成
  - OI-006: STT 認識不可時 UX
  - OI-007: 内部通信トークンのローテーション方針
  - OI-013: Azure API キーローテーション手順
  - OI-018: Git ブランチ戦略
  - OI-019: 障害通知チャネル
  - OI-020: 外部バックアップ保管先・暗号化方式・復旧責任者
  - OI-021: CleanupTempFilesJob の実行頻度・削除対象条件
  - OI-027: Stripe Webhook 対象イベント
  - OI-028: 管理画面 MVP 範囲
  - OI-105: 30日後 hard delete 実行主体
- OI-006 は、OPERATIONS.md §3.5 の STT 認識不可時（422）の扱いと整合している
- 422 音声認識不可は、通常障害ではなくユーザー再提出 UX の対象として扱われている
- 422 急増時は、通常障害の断定ではなく、録音品質・ブラウザ録音・音声形式変換・Azure STT 応答傾向の品質調査対象として扱われている
- OI-018 は、OPERATIONS.md §4.7 のブランチ戦略未確定扱いと整合している
- OI-020 は、OPERATIONS.md §5.4 の外部保管先未確定扱いと整合している
- OI-021 は、OPERATIONS.md §2.2 / §3.6 の CleanupTempFilesJob の扱いと整合している
- OI-027 は、OPERATIONS.md §1 / §3 / §4 / §7 / §8 の Stripe Webhook 対象イベント未確定扱いと整合している
- OI-028 は、OPERATIONS.md §1 / §4 / §8 の管理画面MVP範囲未確定扱いと整合している
- OI-105 は、OPERATIONS.md §2.2 の hard delete 実行主体未確定扱いと整合している

---

## 6. DESIGN.md と ARCHITECTURE.md の整合

### 確認済み

- DESIGN.md §1.3 の関連文書テーブルと ARCHITECTURE.md 冒頭テーブルの役割定義が一致している
- DESIGN.md §5.2 の音声提出フローは、ARCHITECTURE.md §5.1 のシーケンス図と整合している
- DESIGN.md §7-2 のポーリング参照（ARCHITECTURE.md §5.5）が正確である
- DESIGN.md §7-4 の Feature Flag 参照（ARCHITECTURE.md §6）が正確である
- DESIGN.md §7-4 の `useFeatureFlag` composable 参照（ARCHITECTURE.md §7.3）が正確である
- DESIGN.md §7-2 の状態管理参照（ARCHITECTURE.md §7.2 `useRecordingStore`, `useSubmissionPollingStore`）が正確である
- DESIGN.md §7-6 の admin ロール参照（ARCHITECTURE.md §10.2）が正確である
- 現行Stage-AではFeature Flagにかかわらず発音・流暢さ・comment・`overall_score`を表示せず、空欄・NULL・未評価も表示しない方針で整合している
- `questions.question_format` は、ARCHITECTURE.md §13 と DESIGN.md §7-3 で問題形式を表す分類軸として整合している
- `questions.question_format` の値域は OI-022 管理に留められており、両文書で具体値を確定していない
- `difficulty` と `question_format` は独立した分類軸として両文書で整合している
- `has_model_answer` は模範解答有無であり、問題形式ではないという扱いが両文書で整合している
- 管理画面は admin ロール前提で整合している
- 管理画面MVP範囲は OI-028 管理に留められており、両文書で範囲を確定・拡張していない
- Stripe Webhook 対象イベントは、DESIGN.md では詳細化せず、ARCHITECTURE.md と同様に OI-027 管理として扱っている

### 責務分離の確認

| 関心事 | DESIGN.md | ARCHITECTURE.md |
|---|---|---|
| 画面構成・遷移 | §4〜§7 で定義 | 記載なし（DESIGN.md に委譲） |
| フロントエンド状態管理 | 参照のみ（§7-2, §7-4） | §7 で正本として定義 |
| Feature Flag | UI 表示制御の方針（§7-4） | §6 で設計・キー・管理方式 |
| `question_format` | 問題一覧・フィルタ・UI上の扱い | §13 でDBエンティティ概要として整理 |
| デザイントークン | §3 で定義（骨格） | 記載なし（DESIGN.md の責務） |
| レイアウト・ナビゲーション | §6 で定義 | 記載なし（DESIGN.md の責務） |
| 管理画面 | §7-6 でUI候補範囲を整理 | §10.2 で admin ロール・認可方針を整理 |

重複記述は排除できている。

---

## 7. DESIGN.md と DB_SCHEMA.md の整合

### 確認済み

- DESIGN.md §4.2 の用語対照テーブルが DB_SCHEMA.md のテーブル・カラム名と一致している
- DESIGN.md §4.1 の DB 対応列が DB_SCHEMA.md のテーブル構成と一致している
- DESIGN.md §7-3 の difficulty 値（beginner / intermediate / advanced）は DB_SCHEMA.md §5.2 と一致している
- DESIGN.md §7-3 の `questions.question_format` は、DB_SCHEMA.md に反映済みのカラムである
- DESIGN.md / DB_SCHEMA.md の整合状態は、`question_format` の「カラム追加待ち」ではなく「T002-05での値域・CHECK制約・Seeder・UIラベル・validation反映待ち」である
- `questions.question_format` の具体値・値域は `single_prompt` / `two_choice` として確定済みである
- `questions.question_format` のUI表示ラベルは `単体問題` / `二者択一` として確定済みである
- `difficulty` と `question_format` は独立した分類軸である
- `has_model_answer` は模範解答有無であり、問題形式ではない
- `question_type` は使用しない方針で一致している
- DESIGN.md §7-4 のStage-A結果表示が、DB_SCHEMA.md §4-2-6の`final_score` / `evaluation_result` / transcript / Stage-A補助情報と一致している
- `pronunciation_result` / `fluency_result` / `overall_score` / `comment`はStage-B用nullableカラムであり、Stage-AのみではNULL・非表示とする方針が整合している
- DESIGN.md §7-1 の consents 記録が DB_SCHEMA.md §4-5-1 と一致している
- 設定項目の保存先は OI-023 で `user_learning_settings` テーブル方式に確定済みであり、DB_SCHEMA.md では T011-02 実装前提のテーブル仕様として整合している
- DESIGN.md でも `user_learning_settings` テーブル方式に確定済み、かつ `users` JSONB方式は不採用として扱っている

### 要確認（OI 依存）

- `questions.question_format`: DBカラムは反映済み。具体値・値域は `single_prompt` / `two_choice` として確定済みで、CHECK制約・Seeder・UIラベル・validationはT002-05で反映する
- 設定項目の保存先: OI-023 確定方針に従い、DB_SCHEMA.md / DESIGN.md / ARCHITECTURE.md の該当箇所を `user_learning_settings` テーブル方式へ更新済み
- テーブル数は18テーブル / 5カテゴリとして更新済み

---

## 8. DESIGN.md と OPEN_ISSUES.md の整合

### 確認済み

- DESIGN.md 本文中の OI 参照は OPEN_ISSUES.md の ID と整合している
  - §1.2: OI-026（学習管理画面 MVP 対象外）
  - §1.3: OI-022（`questions.question_format` の値域）
  - §1.3: OI-023（設定項目の保存先）
  - §1.3: OI-024（ナビゲーション最終構成）
  - §1.3: OI-025（デザイントークン具体値）
  - §1.3: OI-028（管理画面 MVP 範囲）
  - §3: OI-025（デザイントークン確定）
  - §4.1: OI-023（設定画面の保存先）
  - §4.1: OI-027（Stripe Webhook 対象イベント）
  - §4.1: OI-028（管理画面 MVP 範囲）
  - §5.2: OI-006（STT 認識不可時 UX）
  - §6.3 / §6.4: OI-024（ナビゲーション最終構成）
  - §7-1: OI-016（Google OAuth 自動リンク）
  - §7-2: OI-006（STT 認識不可時 UX）
  - §7-3: OI-022（question_format 値域）
  - §7-4: OI-010（continuous recognition 安定性検証）
  - §7-4: OI-012（Pronunciation Assessment PoC Go/No-Go）
  - §7-5: OI-023（設定項目保存先）
  - §7-6: OI-028（管理画面 MVP 範囲）
  - §7-7: OI-027（Stripe Webhook 対象イベント）
  - §8: OI-025（ブレイクポイント）
- OI-022 は、問題形式の値域管理として DESIGN.md と整合している
- OI-023 は、設定項目保存先の確定方針（`user_learning_settings` テーブル方式、`users` JSONB方式不採用）として DESIGN.md と整合している
- OI-024 は、ナビゲーション最終構成の未確定管理として DESIGN.md と整合している
- OI-025 は、デザイントークン具体値の未確定管理として DESIGN.md と整合している
- OI-026 は、学習管理画面をMVP対象外とする扱いとして DESIGN.md と整合している
- OI-027 は、Stripe Webhook 対象イベントを DESIGN.md で詳細化しない扱いとして整合している
- OI-028 は、管理画面MVP範囲を候補範囲に留め、確定済みとして広げない扱いとして整合している

---

## 9. DESIGN.md と OPERATIONS.md の整合

### 確認済み

- Stage-AでStage-B用4項目を生成・表示せず、空欄・NULL・未評価をユーザーに表示しない方針は、DESIGN.md §7-4 と OPERATIONS.md §4.5 で整合している
- OPERATIONS.mdのFeature Flag運用は将来のStage-B有効化判断へ限定され、現行Stage-AでStage-B項目を表示しないDESIGN.mdのUI方針と整合している
- STT認識不可時（422）は、DESIGN.md では再録音 / 再提出 UX の対象として扱われている
- STT認識不可時（422）は、OPERATIONS.md では通常障害ではなく、原則としてユーザー再提出 UX の対象として扱われている
- 422急増時は、OPERATIONS.md で録音品質・ブラウザ録音・音声形式変換・Azure STT 応答傾向の品質調査対象として扱われており、DESIGN.md の OI-006 管理と矛盾しない
- Stripe Webhook 対象イベントは、DESIGN.md / OPERATIONS.md ともに OI-027 管理に留めている
- 管理画面MVP範囲は、DESIGN.md / OPERATIONS.md ともに OI-028 管理に留めている
- 管理画面は単一 admin ロール前提で扱われており、OPERATIONS.md の点検対象も OI-028 確定後に具体化する方針で整合している
- 設定変更後の確認対象に Feature Flag による結果画面表示が含まれており、DESIGN.md の表示制御方針と整合している

### 責務分離の確認

| 関心事 | DESIGN.md | OPERATIONS.md |
|---|---|---|
| Stage-A／Stage-B表示境界 | §7-4 でStage-A時のStage-B項目非表示を定義 | §4.5で運用確認境界を定義 |
| STT認識不可時（422） | §5.2 / §7-2 で再録音・再提出UXを定義 | §1 / §2 / §3 / §7 で障害扱いとの区別・品質調査 |
| Stripe Webhook | §7-7 で詳細化せず OI-027 参照 | §1 / §3 / §7 / §8 で点検・監視対象、OI-027 参照 |
| 管理画面 | §7-6 で候補範囲、OI-028 参照 | §1 / §4 / §8 で点検対象、OI-028 参照 |

重複記述は排除できている。

---

## 10. 全体整合の確認結果

### 確認済み

- `OPEN_ISSUES.md` は未確定事項の唯一の管理台帳として扱われている
- `DB_SCHEMA.md` は DB 設計の正本として扱われている
- `ARCHITECTURE.md` はシステム設計のハブ文書として扱われている
- `DESIGN.md` は MVP UI/UX 設計の正本として扱われている
- `OPERATIONS.md` は MVP テスト環境向け最小運用の正本として扱われている
- `CONSISTENCY_CHECK.md` は文書間の整合性確認結果を記録するメモとして扱われている
- `questions.question_format` は DB_SCHEMA.md 反映済みであり、値域は `single_prompt` / `two_choice` として確定済みである
- 現在のQuestionSeeder暫定値 `mvp_verification` はT002-05で正式値へ置換する
- `questions.question_type` は復活していない
- `has_model_answer` は模範解答有無として維持されている
- ユーザー設定保存先は OI-023 で確定済みであり、`user_learning_settings` テーブル方式として OPEN_ISSUES.md / DB_SCHEMA.md / ARCHITECTURE.md / DESIGN.md / TASKS.md 間で統一されている
- `users` JSONB方式は不採用として統一されている
- T011-02はこの確定方針を前提に実装へ進める
- Stripe Webhook 対象イベントは OI-027 管理であり、確定済みイベント一覧として扱っていない
- 管理画面MVP範囲は OI-028 管理であり、確定済み範囲として広げていない
- CleanupTempFilesJob は即時削除失敗時の回復手段として扱われている
- 一時音声ファイルはバックアップ対象外として扱われている
- Secrets / 監視対象の更新は OPERATIONS.md に反映済みであり、ARCHITECTURE.md と矛盾していない
- 422急増時の品質調査扱いは、DESIGN.md のUX設計、OPEN_ISSUES.md OI-006、OPERATIONS.md の運用整理と矛盾していない
- OI-029 / OI-030 / OI-031は解消済みであり、OPEN_ISSUES.mdの解消済み台帳、各Decision Record、関連正本文書の状態が一致している
- `STAGE_A_SCORING.md`をStage-A採点仕様の正本とし、Decision Recordは判断履歴として参照する責務が各文書で一致している
- PythonはAzure STTとStage-A認識事実値の取得、Laravelは正本に従う採点とevaluation保存を担当し、Pythonが`final_score`を決定しない
- `time_score`は録音制御上の経過時間`T`とstop reasonに基づき、Azure認識segment duration合計、元WebM duration`D`、UI丸め秒数、profile値を入力として代用しない
- OI-030のproduction共通technical marginは`0.07秒`であり、Azure送信前に `D <= P + 0.07` / `D > P + 0.07` を判定する。UI timer、auto stop、time_score、Queue／HTTP timeoutへ加算しない
- `pronunciation_result` / `fluency_result` / `overall_score` / `comment`はStage-B用nullableカラムであり、Stage-AのみではNULL、非生成、非表示とする。Stage-Aは`final_score`を使用し、template comment、pronunciation、fluencyを生成しない
- UIはSTOPを録音中いつでも受け付け、最低採点時間未満ならsubmissionを作成せず破棄／再録音、最低時間以上なら提出確認、profile上限ならauto stopとする
- 合格、採点不合格、採点対象外、422 `speech_unrecognized`、system errorを別状態として扱う。採点不合格ではevaluationを作成し、submissionを`completed`とする
- historical bulk rescoreの実行triggerはOPERATIONS.mdの運用責務とし、再採点semanticsは`STAGE_A_SCORING.md`を参照する。実行者、時期、方式、具体triggerは後続運用設計へ残す
- `character_count_version`、time_score用`T`、stop reason／profile limit到達相当の最終DBカラム名・型・精度・保存場所・migrationはT002-06 / T002-07の後続責務として未確定を維持している

本メモは文書間整合性の確認結果であり、実装タスク定義および CodeX 実装指示は別文書で管理する。
---
