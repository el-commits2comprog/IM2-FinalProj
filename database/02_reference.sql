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
    ('Electronics'), ('Clothing'), ('Books'), ('IDs & Cards'), ('Bags'), ('Keys'), ('Other');

INSERT INTO locations (location_name) VALUES
    ('Library'), ('CS Canteen'), ('CENG Gymnasium'), ('BU Commencement Grounds'), ('BU Oval'), ('Other');

