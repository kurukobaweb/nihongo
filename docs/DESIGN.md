# DESIGN.md

> **対象範囲: MVP（フェーズ①「MVP の作成とテスト環境の検証」）**
> **最終更新: 2026-05-14（MVP実装前レビュー反映）**

---

## 1. 本文書の位置づけ

本文書は、日本語スピーチ練習アプリ MVP の UI/UX 設計を定義する正本である。
実装フェーズの AI ディベロッパーが、この文書を起点に画面構成・画面遷移・コンポーネント役割・デザイントークンを把握し、Vue コンポーネントの実装判断を開始できることを目標とする。

本文書で扱うのは、画面構成、画面遷移、UI 要素、UX 挙動である。
DB 設計の詳細は `DB_SCHEMA.md`、システム設計・状態管理・Feature Flag は `ARCHITECTURE.md`、未確定事項は `OPEN_ISSUES.md` を正本として参照する。

### 1.1 UI 継承とデザイン新規定義

本文書の UI 設計は、企画段階で作成した UI モックの検討結果を継承している。

- **継承済み**: 画面構成、UI フロー、情報配置、画面の役割分担（本文書 §4〜§7 に反映済み）
- **新規定義する**: 配色、フォント、アイコン、ビジュアルトンマナ（§3 で定義）

本文書は実装に必要な UI/UX 情報を自己完結で提供する。モックの参照は不要である。

### 1.2 MVP 画面スコープ

本文書は MVP で実装する画面のみを対象とする。以下は MVP 対象外であり、本文書では扱わない。

- 学習管理画面（統計カード、カレンダー、連続日数）→ OI-026 で将来タスクとして管理
- 超級（difficulty 4段階目）
- S級試験モード
- 言語切替 UI

### 1.3 関連文書

| 関心事 | 正本 |
|---|---|
| システム構成・レイヤー設計・Feature Flag | `ARCHITECTURE.md` |
| DB 設計（テーブル・カラム・制約） | `DB_SCHEMA.md` |
| 未確定事項 | `OPEN_ISSUES.md` |
| 運用手順 | `OPERATIONS.md` |
| Stage-A採点仕様 | `STAGE_A_SCORING.md` |
| フロントエンド状態管理 | `ARCHITECTURE.md` §7 |
| `questions.question_format` の値域 | `DB_SCHEMA.md` / 本文書 §4.1, §7-3（OI-022は解消済みdecision history） |
| 設定項目の保存先 | `DB_SCHEMA.md` / `ARCHITECTURE.md` §13 / 本文書 §4.1, §7-5（OI-023は解消済みdecision history） |
| ナビゲーション最終構成 | `OPEN_ISSUES.md` OI-024 |
| デザイントークン具体値 | `OPEN_ISSUES.md` OI-025 |
| 管理画面 MVP 範囲 | `OPEN_ISSUES.md` OI-028 |

OI-022 / OI-023は解消済みdecisionの履歴参照であり、current未確定事項またはprimary sourceとして扱わない。

---

## 2. デザインコンセプト

### 2.1 ターゲットユーザー像

日本語学習者で、スピーチ（話す力）を練習・向上させたい人。
JLPT N5〜N1 の幅広いレベルを想定し、初級者でも迷わず練習を開始でき、中上級者には多面的な評価で改善点を示す。

### 2.2 サービスとしてのトーン

学習サービスとしての信頼感と、継続しやすさを伝える方向性を基本とする。

- 落ち着いたトーンで集中しやすい
- 練習の成果が見えることで継続意欲を支える
- 分析結果を過度に重く見せず、前向きな印象を保つ

### 2.3 情報設計方針

各画面の情報は以下の優先順位で配置する。

1. いま何をする画面か（画面の主目的）
2. どの対象を扱っているか（選択中の問題・カテゴリ）
3. 結果や進捗がどう見えるか（評価結果・完了状態）
4. 次に何ができるか（次のアクション）

共通の表現ルール:

- 主目的を上部に配置する
- 詳細情報はカード化して分離する
- 補助情報は小さい文字・淡色で整理する
- 次アクションはボタンで明示する

---

## 3. デザイントークン（骨格）

デザイントークンの具体値は OI-025 で確定予定。本節では役割定義のみ示す。

### 3.1 カラーパレット

役割ベースで色を定義する方針とする。

