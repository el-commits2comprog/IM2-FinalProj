-- q1_users_alphabetical.sql
-- Week 3 query for ByteBack.
-- Lists every user account in A-to-Z order by full name.
-- Sample rows are inserted out of that order (Maria, Rafael, Ana, Jose),
-- so a correct result is: Ana Villanueva, Jose Mendoza,
-- Maria Lourdes Santos, Rafael Cruz.

USE byteback;

SELECT user_id, full_name, email, role, status
FROM users
ORDER BY full_name ASC;
