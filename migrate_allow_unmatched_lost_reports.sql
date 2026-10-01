ALTER TABLE lost_reports
MODIFY pet_id INT NULL;

UPDATE lost_reports
SET pet_id = NULL, status = '未確認'
WHERE notes LIKE '【コード不明の写真報告】%';
