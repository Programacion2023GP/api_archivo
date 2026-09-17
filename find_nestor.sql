USE archivo;
-- Find Nestor Josue in users
SELECT id, firstName, paternalSurname, fullName, role, departament_id, active FROM users WHERE firstName LIKE '%nestor%' OR paternalSurname LIKE '%josue%' OR fullName LIKE '%nestor%' OR fullName LIKE '%josue%';

-- Show all users with their roles
SELECT id, firstName, paternalSurname, role, departament_id, active FROM users ORDER BY id;
