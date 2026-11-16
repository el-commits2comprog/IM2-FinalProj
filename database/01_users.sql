-- ByteBack [M1] users
-- Spellings must match config/constants.php (ROLE_* and USER_STATUS_*).
--
-- Sample login (every account uses the same password):
--   Admin    maria.lourdes.santos@bicol-u.edu.ph    Password123!
--   Student  rafael.cruz@bicol-u.edu.ph             Password123!
--   Student  ana.villanueva@bicol-u.edu.ph          Password123!
--   Student  jose.mendoza@bicol-u.edu.ph            Password123!
--
-- Hashes were generated with:
--   php -r "echo password_hash('Password123!', PASSWORD_DEFAULT);"
-- Rows are inserted out of alphabetical order on purpose (see queries/q1).

SET NAMES utf8mb4;
USE byteback;

DROP TABLE IF EXISTS users;

CREATE TABLE users (
  user_id INT NOT NULL AUTO_INCREMENT,
  student_number VARCHAR(20) NULL,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('Admin', 'Student') NOT NULL DEFAULT 'Student',
  status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  UNIQUE KEY uq_users_student_number (student_number),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_full_name (full_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (student_number, full_name, email, password_hash, role, status) VALUES
  (NULL, 'Maria Lourdes Santos', 'maria.lourdes.santos@bicol-u.edu.ph', '$2y$10$SxJdWnQsFCSj/scNQGhssO3EQhrjkeERtA0ItNChQG2LlgqBwvFvG', 'Admin', 'Active'),
  ('2021-11842', 'Rafael Cruz', 'rafael.cruz@bicol-u.edu.ph', '$2y$10$uXYGGI1yeKBLta/9Fj9xrOwBslMJBvz09pxuqPe5Ex7piAm8wUz7u', 'Student', 'Active'),
  ('2023-10421', 'Ana Villanueva', 'ana.villanueva@bicol-u.edu.ph', '$2y$10$6OqGBhwQXt9TAjTpLyWF7Oc2ifbH8G.s3Yne6YBpgcQi3f7cmjAL6', 'Student', 'Active'),
  ('2022-13007', 'Jose Mendoza', 'jose.mendoza@bicol-u.edu.ph', '$2y$10$HWaVu9VucJWVXsRnzWvrYOEmk10sba0YCGrt9KNLwBLxH.GA9ZuKe', 'Student', 'Active');
