Pet Digital ID（ペットデジタルパスポート）最小実装（XAMPP向け）

概要:
- 本プロジェクトは卒業制作向けのプロトタイプです。XAMPP（Apache + PHP + MySQL）で動作します。

セットアップ手順:
1. `C:/xampp/htdocs/` に本フォルダ `動物保護` を配置する。
2. MySQLでデータベースを作成し、`migrate.sql` を実行する。
   ```sql
   CREATE DATABASE pet_digital_id CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   USE pet_digital_id;
   SOURCE migrate.sql;
   ```
3. `config.php` の DB 接続情報を環境に合わせて編集する。
4. ブラウザで `http://localhost/動物保護/` にアクセスする。
- `seed.php` は開発用データの投入スクリプトで、誤実行防止のためコマンドラインからのみ実行できます。本番データベースでは実行しないでください。

サイト内通知受信箱:
- phpMyAdmin で `pet_digital_id` データベースを選択し、`migrate_add_app_notifications.sql` を実行してください。
- ログイン後、左メニューの「受信箱」から通知を確認できます。
- 迷子・発見報告は管理者と「迷子通知ON」のユーザーの受信箱に保存されます。外部メールサーバーへの送信は行いません。

飼い主ログイン:
- お迎え前はペット登録で種類・写真などを登録し、ペット名と飼い主情報は空欄のままにできます。飼い主が決まったらショップ管理から名前を変更し、飼い主情報とログインIDを登録できます。ログインIDはペットの個体コード・QRコードとは別のものです。仮パスワードはシステムが生成し、発行完了後にショップ管理画面で一度だけ表示します。飼い主本人へ安全な方法で伝えてください。
- 仮パスワードで初回ログインするとパスワード変更画面に限定され、本人が10文字以上の新しいパスワードへ変更すると通常利用できます。
- 飼い主がパスワードを忘れた場合、ショップ管理の該当ペット、または管理者のユーザー管理から仮パスワードを再発行できます。再発行すると以前のパスワードは使えなくなり、新しい仮パスワードは担当者の画面に一度だけ表示されます。本人確認のうえ安全な方法で伝えてください。飼い主は次回ログイン時に新しいパスワードへ変更します。
- 既存データベースでは `migrate_add_temporary_password_flag.sql` を一度実行してください。飼い主アカウントの重複防止に `migrate_add_unique_owner_login.sql` をまだ適用していない場合は、先に適用してください。

迷子報告と写真候補:
- コードで指定されたペットだけを報告対象として登録します。コードがなく写真から類似候補が見つかった場合は、個体を自動特定せず「未確認」として報告を保存し、候補は参考情報として通知・管理画面のメモに記録します。
- 既存データベースではphpMyAdminから `migrate_allow_unmatched_lost_reports.sql` を一度実行し、ペット未特定の報告を保存できるようにしてください。この移行では、過去にコード不明の写真報告から自動紐付けされていた記録も「未確認」に戻します。
- 管理者は迷子報告一覧で写真・メモ・候補を確認し、必要な場合だけペットを選んで紐付けられます。誤操作時は紐付けを解除して未特定に戻せます。操作はCSRF確認と操作履歴記録の対象です。
- 迷子掲示板では「未確認」「未対応」「対応中」「迷子」「解決済み」で絞り込み・状態更新できます。「写真候補あり」「found」は既存報告用の旧状態として保持しています。
- 迷子掲示板の公開メモからは、従来の形式で保存された「発見者」「連絡先」行も除外します。発見者名と電話番号は専用欄に保存し、管理者だけが確認できます。メモ欄には個人の連絡先を書かないよう案内しています。

オリジナル動物ニュース:
- phpMyAdmin で `pet_digital_id` データベースを選択し、`migrate_add_editorial_news.sql` を実行してください。
- ニュース記事に画像を使う場合は、同じデータベースで `migrate_add_editorial_news_image.sql` も一度実行してください。
- 管理者画面の「動物ニュース記事作成」から、独自の紹介文と出典リンクを登録できます。
- ニュース画像はJPEG、PNG、WebP形式で5MB以下にしてください。画像はプロジェクト内の `uploads/news/` に保存されます。
- 公開画面には公開状態の記事だけを表示します。外部記事の本文や長い抜粋を転載せず、利用許諾と出典元の条件を確認してください。

