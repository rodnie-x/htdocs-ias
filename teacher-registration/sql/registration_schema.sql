-- ============================================================
-- Teacher Registration portal: extra tables
-- Run ONCE, in the SAME database as the Class Scheduling System
-- (select that database in phpMyAdmin first, then Import this file).
--
-- Tables are prefixed "treg_" so they can't clash with anything
-- already in the scheduling database.
-- ============================================================

-- Portal logins (sign up / log in). One row per teacher who signs up.
CREATE TABLE IF NOT EXISTS treg_accounts (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    first_name          VARCHAR(60)  NOT NULL,
    middle_name         VARCHAR(60)  NOT NULL DEFAULT '',
    last_name           VARCHAR(60)  NOT NULL,
    email               VARCHAR(190) NOT NULL,            -- gmail address as entered (lowercase)
    email_key           VARCHAR(190) NOT NULL,            -- gmail with dots / +tags removed, used for uniqueness + lookup
    program             VARCHAR(30)  NOT NULL,
    year_level          VARCHAR(20)  NOT NULL,
    password_hash       VARCHAR(255) NOT NULL,

    is_verified         TINYINT(1)   NOT NULL DEFAULT 0,  -- becomes 1 after the first successful code login
    login_code_hash     CHAR(64)     NULL,                -- HMAC of the emailed 6-digit code (never stored in plain text)
    login_code_expires  DATETIME     NULL,
    code_sent_at        DATETIME     NULL,
    failed_attempts     INT          NOT NULL DEFAULT 0,
    locked_until        DATETIME     NULL,
    last_login          DATETIME     NULL,

    -- Link to the scheduling system's `teachers` row (created when the form is first submitted)
    teacher_id          INT          NULL,
    reg_status          ENUM('incomplete','submitted','approved','rejected') NOT NULL DEFAULT 'incomplete',
    review_note         VARCHAR(255) NULL,                -- reason shown to the teacher when rejected
    submitted_at        DATETIME     NULL,
    profile_updated_at  DATETIME     NULL,
    reviewed_at         DATETIME     NULL,
    reviewed_by         INT          NULL,                -- scheduling system users.id of the reviewer
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_treg_email_key (email_key),
    KEY idx_treg_teacher (teacher_id),
    KEY idx_treg_status (reg_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Time windows a teacher is available to teach. Keyed by teachers.id so the
-- scheduling system can check it without knowing anything about portal logins.
CREATE TABLE IF NOT EXISTS treg_availability (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id   INT NOT NULL,
    day_of_week  ENUM('Mon','Tue','Wed','Thu','Fri','Sat') NOT NULL,   -- same values schedules.day_of_week uses
    start_time   TIME NOT NULL,
    end_time     TIME NOT NULL,
    KEY idx_treg_avail (teacher_id, day_of_week)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subjects a teacher says they can teach (subjects.id).
CREATE TABLE IF NOT EXISTS treg_subjects (
    teacher_id  INT NOT NULL,
    subject_id  INT NOT NULL,
    PRIMARY KEY (teacher_id, subject_id),
    KEY idx_treg_subject (subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional: foreign keys. Only add these if teachers.id / subjects.id are plain INT
-- (not INT UNSIGNED) in your schema, otherwise MySQL rejects them with error 1215/150.
-- ALTER TABLE treg_accounts     ADD CONSTRAINT fk_treg_acct_teacher  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL;
-- ALTER TABLE treg_availability ADD CONSTRAINT fk_treg_avail_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE;
-- ALTER TABLE treg_subjects     ADD CONSTRAINT fk_treg_subj_teacher  FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE;
-- ALTER TABLE treg_subjects     ADD CONSTRAINT fk_treg_subj_subject  FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE;
