-- Migration v1.1: add the notifications table for review decisions.
-- Run once on an existing install. Safe to run again: the table is only
-- created when it does not exist yet.
USE academic_archiving;

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
