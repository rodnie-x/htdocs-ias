-- Run once in phpMyAdmin: database css_system -> SQL tab -> paste -> Go
CREATE TABLE IF NOT EXISTS treg_profiles (
  account_id         INT NOT NULL PRIMARY KEY,
  photo_file         VARCHAR(80) NULL,
  employee_id        VARCHAR(30) NULL,
  faculty_id         VARCHAR(30) NULL,
  gender             VARCHAR(40) NULL,
  birthdate          DATE NULL,
  civil_status       VARCHAR(40) NULL,
  nickname           VARCHAR(40) NULL,
  nationality        VARCHAR(40) NULL,
  religion           VARCHAR(40) NULL,
  birth_place        VARCHAR(100) NULL,
  height_cm          SMALLINT NULL,
  weight_kg          DECIMAL(5,1) NULL,
  addr_street        VARCHAR(120) NULL,
  barangay           VARCHAR(80) NULL,
  city               VARCHAR(80) NULL,
  province           VARCHAR(80) NULL,
  zip                VARCHAR(10) NULL,
  phone              VARCHAR(20) NULL,
  alt_phone          VARCHAR(20) NULL,
  emergency_name     VARCHAR(100) NULL,
  emergency_relation VARCHAR(40) NULL,
  emergency_phone    VARCHAR(20) NULL,
  position           VARCHAR(80) NULL,
  department         VARCHAR(100) NULL,
  employment_status  VARCHAR(40) NULL,
  date_hired         DATE NULL,
  years_experience   TINYINT NULL,
  specialization     VARCHAR(150) NULL,
  prc_license_no     VARCHAR(40) NULL,
  prc_license_expiry DATE NULL,
  updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS treg_education (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  account_id  INT NOT NULL,
  level       VARCHAR(20) NOT NULL,
  course      VARCHAR(150) NULL,
  school      VARCHAR(150) NULL,
  year_grad   SMALLINT NULL,
  UNIQUE KEY uq_edu (account_id, level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS treg_documents (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  account_id    INT NOT NULL,
  doc_type      VARCHAR(20) NOT NULL,
  original_name VARCHAR(150) NOT NULL,
  stored_name   VARCHAR(80) NOT NULL,
  size          INT NOT NULL,
  uploaded_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_doc (account_id, doc_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
