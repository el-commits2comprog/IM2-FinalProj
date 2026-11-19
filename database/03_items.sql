SET NAMES utf8mb4;
USE byteback;

DROP TABLE IF EXISTS items;

CREATE TABLE items (
    item_id     INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INTEGER NOT NULL,
    item_type   VARCHAR(10) NOT NULL,
    item_name   VARCHAR(100) NOT NULL,
    category_id INTEGER UNSIGNED NOT NULL,   
    location_id INTEGER UNSIGNED NOT NULL,   
    event_date  DATE NOT NULL,    
    photo_path VARCHAR(255) NULL,  
    description TEXT NOT NULL,
    status      VARCHAR(20) NOT NULL DEFAULT 'Pending',
    reviewed_by INT NULL,
    review_remarks TEXT NULL, 
    reviewed_at DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 
    CONSTRAINT chk_item_type CHECK (item_type IN ('Lost', 'Found')),
    CONSTRAINT chk_status CHECK (status IN ('Pending', 'Approved', 'Rejected', 'Matched', 'Claimed', 'Returned')),
    CONSTRAINT fk_items_user
        FOREIGN KEY (user_id) REFERENCES users(user_id),
    CONSTRAINT fk_items_category
        FOREIGN KEY (category_id) REFERENCES categories(category_id),
    CONSTRAINT fk_items_location
        FOREIGN KEY (location_id) REFERENCES locations(location_id),
    CONSTRAINT fk_items_reviewer
        FOREIGN KEY (reviewed_by) REFERENCES users(user_id),

    INDEX idx_items_status (status),
    INDEX idx_items_user (user_id),
    FULLTEXT INDEX ft_items_search (item_name, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO items (user_id, category_id, location_id, item_name, description, item_type, event_date, photo_path, status, reviewed_by, review_remarks, reviewed_at)
VALUES
    (3, 1, 2, 'Black Samsung Phone',
    'Black Samsung phone with a clear case.',
    'Lost', '2026-11-09', 'uploads/black_phone.jpg',
    'Pending', NULL, NULL, NULL),

    (4, 5, 1, 'Blue Backpack',
    'Blue backpack found near the library entrance.',
    'Found', '2026-11-05', 'uploads/blue_backpack.jpg',
    'Approved', 1, 'Report verified and approved.',
    '2026-11-11 14:30:00');
