-- Migration v1.2: favorites, download counts, and login lockout.
-- Run once on an existing install. Safe to run again for the table only;
-- the ALTER TABLE lines use plain ADD COLUMN and give a harmless
-- "Duplicate column name" error on a second run, which you can ignore.
USE academic_archiving;

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

ALTER TABLE submissions ADD COLUMN download_count INT UNSIGNED NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN failed_logins INT NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN locked_until DATETIME NULL;