迷子ペットの写真類似検索:
- 写真を9×8画素に縮小し、横方向の明暗の並びを64 bitで表すdHashで候補を検索します。機械学習AIによる個体識別ではなく、候補は必ず目視で確認してください。
- XAMPP Control PanelでApacheを停止し、`C:\xampp\php\php.ini` の `;extension=gd` を `extension=gd` に変更してApacheを再起動してください。CLIでもGDが有効になったか `C:\xampp\php\php.exe -m` で確認します。
- GDを有効にした後、プロジェクトフォルダーで `C:\xampp\php\php.exe tools\rebuild_image_hashes.php` を実行すると、更新件数を確認するドライランになります。データベースをバックアップして内容を確認後、`C:\xampp\php\php.exe tools\rebuild_image_hashes.php --apply` で登録済み写真からdHashを再生成してください。画像がない・読み込めないペットは候補検索の対象外となり、処理結果に件数が表示されます。
- dHashの距離計算テストは `C:\xampp\php\php.exe tools\test_dhash.php` で実行できます。

主要ファイル:
- `config.php` - DB設定
- `db.php` - PDO接続
- `migrate.sql` - スキーマ
- `migrate_add_app_notifications.sql` - サイト内通知受信箱の追加
- `migrate_add_editorial_news.sql` - オリジナル動物ニュース記事の追加
- `migrate_add_editorial_news_image.sql` - オリジナル動物ニュース画像の追加
- `admin/news_articles.php` - 動物ニュース記事の作成・編集・公開管理
- `admin/pets.php` - 管理者向けペット一覧
- `admin/edit_pet.php` - 管理者向けペット情報編集
- `admin/delete_pet.php` - 管理者向けペット完全削除（関連記録・写真を含む）
- `admin/audit_logs.php` - 管理者向け操作履歴の検索・閲覧
- `change_password.php` - ログイン中ユーザーのパスワード変更
- `migrate_add_temporary_password_flag.sql` - 仮パスワードの初回変更を必須にする列
- `migrate_add_unique_owner_login.sql` - 飼い主ごとにログインを1つに制限するインデックス
- `tools/rebuild_image_hashes.php` - 登録済みペット写真のdHash再生成（CLI専用）
- `tools/test_lost_report_privacy.php` - 掲示板メモの個人情報秘匿テスト（CLI専用）
- `index.php` - ランディング（役割選択）
- `shop/register_pet.php` - ペット登録（コード・QR発行）
- `pet/view.php` - コードで個体を表示、記録の追加
- `assets/style.css` - 簡易スタイル

注意:
- 本サンプルは認証、入力検証、セキュリティ対策を簡略化しています。本格運用時は必ず追加実装してください。

迷子報告の通知は、外部メールではなくサイト内の受信箱へ保存されます。ログイン後、メニューの「受信箱」で未読通知を確認できます。

エンドツーエンドの動作確認手順
1. 登録ページ: `http://localhost/動物保護/shop/register_pet.php` でペットを登録
2. 詳細ページ: `pet/view.php` で登録した個体を確認（QR のリンク先を確認）
3. 迷子報告: QR または `lost.php?code=<pet_code>` で報告を送信
4. 管理画面: `http://localhost/動物保護/admin/lost_reports.php` で報告が保存されていることを確認
5. 受信箱: ログインして、迷子報告通知がサイト内受信箱に保存されていることを確認

セキュリティ備考
- 実運用では `config.php` に平文でパスワードを置かないでください。環境変数や安全なシークレット管理を利用してください。
- 本プロトタイプは最小限の実装です。入力検証・認可・CSRF 対策等を必ず実装してください。

次のステップ
- ニュース記事は許諾の範囲を確認し、独自の紹介文と出典リンクを登録してください。
