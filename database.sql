-- ============================================================
-- Quiz Generator Database Schema
-- Nueva Ecija University of Science and Technology
-- ITWS03 / ITWS04 / ITWS05 Case Study
-- ============================================================

CREATE DATABASE IF NOT EXISTS quiz_generator
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE quiz_generator;

-- ============================================================
-- TABLES
-- ============================================================

CREATE TABLE users (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(50)  NOT NULL UNIQUE,
    email       VARCHAR(100) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,   -- Bcrypt/Argon2 hash
    role        ENUM('admin','teacher','student') NOT NULL DEFAULT 'student',
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE quizzes (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title        VARCHAR(200) NOT NULL,
    description  TEXT,
    created_by   INT UNSIGNED NOT NULL,
    time_limit   INT UNSIGNED DEFAULT 0 COMMENT 'seconds; 0 = no limit',
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE questions (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id      INT UNSIGNED NOT NULL,
    question_text TEXT NOT NULL,
    question_type ENUM('multiple_choice','true_false','short_answer') NOT NULL DEFAULT 'multiple_choice',
    points       INT UNSIGNED NOT NULL DEFAULT 1,
    sort_order   INT UNSIGNED NOT NULL DEFAULT 0,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE choices (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question_id INT UNSIGNED NOT NULL,
    choice_text TEXT NOT NULL,
    is_correct  TINYINT(1) NOT NULL DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE attempts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quiz_id     INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    started_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    submitted_at DATETIME DEFAULT NULL,
    score       DECIMAL(5,2) DEFAULT NULL,
    total_points INT UNSIGNED DEFAULT NULL,
    FOREIGN KEY (quiz_id)  REFERENCES quizzes(id)  ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)    ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE responses (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_id  INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    choice_id   INT UNSIGNED DEFAULT NULL,
    text_answer TEXT DEFAULT NULL,
    is_correct  TINYINT(1) DEFAULT NULL,
    FOREIGN KEY (attempt_id)  REFERENCES attempts(id)   ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES questions(id)  ON DELETE CASCADE,
    FOREIGN KEY (choice_id)   REFERENCES choices(id)    ON DELETE SET NULL
) ENGINE=InnoDB;

-- Audit log — populated by triggers
CREATE TABLE audit_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    table_name  VARCHAR(50) NOT NULL,
    action      ENUM('INSERT','UPDATE','DELETE') NOT NULL,
    record_id   INT UNSIGNED NOT NULL,
    changed_by  INT UNSIGNED DEFAULT NULL,
    changed_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    description TEXT
) ENGINE=InnoDB;

-- ============================================================
-- STORED PROCEDURES
-- ============================================================

DELIMITER $$

-- Submit a quiz attempt and auto-grade it
CREATE PROCEDURE sp_submit_attempt(
    IN  p_attempt_id  INT UNSIGNED,
    OUT p_score       DECIMAL(5,2),
    OUT p_total       INT UNSIGNED
)
BEGIN
    DECLARE v_score INT UNSIGNED DEFAULT 0;
    DECLARE v_total INT UNSIGNED DEFAULT 0;

    -- Grade multiple_choice and true_false by matching choice
    UPDATE responses r
    JOIN choices c ON r.choice_id = c.id
    SET r.is_correct = c.is_correct
    WHERE r.attempt_id = p_attempt_id;

    -- Calculate total points possible for this quiz
    SELECT SUM(q.points) INTO v_total
    FROM attempts a
    JOIN questions q ON q.quiz_id = a.quiz_id
    WHERE a.id = p_attempt_id;

    -- Calculate earned points
    SELECT COALESCE(SUM(q.points), 0) INTO v_score
    FROM responses r
    JOIN questions q ON r.question_id = q.id
    WHERE r.attempt_id = p_attempt_id
      AND r.is_correct = 1;

    -- Update attempt record
    UPDATE attempts
    SET score        = IF(v_total > 0, ROUND((v_score / v_total) * 100, 2), 0),
        total_points = v_total,
        submitted_at = NOW()
    WHERE id = p_attempt_id;

    SET p_score = IF(v_total > 0, ROUND((v_score / v_total) * 100, 2), 0);
    SET p_total = v_total;
END$$

-- Get quiz results summary
CREATE PROCEDURE sp_get_quiz_results(IN p_quiz_id INT UNSIGNED)
BEGIN
    SELECT
        u.username,
        a.score,
        a.total_points,
        a.submitted_at,
        TIMESTAMPDIFF(SECOND, a.started_at, a.submitted_at) AS time_taken_seconds
    FROM attempts a
    JOIN users u ON a.user_id = u.id
    WHERE a.quiz_id = p_quiz_id
      AND a.submitted_at IS NOT NULL
    ORDER BY a.score DESC, a.submitted_at ASC;
END$$

-- Get leaderboard for a quiz
CREATE PROCEDURE sp_get_leaderboard(IN p_quiz_id INT UNSIGNED)
BEGIN
    SELECT
        RANK() OVER (ORDER BY a.score DESC) AS rank_pos,
        u.username,
        a.score,
        a.submitted_at
    FROM attempts a
    JOIN users u ON a.user_id = u.id
    WHERE a.quiz_id = p_quiz_id
      AND a.submitted_at IS NOT NULL
    ORDER BY a.score DESC
    LIMIT 20;
END$$

DELIMITER ;

-- ============================================================
-- TRIGGERS
-- ============================================================

DELIMITER $$

-- Log when a quiz is created
CREATE TRIGGER trg_quiz_after_insert
AFTER INSERT ON quizzes
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, action, record_id, description)
    VALUES ('quizzes', 'INSERT', NEW.id,
            CONCAT('Quiz created: "', NEW.title, '" by user ', NEW.created_by));
END$$

-- Log when a quiz is updated
CREATE TRIGGER trg_quiz_after_update
AFTER UPDATE ON quizzes
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, action, record_id, description)
    VALUES ('quizzes', 'UPDATE', NEW.id,
            CONCAT('Quiz updated: "', NEW.title, '"'));
END$$

-- Log when a quiz is deleted
CREATE TRIGGER trg_quiz_after_delete
AFTER DELETE ON quizzes
FOR EACH ROW
BEGIN
    INSERT INTO audit_log (table_name, action, record_id, description)
    VALUES ('quizzes', 'DELETE', OLD.id,
            CONCAT('Quiz deleted: "', OLD.title, '"'));
END$$

-- Log when an attempt is submitted
CREATE TRIGGER trg_attempt_after_update
AFTER UPDATE ON attempts
FOR EACH ROW
BEGIN
    IF OLD.submitted_at IS NULL AND NEW.submitted_at IS NOT NULL THEN
        INSERT INTO audit_log (table_name, action, record_id, description)
        VALUES ('attempts', 'UPDATE', NEW.id,
                CONCAT('Attempt submitted by user ', NEW.user_id,
                       ' for quiz ', NEW.quiz_id,
                       ' — score: ', NEW.score, '%'));
    END IF;
END$$

DELIMITER ;

-- ============================================================
-- SEED DATA (optional demo)
-- ============================================================

-- Default admin user: admin / Admin@1234
INSERT INTO users (username, email, password, role) VALUES
('admin', 'admin@quizgen.local',
 '$2y$12$wArkiVxm.vQZAeUX8wtimeLOHuuxwUten56TUWWL.3HwAoYo75sb2',
 'admin');