| 役割 | 用途 |
|---|---|
| Primary | 主要アクション、基本導線の識別 |
| Success | 完了、達成、進捗の表現 |
| Warning | 注意、高難度の区別 |
| Neutral | 背景、境界線、補助テキスト |
| Accent | 情報群の識別、カテゴリ区分 |

### 3.2 タイポグラフィ

- フォントファミリー: OI-025 で確定
- サイズ体系: 見出し・本文・補助の3段階を基本とする
- 日本語と英数字の混在を前提としたフォント選定

### 3.3 アイコン方針

- アイコンライブラリの選定: OI-025 で確定
- 用途: ナビゲーション項目、状態表示（完了・未完了）、アクションボタン

---

## 4. 画面一覧

### 4.1 MVP 実装対象画面

| 画面名 | 役割 | 主要コンポーネント | DB 対応 |
|---|---|---|---|
| ログイン | 認証入口 | メール/パスワード入力、Google OAuth ボタン | `users`, `sessions` |
| 新規登録 | アカウント作成 | 入力フォーム、利用規約同意チェック | `users`, `consents` |
| メール認証 | メールアドレス確認 | 認証完了メッセージ、再送ボタン | `users.email_verified_at` |
| パスワード再設定 | パスワード復旧 | メール入力、新パスワード入力 | `password_reset_tokens` |
| ホーム | 課題選択・録音・提出 | 問題表示、録音ボタン、タイマー、提出後ポーリング | `questions`, `submissions` |
| 問題一覧 | 問題の閲覧・選択 | difficulty フィルタ、question_format フィルタ、問題リスト | `questions.question_format`, `categories`, `tags` |
| 結果表示 | 評価結果の確認 | Stage-Aの`final_score`、合否、速度、transcript。Stage-B用項目は現行Stage-Aでは非表示 | `evaluations` |
| 設定 | 練習条件の調整 | 出題方式、タイマー表示方式 | `user_learning_settings`（current補正はT011-03） |
| サブスクリプション管理 | 契約状態の確認・操作 | プラン表示、契約状態表示、解約導線 | `customers`, `subscriptions` |
| 退会 | アカウント削除 | 退会確認、注意事項表示 | `users.deleted_at` |
| 管理画面 | 管理者向け運用 | ユーザー一覧、問題管理、提出音声一覧、評価結果確認、Stripe 契約状態確認 | admin ロール。MVP範囲は OI-028 |
| 静的ページ群 | 法務・情報 | 利用規約、プライバシーポリシー、特定商取引法に基づく表記、会社概要、サイトポリシー、お問い合わせ、解約/料金案内 | — |

補足:

- 問題一覧の問題形式フィルタは `questions.question_format` を使用する。
- `questions.question_format` は DB_SCHEMA.md 反映済みのカラムである。値域は `single_prompt` / `two_choice` とする。
- 問題形式のUI表示ラベルは `single_prompt` = `単体問題`、`two_choice` = `2択` とする。正本文書上の説明は「二テーマ選択」とする。
- 設定画面の保存先は OI-023 で `user_learning_settings` テーブル方式、保存項目は2項目に確定済み。historical 5項目実装との差はT011-03で補正する。
- 管理画面は単一 admin ロールを前提とする。MVP で実装する最小範囲は OI-028 で管理する。
- サブスクリプション管理画面は契約状態の表示と解約導線を扱う。Stripe Webhook 対象イベントは OI-027 で管理し、本文書では詳細化しない。

### 4.2 用語対照

| DESIGN.md 上の表記 | DB_SCHEMA.md / ARCHITECTURE.md の対応 |
|---|---|
| 問題 | `questions` テーブル |
| カテゴリ | `categories` テーブル（問題の大分類） |
| タグ | `tags` テーブル（問題の副分類、N:N） |
| 難易度 | `questions.difficulty`（beginner / intermediate / advanced） |
| 問題形式 | `questions.question_format`（`single_prompt` / `two_choice`） |
| 模範解答有無 | `questions.has_model_answer` |
| 提出 | `submissions` テーブル（UUID v4） |
| 評価結果 | `evaluations` テーブル（submissions と 1:1） |
| Stage-Aスコア | `evaluations.final_score` |
| Stage-B総合スコア | `evaluations.overall_score`（Stage-AのみではNULL・非表示） |
| 速度 | `evaluations.characters_per_minute` + `speed_assessment` |
| Stage-B発音 | `evaluations.pronunciation_result`（Stage-AのみではNULL・非表示） |
| Stage-B流暢さ | `evaluations.fluency_result`（Stage-AのみではNULL・非表示） |
| transcript | `evaluations.transcript` |
| コメント | `evaluations.comment`（将来のStage-B用。Stage-AのみではNULL・非表示） |

