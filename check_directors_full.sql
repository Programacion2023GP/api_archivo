USE archivo;
SELECT d.id, d.name, u.id AS user_id, u.fullName, u.role, u.departament_id, u.active
FROM users u
LEFT JOIN departaments d ON d.id = u.departament_id
WHERE LOWER(u.role) = 'director' AND u.active = 1;
