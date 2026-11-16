-- ByteBack [M1]
-- Rebuilds the database from scratch. This deletes every table in `byteback`.
-- Run from the database/ folder via run_all.sql.

SET NAMES utf8mb4;

DROP DATABASE IF EXISTS byteback;

CREATE DATABASE byteback
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE byteback;
