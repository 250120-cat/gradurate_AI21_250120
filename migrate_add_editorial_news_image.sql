USE pet_digital_id;

ALTER TABLE editorial_news
  ADD COLUMN image_path VARCHAR(500) DEFAULT NULL AFTER summary;