補足:

- `questions.question_format` は問題形式を表す分類軸であり、DB_SCHEMA.md 反映済みである。
- `questions.question_format` の値域は `single_prompt` / `two_choice` とする。
- UI上ではDB値をそのまま表示せず、`single_prompt` は `単体問題`、`two_choice` は `2択` と表示する。
- `questions.has_model_answer` は模範解答有無を表す項目であり、問題形式ではない。
- `question_type` は使用しない。

---

## 5. 画面遷移図

### 5.1 全体遷移

```mermaid
graph LR
    subgraph 未認証
        Login[ログイン]
        Register[新規登録]
        PwReset[パスワード再設定]
        EmailVerify[メール認証]
    end

    subgraph 認証後
        Home[ホーム]
        QuestionList[問題一覧]
        Result[結果表示]
        Settings[設定]
        Subscription[サブスクリプション管理]
        Withdraw[退会]
    end

    subgraph 管理者
        Admin[管理画面]
    end

    Static[静的ページ群]

    Login -->|認証成功| Home
    Login -->|新規登録| Register
    Login -->|パスワード忘れ| PwReset
    Register -->|登録完了| EmailVerify
    EmailVerify -->|認証完了| Home
    PwReset -->|再設定完了| Login

    Home -->|問題を探す| QuestionList
    QuestionList -->|問題選択| Home
    Home -->|録音・提出・完了| Result
    Result -->|次の問題| Home
    Home -->|設定| Settings
    Home -->|契約管理| Subscription
    Subscription -->|退会| Withdraw

    Home -->|admin ロール| Admin
````

### 5.2 音声提出フロー（ホーム画面内）

```mermaid
stateDiagram-v2
    [*] --> 課題表示
    課題表示 --> 録音中: START
    録音中 --> 録音破棄: 最低採点時間未満でSTOP
    録音破棄 --> 課題表示: 再録音
    録音中 --> 提出確認: 最低採点時間以上でSTOP
    録音中 --> 提出確認: profile上限でauto stop
    提出確認 --> アップロード中: 提出
    提出確認 --> 課題表示: キャンセル
    アップロード中 --> 解析中: 202 Accepted
    解析中 --> 結果表示: 採点完了（pass / fail）
    解析中 --> 採点対象外表示: 採点対象外（evaluationなし）
    解析中 --> 認識不可表示: 422 speech_unrecognized
    解析中 --> システムエラー表示: system error
