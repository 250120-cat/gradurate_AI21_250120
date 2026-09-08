-- phpMyAdmin で pet_digital_id データベースを選択して実行してください
-- ※ 既にカラムがある場合はエラーが出ますが、その行はスキップして問題ありません

USE pet_digital_id;

-- ユーザーにメールアドレスと通知設定を追加
ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL AFTER username;
ALTER TABLE users ADD COLUMN notify_lost TINYINT(1) NOT NULL DEFAULT 1 COMMENT '迷子報告メールを受け取る' AFTER role;

-- 飼い主にもメール（任意）
ALTER TABLE owners ADD COLUMN email VARCHAR(255) NULL AFTER phone;

-- 迷子報告テーブルの拡張カラム
ALTER TABLE lost_reports ADD COLUMN reporter_name VARCHAR(255) NULL AFTER notes;
ALTER TABLE lost_reports ADD COLUMN reporter_phone VARCHAR(50) NULL AFTER reporter_name;
ALTER TABLE lost_reports ADD COLUMN photo_path VARCHAR(500) NULL AFTER reporter_phone;

-- ペット写真・画像ハッシュ
ALTER TABLE pets ADD COLUMN photo_path VARCHAR(500) NULL AFTER notes;
ALTER TABLE pets ADD COLUMN image_hash VARCHAR(64) NULL AFTER photo_path;
ALTER TABLE pets ADD COLUMN personality TEXT NULL AFTER image_hash;
ALTER TABLE pets ADD COLUMN parent_info TEXT NULL AFTER personality;
ALTER TABLE pets ADD COLUMN care_notes TEXT NULL AFTER parent_info;

-- 迷子報告メールのデフォルトテンプレート
INSERT INTO email_templates (template_key, subject, body_html, body_text) VALUES (
  'lost_found',
  '【Pet Digital ID】迷子・発見報告が届きました',
  '<p>迷子・発見報告が届きました。</p>
<p><strong>ペット名:</strong> {pet_name}<br>
<strong>コード:</strong> {code}<br>
<strong>発見者:</strong> {reporter_name}<br>
<strong>連絡先:</strong> {reporter_phone}<br>
<strong>発見場所:</strong> {location}<br>
<strong>報告日時:</strong> {report_date}</p>
<p><a href="{pet_link}">ペット詳細を見る</a></p>
<p>{notes}</p>',
  '迷子・発見報告が届きました。

ペット名: {pet_name}
コード: {code}
発見者: {reporter_name}
連絡先: {reporter_phone}
発見場所: {location}
報告日時: {report_date}

詳細: {pet_link}

メモ:
{notes}'
) ON DUPLICATE KEY UPDATE template_key = template_key;

-- テストユーザーにサンプルメールを設定
UPDATE users SET email = 'admin@example.com', notify_lost = 1 WHERE username = 'admin';
UPDATE users SET email = 'hospital@example.com', notify_lost = 1 WHERE username = 'hospital_test';
UPDATE users SET email = 'owner@example.com', notify_lost = 1 WHERE username = 'owner_test';
UPDATE users SET email = 'shop@example.com', notify_lost = 1 WHERE username = 'shop_test';
