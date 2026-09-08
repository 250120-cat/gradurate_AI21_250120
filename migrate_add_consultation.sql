-- Consultation board schema additions
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS consultation_threads (
  thread_id INT AUTO_INCREMENT PRIMARY KEY,
  pet_id INT DEFAULT NULL,
  creator_id INT DEFAULT NULL,
  creator_name VARCHAR(100) DEFAULT NULL,
  creator_email VARCHAR(255) DEFAULT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'open',
  pdf_path VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (pet_id) REFERENCES pets(pet_id) ON DELETE SET NULL,
  FOREIGN KEY (creator_id) REFERENCES users(user_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS consultation_posts (
  post_id INT AUTO_INCREMENT PRIMARY KEY,
  thread_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  author_name VARCHAR(100) DEFAULT NULL,
  author_email VARCHAR(255) DEFAULT NULL,
  message TEXT NOT NULL,
  pdf_path VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (thread_id) REFERENCES consultation_threads(thread_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL
);

SET FOREIGN_KEY_CHECKS = 1;