```

補足:

* STOP操作自体は録音中いつでも可能とする。
* 最低採点時間未満でSTOPした録音はsubmissionを作成せず、採点提出させずに破棄／再録音へ進める。最低採点時間は `STAGE_A_SCORING.md` を正とする。
* 最低採点時間以上のSTOPと、profile上限によるauto stopは提出確認へ進める。
* OI-030のtechnical margin `0.07秒`はUI timer、ユーザー回答時間、auto stop時刻、STOP可能時間へ加算しない。
* 422: 音声認識不可時は、エラー表示後に再録音または再提出へ戻す導線を用意する。
* 詳細なユーザー向け説明文言、再録音導線、エラー表示方針は OI-006 で管理する。
* 本文書では具体文言を確定しない。

---

## 6. レイアウト構成

### 6.1 共通構成

ログイン後のアプリ全体は共通レイアウトで包む。

* PC: 左側サイドバー + 上部ヘッダー + 中央コンテンツ領域
* Mobile / Tablet: 上部ヘッダー + 中央コンテンツ領域 + 下部ボトムナビ

### 6.2 PC レイアウト

左側に固定サイドバーを持つ構成。

* サービス名表示
* グローバルナビゲーション（常時表示）
* ログアウト導線

機能間の移動を常時可能にし、サービスの全体像を維持する。

### 6.3 Mobile / Tablet レイアウト

画面占有率と主要画面への到達性を優先する構成。

* Tablet は Mobile と同系統のナビゲーション形式で扱う
* ボトムナビを主要導線とする
* コンテンツ本体の表示領域を優先する
* 練習・問題選択・結果確認の反復導線にアクセスしやすくする

ハンバーガーメニューの採否を含むナビゲーション最終構成は OI-024 で管理。

### 6.4 ナビゲーション設計

グローバルナビゲーションの対象（MVP）:

| 項目   | 画面   | ユーザー行動    |
| ---- | ---- | --------- |
| ホーム  | ホーム  | 練習する      |
| 問題一覧 | 問題一覧 | 問題を探す     |
| 結果   | 結果表示 | 結果を確認する   |
| 設定   | 設定   | 練習条件を調整する |

PC ではサイドバーに全項目を常時表示する。
Mobile / Tablet ではボトムナビに主要導線を配置し、補助導線（設定、サブスクリプション管理、ログアウト等）の配置は OI-024 で確定する。

---

## 7. 画面別設計

### 7-1. ログイン / 新規登録

#### 画面の役割

サービス利用開始の入口。認証方式はメール+パスワードと Google OAuth の2系統（ARCHITECTURE.md §10.1）。

#### 主な要素

**ログイン画面:**

* サービス名・説明文
* メールアドレス入力
* パスワード入力
* ログインボタン
* Google OAuth ボタン
* エラーメッセージ表示領域
* 新規登録導線
* パスワード再設定導線

**新規登録画面:**

* 表示名・メールアドレス・パスワード入力
* 利用規約・プライバシーポリシーへの同意チェック（`consents` テーブルに記録）
* Google OAuth による登録導線

#### 設計意図

* 初回接触時に「日本語スピーチ練習アプリ」であることを明確に伝える
* 入力構造をシンプルにし、練習開始までの導線を短くする
* Google OAuth の自動リンク方針は解消済みOI-016の確定内容に従う

---

### 7-2. ホーム（課題選択 → 録音 → 結果表示）

#### 画面の役割

サービスの中心画面。問題を確認し、スピーチを録音・提出し、解析完了を待つまでの一連のフローを担う。

#### 主な要素

* 選択中の問題表示（タイトル、問題文、カテゴリ、難易度）
* 評価profile選択（初期値は`questions.recommended_duration_seconds`、選択肢は10 / 40 / 60 / 90 / 120秒）
* START / STOP ボタン
* 録音タイマー
* 提出ボタン
* 解析中表示（ポーリング。ARCHITECTURE.md §5.5）
* 問題移動（次の問題 / 前の問題）
* 問題一覧への導線
* エラー表示領域
* 再録音 / 再提出導線

#### 設計意図

* 画面の主役を「問題文」と「録音開始アクション」に置く
* 録音中は集中を妨げない最小限の表示とする
* STOPは録音中いつでも可能とし、最低採点時間未満なら提出確認へ進めず、録音を破棄して再録音できる状態へ戻す
* 最低採点時間以上なら提出確認へ進め、profile上限ではauto stopする
* UIの回答時間とauto stopはprofileそのものを基準とし、OI-030の`0.07秒`を加算しない
* 提出後は解析中の状態を明示し、完了時に結果表示へ遷移する
* エラー時（422: 音声認識不可）は再提出を案内する（OI-006）
* 422時は空欄の結果画面へ進めず、ホーム上または提出フロー内で再録音 / 再提出に戻す
* 422の具体文言・配置と、その他のStage-A failureの最終UI actionは OI-006 / OI-112 の確定内容に従う

#### 状態遷移

画面内の状態遷移は §5.2 の stateDiagram を参照。
録音状態管理は `useRecordingStore`、ポーリング状態は `useSubmissionPollingStore`（ARCHITECTURE.md §7.2）。

---

### 7-3. 問題一覧（difficulty × question_format で絞り込み）

#### 画面の役割

練習する問題を閲覧・選択する画面。難易度と問題形式の2軸で絞り込む。

#### 主な要素

* 難易度タブ（beginner / intermediate / advanced）
* 問題形式タブ（`単体問題` / `2択`）
* 問題リスト（タイトル、カテゴリ、推奨秒数）
* 問題ごとの実施済みマーク
* 問題選択 → ホームへの遷移

#### 設計意図

* まず難易度、次に問題形式の順で絞り込み、選択負荷を下げる
* 一覧画面で進捗が見えるようにし、学習継続のモチベーションにつなげる
* 問題単位で練習を始められる構造にする
* `difficulty` と `question_format` は独立した分類軸として扱う
* `has_model_answer` は模範解答有無であり、問題形式フィルタとして扱わない

#### DB との対応

* 難易度: `questions.difficulty`（CHECK制約: beginner / intermediate / advanced）
* 問題形式: `questions.question_format`（`single_prompt` / `two_choice`）
* カテゴリ: `questions.category_id` → `categories`
* タグ: `question_tag` → `tags`（副分類）
* 実施済み判定: `submissions` の該当 `question_id` + `status = completed` の存在

#### question_format の扱い

* `questions.question_format` は DB_SCHEMA.md 反映済みのカラムである。
* `questions.question_format` の値域は `single_prompt` / `two_choice` とする。
* 問題形式タブの表示ラベルは `single_prompt` = `単体問題`、`two_choice` = `2択` とする。仕様説明では「二テーマ選択」を使用する。
* UI上ではDB値をそのまま表示せず、日本語ラベルへ変換する前提とする。
* DBカラム、`single_prompt` / `two_choice`の値域、CHECK制約、Seeder、UIラベル（`two_choice` = `2択`）、validationはT002-05で反映・静的確認済みである。仕様説明では「二テーマ選択」を維持する。
* `question_type` は使用しない。

---

### 7-4. Stage-A結果表示

#### 画面の役割

音声提出に対するStage-A評価結果を確認する画面。Stage-Aの合否、スコア、認識事実を表示し、Stage-B用項目と混在させない。

#### 主な要素

* Stage-Aスコア（`evaluations.final_score`）
* 合否（`evaluations.evaluation_result`）
* transcript（`evaluations.transcript`）
* 速度（`evaluations.characters_per_minute` + `speed_assessment`）

#### 設計意図

* `final_score`と`evaluation_result`を明確に表示し、`overall_score`をStage-Aスコアの代用にしない
* Stage-Aのみでは`pronunciation_result`、`fluency_result`、`overall_score`、`comment`を表示しない。NULL・空欄・未評価等の内部状態も表示しない
* Stage-Aではpronunciation、fluency、template commentを生成・表示しない
* `slow` / `appropriate` / `fast`は補助分類であり、`final_score`そのものとして表示しない
* `character_score` / `time_score` / `character_count`等の内部採点値を表示するかは本書で新規確定せず、後続UI仕様で決定する
* 採点詳細、境界、pass / failは `STAGE_A_SCORING.md` を正とし、本書へ採点表を複製しない

#### 結果分類

UIは次を別状態として扱い、同じエラー表示や空欄の結果画面へまとめない。

1. 合格: evaluationあり、`evaluation_result = pass`
2. 採点不合格: evaluationあり、`evaluation_result = fail`、submissionは`completed`
3. 採点対象外: Stage-A採点およびevaluation作成なし（例: OI-030上限超過）
4. 422 `speech_unrecognized`: STT認識不可
5. system error: 500系、timeout等のシステム障害

具体的なユーザー向け文言、ボタンラベル、最終レイアウトは本節で確定せず、OI-006等の未確定事項と後続UIタスクへ委譲する。

#### Stage-B Feature Flagとの境界

既存Feature Flag設計は将来のStage-B有効化判断に使用する。現行Stage-A productionではFlagにかかわらずStage-B用4項目を生成・表示しない。

* `speech.pronunciation_assessment.enabled` → 発音セクション
* `speech.fluency_assessment.enabled` → 流暢さセクション

Pronunciation Assessment / Fluency Assessment は PoC 完了まで Feature Flag OFF を維持する。PoC成功後もStage-Bとしての具体実装・表示仕様を別途確定してから有効化する。
continuous recognition の安定性検証は OI-010、PoC Go/No-Go は OI-012 で管理する。

---

### 7-5. 設定

#### 画面の役割

ユーザーごとの練習条件を調整する画面。

#### 設定項目

| 項目       | 選択肢                              | デフォルト   |
| -------- | -------------------------------- | ------- |
| 出題方式     | 順番 / ランダム                        | 順番      |
| スピーチ時間   | 問題の推奨秒数に準拠（10/40/60/90/120秒から選択） | 問題の推奨秒数 |
| タイマー表示方式 | カウントアップ / カウントダウン / 非表示          | カウントダウン |
| 強制終了     | ON / OFF                         | ON      |
| 文字起こし表示  | ON / OFF                         | ON      |

#### 主な要素

* 設定項目ごとのラジオボタン / トグル
* 保存ボタン
* 保存完了トースト
* 保存失敗時のエラー表示
* 未保存変更がある場合の状態表示

#### 設計意図

* 設定項目を意味単位で整理し、選択状態が直感的に分かる構成にする
* ラジオボタン・トグル中心の UI とする
* 保存完了時はトースト通知で確認を表示する
* 保存前の変更状態が分かるようにし、意図しない離脱時の扱いは実装時に UI として明示する
* 設定画面は UI として定義するが、永続化方式は本文で確定しない

#### 保存先

設定項目の永続化方式は OI-023 で `user_learning_settings` テーブル方式に確定済みである。  
`users` JSONB方式は採用しない。  
T011-01時点ではUIのみで永続化未実装だったため、T011-02で `user_learning_settings` への保存を実装する。

---

### 7-6. 管理画面（admin）

#### 画面の役割

admin ロールのユーザーが運用管理を行う画面。ARCHITECTURE.md §10.2 の認可設計に基づき、`admin` ミドルウェアでガードする。

#### 主な機能

| 機能            | 概要                  | MVPでの扱い      |
| ------------- | ------------------- | ------------ |
| ユーザー一覧        | ユーザーの一覧表示、ロール・状態の確認 | OI-028 の候補範囲 |
| 問題管理          | 問題の作成・編集・公開/非公開切替   | OI-028 の候補範囲 |
| 提出音声一覧        | 提出の一覧表示、ステータス確認     | OI-028 の候補範囲 |
| 評価結果確認        | 評価結果の閲覧             | OI-028 の候補範囲 |
| Stripe 契約状態確認 | サブスクリプション状態の確認      | OI-028 の候補範囲 |

#### 設計意図

* MVP では単一 admin ロールで管理画面をガードする
* MVP 管理画面の最小範囲は OI-028 で管理する
* 既存の機能一覧は、OI-028 の候補範囲として扱う
* 初期実装で多機能化しない
* 複数管理ロールや細分化権限を MVP UI の前提にしない
* 現時点では追加権限テーブルを作らない前提を維持する
* 将来の権限粒度（閲覧/編集/課金情報）への拡張は、MVP 後の設計課題として扱う

---

### 7-7. サブスクリプション管理

#### 画面の役割

ユーザーが現在の契約状態を確認し、必要に応じて解約導線へ進む画面。

#### 主な要素

* 現在のプラン表示
* 契約状態表示
* トライアル状態・終了予定日の表示
* 次回更新日または終了予定日の表示
* 解約導線
* 課金に関する補助説明
* 料金 / 解約案内ページへの導線

#### 設計意図

* UI 側では契約状態の表示と操作導線に留める
* Stripe Webhook 対象イベントは DESIGN.md では詳細化しない
* Webhook 対象イベントの最小範囲は OI-027 で管理する
* 契約状態の正確な同期方式は DB_SCHEMA.md の Stripe 関連テーブルおよび ARCHITECTURE.md の Stripe Webhook 方針を参照する

---

## 8. レスポンシブ方針（骨格）

レスポンシブ対応は「同じ機能を提供する」ことを維持しつつ、デバイスごとに導線の見せ方を調整する。

* **PC**: 一覧性と常時ナビゲーションを重視。サイドバー常時表示
* **Tablet / Mobile**: コンテンツ表示領域と主要導線への到達性を重視。ボトムナビ中心

PC と Tablet / Mobile の間では、単なる横幅調整ではなく、ナビゲーション方式そのものが切り替わる設計とする。

ブレイクポイントの具体値はデザイントークン確定時（OI-025）に定義する。

---

## 9. アクセシビリティ方針（骨格）

MVP では以下を最低限の方針とする。

* セマンティック HTML の使用（適切な見出しレベル、ランドマーク、フォーム要素のラベル）
* キーボード操作でのフォーカス管理（録音ボタン、送信ボタン等の主要アクション）
* 色だけに依存しない情報伝達（状態表示にはテキストラベルを併用）
* 画像・アイコンへの代替テキスト付与

詳細な WCAG 準拠レベルの策定はフェーズ②以降で検討する。

```
```
