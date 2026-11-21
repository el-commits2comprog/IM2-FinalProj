-- ByteBack full rebuild. Run this from the database/ folder so SOURCE can see the files:
--   cd database
--   C:\xampp\mysql\bin\mysql.exe -u root < run_all.sql
--
-- MySQL Workbench runs SOURCE relative to the client's working directory, not
-- the .sql file. If a SOURCE line fails, cd into database/ and use the mysql
-- client above, or run 00 then 01 (and later files) yourself in this order.
--
-- M2, M3, and M4: uncomment your file when it is ready. Keep this order.

SOURCE 00_create_database.sql;
SOURCE 01_users.sql;
SOURCE 02_reference.sql;
SOURCE 03_items.sql;
-- SOURCE 04_claims.sql;
-- SOURCE 05_matches_transactions.sql;
SOURCE 06_view.sql;
-- SOURCE 07_procedure.sql;
-- SOURCE 08_trigger.sql;
