SET NAMES utf8mb4;
USE byteback;

DROP TABLE IF EXISTS items;

CREATE TABLE items (
    item_id     INTEGER NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INTEGER NOT NULL,
    item_type   ENUM('Lost', 'Found') NOT NULL,
    item_name   VARCHAR(100) NOT NULL,
    category_id INTEGER UNSIGNED NOT NULL,
    location_id INTEGER UNSIGNED NOT NULL,
    custom_location VARCHAR(255) NULL,
    event_date  DATE NOT NULL,
    photo_path  VARCHAR(255) NULL,
    description TEXT NOT NULL,
    status      ENUM('Pending', 'Approved', 'Rejected', 'Matched', 'Claimed', 'Returned') NOT NULL DEFAULT 'Pending',
    reviewed_by INT NULL,
    review_remarks TEXT NULL,
    reviewed_at DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

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

INSERT INTO items
    (user_id, item_type, item_name, category_id, location_id, custom_location,
     event_date, photo_path, description, status, reviewed_by, review_remarks,
     reviewed_at, created_at)
VALUES
    (3, 'Lost', 'Black Samsung Phone', 1, 2, NULL,
     '2026-11-09', 'uploads/black_phone.webp',
     'Black Samsung phone with a clear case.',
     'Approved', 1, 'Report verified and approved.',
     '2026-11-10 12:20:00', '2026-11-20 17:47:39'),

    (4, 'Found', 'Blue Backpack', 2, 4, NULL,
     '2026-11-05', 'uploads/blue_backpack.jpg',
     'Blue backpack found at the library.',
     'Returned', 1, 'Report verified and approved.',
     '2026-11-11 14:30:00', '2026-11-20 17:47:39'),

    (3, 'Found', 'Black Umbrella', 6, 7, '2nd Floor, CS Building 2, near Room 201',
     '2026-11-08', 'uploads/black_umbrella.webp',
     'Black folding umbrella left at the 2nd floor stairs',
     'Approved', 1, 'Report verified and approved.',
     '2026-11-10 10:01:00', '2026-11-20 17:47:39'),

    (2, 'Found', 'Wireless Earbuds', 1, 7, '2nd Floor, BUCN Building',
     '2026-11-14', 'uploads/black_earbuds.jpg',
     'Black wireless earbuds in a charging case.',
     'Approved', 1, 'Report verified and approved.',
     '2026-11-20 18:00:26', '2026-11-20 17:54:01'),

    (3, 'Lost', 'Student ID', 5, 7, '2nd Floor, CS Building 4, near Room 203',
     '2026-11-17', NULL,
     'Student ID inside a pink ID holder with a 1x1 picture attached at the back.',
     'Approved', 1, 'Report verified and approved.',
     '2026-11-20 22:55:48', '2026-11-20 22:54:41'),

    (2, 'Found', 'Black Cellphone', 1, 2, NULL,
     '2026-11-19', 'uploads/337a36b889527d0903fddc4e.webp',
     'Found a black cellphone placed on a table inside the kubo within BU grounds.',
     'Approved', 1, 'Report verified and approved.',
     '2026-11-20 01:02:51', '2026-11-20 23:52:05'),

    (2, 'Lost', 'water bottle', 6, 1, 'new grandstand',
     '2026-11-16', 'uploads/5cdd49fafdbc51a8d9c72758f958e228.jpg',
     'blue water bottle with stickers',
     'Approved', 1, 'Report verified and approved.',
     '2026-11-20 01:03:22', '2026-11-20 23:59:34'),

    (4, 'Found', 'blue notebook', 4, 4, NULL,
     '2026-11-15', 'uploads/3a582a8e771db2505c59c9c5.jpg',
     'blue spiral notebook with handwritten lecture notes inside',
     'Claimed', 1, 'Verified.',
     '2026-11-21 12:09:29', '2026-11-21 00:14:06'),

    (4, 'Found', 'keys with blue lanyard', 9, 7, 'near gate 1 sidewalk',
     '2026-11-18', 'uploads/8a312f3b64fc0b2e9ed4366a.jpg',
     '3 keys attached to a blue braided lanyard with a colorful circular design.',
     'Claimed', 1, 'Reviewed.',
     '2026-11-21 03:13:30', '2026-11-21 00:40:31'),

    (4, 'Found', 'gray hoodie', 3, 3, NULL,
     '2026-11-18', 'uploads/4c16c6d794ed229e6d40ce33.png',
     'gray hoodie, size M, left on a chair',
     'Pending', NULL, NULL,
     NULL, '2026-11-21 03:46:49'),

    (4, 'Lost', 'Brown envelope', 8, 17, 'sidewalk near the gate 1',
     '2026-11-16', NULL,
     'brown envelope with enrollment form inside',
     'Approved', 1, 'Reviewed and approved.',
     '2026-11-21 04:28:49', '2026-11-21 04:28:03'),

    (2, 'Lost', 'silver wristwatch', 3, 17, 'BUCENG Gymnasium',
     '2026-11-18', 'uploads/60f4d95b0e3f0df8bbadf7a3.png',
     'silver casio wristwatch with a metal strap and a round black dial',
     'Rejected', 1, 'inconsistent item information',
     '2026-11-21 04:37:44', '2026-11-21 04:37:35'),

    (2, 'Found', 'scientific calculator', 4, 5, '2nd floor, CBEM Building',
     '2026-11-20', 'uploads/cd11c3331f104d2334a0ff7d.jpg',
     'light pink casio scientific calculator',
     'Pending', NULL, NULL,
     NULL, '2026-11-21 05:07:15');