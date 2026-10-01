-- Migration: add last_error and status to news_feeds
ALTER TABLE news_feeds
  ADD COLUMN last_error TEXT DEFAULT NULL,
  ADD COLUMN status TINYINT(1) DEFAULT 1;

-- Note: Run this in phpMyAdmin or mysql CLI:
-- USE pet_digital_id;
-- SOURCE migrate_add_news_error.sql;
