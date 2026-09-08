-- Migration: create genres and news tables
CREATE TABLE IF NOT EXISTS consultation_genres (
  genre_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS news_feeds (
  feed_id INT AUTO_INCREMENT PRIMARY KEY,
  url TEXT NOT NULL,
  title VARCHAR(255) DEFAULT NULL,
  enabled TINYINT(1) DEFAULT 1,
  last_fetched DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS news_cache (
  item_id INT AUTO_INCREMENT PRIMARY KEY,
  feed_id INT DEFAULT NULL,
  title TEXT,
  link TEXT,
  summary TEXT,
  pub_date DATETIME DEFAULT NULL,
  fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (feed_id),
  INDEX (pub_date),
  FOREIGN KEY (feed_id) REFERENCES news_feeds(feed_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
