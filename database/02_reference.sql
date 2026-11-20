SET NAMES utf8mb4;
USE byteback;

DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS locations;

CREATE TABLE categories (
    category_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE locations (
    location_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    location_name VARCHAR(100) NOT NULL UNIQUE 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (category_name) VALUES
    ('Electronics'), ('Bags & Wallets'), ('Clothing & Accessories'), ('School Supplies'), ('IDs & Cards'), ('Personal Items'), ('Sports Equipment'), ('Documents'), ('Keys & Keychains'), ('Other');

INSERT INTO locations (location_name) VALUES
    ('BU Oval'), ('BU Commencement Grounds'), ('CS Canteen'), ('BU Library'), ('BU Daraga Campus'), ('BU East Campus'), ('BU West (Main Campus)'), ('Admissions Office'), ('BU OSAS'), ('BU Chapel'), ('BUHS'), ('Guinobatan Campus'), ('Tabaco Campus'), ('Polangui Campus'), ('Gubat Campus'), ('Ligao Campus'), ('Other');
