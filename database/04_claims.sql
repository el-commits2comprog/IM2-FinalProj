
-- ByteBack [M3] Claims table
-- Depends on: users and items tables.

SET NAMES utf8mb4;
USE byteback;

CREATE TABLE claims (
    claim_id INT NOT NULL AUTO_INCREMENT,
    item_id INT NOT NULL,
    claimant_id INT NOT NULL,
    proof_description TEXT NOT NULL,
    proof_photo VARCHAR(255) NULL,
    status ENUM('Pending', 'Approved', 'Rejected')
        NOT NULL DEFAULT 'Pending',
    reviewed_by INT NULL,
    review_remarks TEXT NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (claim_id),

    KEY idx_claims_item (item_id),
    KEY idx_claims_claimant (claimant_id),
    KEY idx_claims_status (status),

    CONSTRAINT fk_claims_item
        FOREIGN KEY (item_id) REFERENCES items (item_id),

    CONSTRAINT fk_claims_claimant
        FOREIGN KEY (claimant_id) REFERENCES users (user_id),

    CONSTRAINT fk_claims_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users (user_id)

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
