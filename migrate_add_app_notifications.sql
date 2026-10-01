USE pet_digital_id;

CREATE TABLE IF NOT EXISTS app_notifications (
  notification_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  link_path VARCHAR(255) NOT NULL DEFAULT 'lost_board.php',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at TIMESTAMP NULL DEFAULT NULL,
  INDEX idx_app_notifications_user_read_created (user_id, read_at, created_at),
  CONSTRAINT fk_app_notifications_user
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
