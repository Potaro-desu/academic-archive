CREATE DATABASE IF NOT EXISTS academic_archiving CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE academic_archiving;

CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 email VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('student','faculty','admin') NOT NULL DEFAULT 'student',
 is_active TINYINT(1) NOT NULL DEFAULT 1,
 failed_logins INT NOT NULL DEFAULT 0,
 locked_until DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_users_role(role)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL UNIQUE,
 description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS submissions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 category_id INT UNSIGNED NOT NULL,
 title VARCHAR(180) NOT NULL,
 description TEXT NOT NULL,
 keywords VARCHAR(500) NULL,
 academic_year VARCHAR(20) NULL,
 resource_type ENUM('file','link') NOT NULL,
 original_name VARCHAR(255) NULL,
 stored_name VARCHAR(255) NULL,
 mime_type VARCHAR(150) NULL,
 file_size BIGINT UNSIGNED NULL,
 external_url TEXT NULL,
 download_count INT UNSIGNED NOT NULL DEFAULT 0,
 visibility ENUM('public','private') NOT NULL DEFAULT 'public',
 status ENUM('pending','approved','rejected','returned') NOT NULL DEFAULT 'pending',
 feedback VARCHAR(1500) NULL,
 reviewed_by BIGINT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 published_at DATETIME NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CONSTRAINT fk_sub_user FOREIGN KEY(user_id) REFERENCES users(id),
 CONSTRAINT fk_sub_category FOREIGN KEY(category_id) REFERENCES categories(id),
 CONSTRAINT fk_sub_reviewer FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_public_catalog(status,visibility,published_at),
 INDEX idx_owner(user_id,created_at),
 INDEX idx_review(status,created_at),
 FULLTEXT KEY ft_submission_search(title,description,keywords)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 submission_id BIGINT UNSIGNED NULL,
 message VARCHAR(1000) NOT NULL,
 is_read TINYINT(1) NOT NULL DEFAULT 0,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_notif_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_notif_submission FOREIGN KEY(submission_id) REFERENCES submissions(id) ON DELETE SET NULL,
 INDEX idx_notif_user(user_id,is_read,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS favorites (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 submission_id BIGINT UNSIGNED NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_fav_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_fav_submission FOREIGN KEY(submission_id) REFERENCES submissions(id) ON DELETE CASCADE,
 UNIQUE KEY uq_fav_user_submission(user_id,submission_id),
 INDEX idx_fav_user(user_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NULL,
 submission_id BIGINT UNSIGNED NULL,
 action VARCHAR(80) NOT NULL,
 details JSON NULL,
 ip_address VARCHAR(45) NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 CONSTRAINT fk_audit_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
 CONSTRAINT fk_audit_submission FOREIGN KEY(submission_id) REFERENCES submissions(id) ON DELETE SET NULL,
 INDEX idx_audit_created(created_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO categories(name,description) VALUES
('Research Papers','Student and faculty research outputs'),
('Thesis and Capstone','Thesis, capstone and project documentation'),
('Learning Materials','Modules, reviewers and instructional materials'),
('Presentations','Slides and presentation files'),
('Multimedia','Audio, video and image resources'),
('Other Resources','Other approved academic materials');
