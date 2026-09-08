-- Pet Digital ID schema for MySQL
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS shops (
  shop_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  address VARCHAR(255),
  phone VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS owners (
  owner_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  address VARCHAR(255),
  phone VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS clinics (
  clinic_id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  address VARCHAR(255),
  phone VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pets (
  pet_id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL UNIQUE,
  name VARCHAR(255),
  species VARCHAR(100),
  birthday DATE,
  shop_id INT,
  owner_id INT,
  microchip VARCHAR(100),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (shop_id) REFERENCES shops(shop_id) ON DELETE SET NULL,
  FOREIGN KEY (owner_id) REFERENCES owners(owner_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS health_records (
  record_id INT AUTO_INCREMENT PRIMARY KEY,
  pet_id INT NOT NULL,
  clinic_id INT,
  record_date DATE,
  details TEXT,
  weight DECIMAL(5,2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pet_id) REFERENCES pets(pet_id) ON DELETE CASCADE,
  FOREIGN KEY (clinic_id) REFERENCES clinics(clinic_id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS training_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  pet_id INT NOT NULL,
  log_date DATE,
  behavior VARCHAR(255),
  score INT,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pet_id) REFERENCES pets(pet_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS lost_reports (
  lost_id INT AUTO_INCREMENT PRIMARY KEY,
  pet_id INT NOT NULL,
  report_date DATE,
  location VARCHAR(255),
  status VARCHAR(50),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (pet_id) REFERENCES pets(pet_id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role VARCHAR(50) DEFAULT 'admin',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- メールテンプレート保存テーブル
CREATE TABLE IF NOT EXISTS email_templates (
  template_key VARCHAR(100) NOT NULL PRIMARY KEY,
  subject VARCHAR(255) DEFAULT NULL,
  body_html TEXT,
  body_text TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

SET FOREIGN_KEY_CHECKS = 1;
