-- ByteBack [M4] matches and transactions
-- Dependence on: 01_users.sql, 03_items.sql, 04_claims.sql (run those first).
-- Spellings must match config/constants.php (TRANSACTION_TYPE_*).
--
-- MATCHES      : a suggested pairing of one Lost item and one Found item.
--                is_confirmed = 0 -> system suggestion, 1 -> an admin confirmed it.
-- TRANSACTIONS : the release / return log. One row per release of an item.
--
-- transaction_type values:
--   'Claim Approved' -> inserted by sp_process_claim (M3) when a claim is approved
--   'Returned'       -> inserted by return_handler.php (M4) when the handover is recorded
-- Query 3 (returns per day) counts only transaction_type = 'Returned'.
--
-- Sample rows are picked from the existing sample items and claims with
-- INSERT ... SELECT, so they work with whatever IDs 03 and 04 created.
-- If 03 or 04 has no matching rows, the sample inserts simply add nothing.

SET NAMES utf8mb4;
USE byteback;

DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS matches;

-- ---------------------------------------------------------------
-- MATCHES
-- ---------------------------------------------------------------
CREATE TABLE matches (
  match_id INT NOT NULL AUTO_INCREMENT,
  lost_item_id INT NOT NULL,
  found_item_id INT NOT NULL,
  is_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  confirmed_by INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (match_id),
  UNIQUE KEY uq_matches_pair (lost_item_id, found_item_id),
  KEY idx_matches_found_item (found_item_id),
  KEY idx_matches_confirmed_by (confirmed_by),
  CONSTRAINT chk_matches_different_items CHECK (lost_item_id <> found_item_id),
  CONSTRAINT fk_matches_lost_item
    FOREIGN KEY (lost_item_id) REFERENCES items (item_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_matches_found_item
    FOREIGN KEY (found_item_id) REFERENCES items (item_id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_matches_confirmed_by
    FOREIGN KEY (confirmed_by) REFERENCES users (user_id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- TRANSACTIONS
-- ---------------------------------------------------------------
CREATE TABLE transactions (
  transaction_id INT NOT NULL AUTO_INCREMENT,
  item_id INT NOT NULL,
  claim_id INT NOT NULL,
  admin_id INT NOT NULL,
  transaction_type ENUM('Claim Approved', 'Returned') NOT NULL DEFAULT 'Returned',
  transaction_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  remarks VARCHAR(255) NULL,
  PRIMARY KEY (transaction_id),
  KEY idx_transactions_item (item_id),
  KEY idx_transactions_claim (claim_id),
  KEY idx_transactions_admin (admin_id),
  KEY idx_transactions_date (transaction_date),
  CONSTRAINT fk_transactions_item
    FOREIGN KEY (item_id) REFERENCES items (item_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_transactions_claim
    FOREIGN KEY (claim_id) REFERENCES claims (claim_id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_transactions_admin
    FOREIGN KEY (admin_id) REFERENCES users (user_id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------
-- Sample data: MATCHES
-- Pairs Lost items with Found items of the same category.
-- The first pair is confirmed by the admin, the rest stay as suggestions.
-- ---------------------------------------------------------------
INSERT INTO matches (lost_item_id, found_item_id, is_confirmed, confirmed_by)
SELECT l.item_id,
       f.item_id,
       0,
       NULL
FROM items l
JOIN items f
  ON f.category_id = l.category_id
WHERE l.item_type = 'Lost'
  AND f.item_type = 'Found'
  AND l.status IN ('Approved', 'Matched')
  AND f.status IN ('Approved', 'Matched')
ORDER BY l.item_id, f.item_id
LIMIT 3;

-- Confirm the first suggested pair (by the sample admin).
UPDATE matches
SET is_confirmed = 1, -- True
    confirmed_by = (SELECT user_id FROM users WHERE role = 'Admin' ORDER BY user_id LIMIT 1)
WHERE match_id = (SELECT first_id FROM (SELECT MIN(match_id) AS first_id FROM matches) AS t);

-- ---------------------------------------------------------------
-- Sample data: TRANSACTIONS
-- One 'Claim Approved' row per approved claim, plus a 'Returned' row for
-- the first one so Query 3 (returns per day) has data.
-- ---------------------------------------------------------------
INSERT INTO transactions (item_id, claim_id, admin_id, transaction_type, transaction_date, remarks)
SELECT c.item_id,
       c.claim_id,
       (SELECT user_id FROM users WHERE role = 'Admin' ORDER BY user_id LIMIT 1),
       'Claim Approved',
       NOW() - INTERVAL 2 DAY,
       'Proof of ownership verified.'
FROM claims c
WHERE c.status = 'Approved';

INSERT INTO transactions (item_id, claim_id, admin_id, transaction_type, transaction_date, remarks)
SELECT c.item_id,
       c.claim_id,
       (SELECT user_id FROM users WHERE role = 'Admin' ORDER BY user_id LIMIT 1),
       'Returned',
       NOW() - INTERVAL 1 DAY,
       'Item handed over at the admin office.'
FROM claims c
WHERE c.status = 'Approved'
ORDER BY c.claim_id
LIMIT 1;