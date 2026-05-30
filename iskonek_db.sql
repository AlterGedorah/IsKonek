-- ============================================================
-- Iskonek Database Schema
-- Run this file in phpMyAdmin or MySQL CLI:
--   mysql -u root -p < iskonek_db.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS iskonek_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE iskonek_db;

-- Users
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    full_name  VARCHAR(120) NOT NULL,
    email      VARCHAR(160) NOT NULL UNIQUE,
    student_id VARCHAR(30)  NOT NULL,
    role       VARCHAR(80)  DEFAULT 'Student',
    password   VARCHAR(255) NOT NULL,
    avatar     VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
);

-- Subjects
CREATE TABLE IF NOT EXISTS subjects (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(20)  NOT NULL UNIQUE,
    name        VARCHAR(160) NOT NULL,
    instructor  VARCHAR(120) DEFAULT NULL,
    units       TINYINT      DEFAULT 3,
    enroll_key  VARCHAR(40)  NOT NULL,
    color       VARCHAR(10)  DEFAULT '#33b77a',
    semester    VARCHAR(40)  DEFAULT '2nd Sem, A.Y. 2024-25',
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- Enrollments
CREATE TABLE IF NOT EXISTS enrollments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    subject_id  INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY  uq_enroll (user_id, subject_id),
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE
);

-- Announcements
CREATE TABLE IF NOT EXISTS announcements (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    subject_id  INT          NOT NULL,
    title       VARCHAR(255) NOT NULL,
    body        TEXT,
    created_by  INT          NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)    ON DELETE CASCADE
);

-- Forums (posts)
CREATE TABLE IF NOT EXISTS forum_posts (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    subject_id  INT          NOT NULL,
    user_id     INT          NOT NULL,
    title       VARCHAR(255) NOT NULL,
    body        TEXT,
    views       INT          DEFAULT 0,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
);

-- Forum replies
CREATE TABLE IF NOT EXISTS forum_replies (
    id       INT AUTO_INCREMENT PRIMARY KEY,
    post_id  INT  NOT NULL,
    user_id  INT  NOT NULL,
    body     TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES forum_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)       ON DELETE CASCADE
);

-- Kanban tasks
CREATE TABLE IF NOT EXISTS tasks (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    subject_id  INT          NOT NULL,
    user_id     INT          NOT NULL,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    status      ENUM('todo','in_progress','done') DEFAULT 'todo',
    due_date    DATE         DEFAULT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
);

-- Events (campus calendar)
CREATE TABLE IF NOT EXISTS events (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    event_date  DATE         NOT NULL,
    event_time  TIME         DEFAULT NULL,
    created_by  INT          NOT NULL,
    created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Files (repository)
CREATE TABLE IF NOT EXISTS files (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    subject_id  INT          NOT NULL,
    user_id     INT          NOT NULL,
    filename    VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_size   INT          DEFAULT 0,
    file_type   VARCHAR(80)  DEFAULT NULL,
    uploaded_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE
);

-- ============================================================
-- Sample Data
-- ============================================================

-- Demo user  (password: password123)
INSERT INTO users (full_name, email, student_id, role, password) VALUES
('Ryan Pol Malabanan', 'ryan@school.edu.ph', '2022-10234', 'Student - BSIT 2-1',
 '$2y$12$n4XRSfVJ4c3P5bQ/2fKzH.4q6aA1RY9kGwkl3tD.xUkdUfyF5jAoG');

-- Subjects
INSERT INTO subjects (code, name, instructor, units, enroll_key, color) VALUES
('ITEC 106', 'Information Assurance & Security',    'Prof. Dela Cruz',  3, 'KEY-106', '#33b77a'),
('ITEC 107', 'Systems Integration & Architecture',  'Prof. Reyes',      3, 'KEY-107', '#4a90d9'),
('ITEC 108', 'Capstone Project 1',                  'Prof. Santos',     6, 'KEY-108', '#d23c3c'),
('CC 105',   'Methods of Research',                 'Prof. Garcia',     3, 'KEY-CC5', '#f5a623'),
('GE 108',   'Contemporary World',                  'Prof. Villanueva', 3, 'KEY-GE8', '#9b59b6'),
('PE 4',     'Physical Education 4',                'Prof. Mendoza',    2, 'KEY-PE4', '#1abc9c');

-- Enroll demo user in all subjects
INSERT INTO enrollments (user_id, subject_id)
SELECT 1, id FROM subjects;

-- Sample announcements
INSERT INTO announcements (subject_id, title, body, created_by) VALUES
(3, 'Capstone submission deadline extended', 'The deadline has been moved to June 30, 2026.', 1),
(1, 'Quiz 3 scheduled for next Wednesday',   'Coverage: Chapters 5–7. Bring your cheat sheet.', 1),
(4, 'Research proposal feedback released',   'Check your email for detailed feedback.', 1);

-- Sample events
INSERT INTO events (title, description, event_date, created_by) VALUES
('Final Examinations',   'Week-long final exams for all subjects.', '2026-06-15', 1),
('Enrollment Period',    'Next semester enrollment opens.',          '2026-07-01', 1);
