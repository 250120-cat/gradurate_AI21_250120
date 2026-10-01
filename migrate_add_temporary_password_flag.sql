ALTER TABLE users
ADD COLUMN password_change_required TINYINT(1) NOT NULL DEFAULT 0;
