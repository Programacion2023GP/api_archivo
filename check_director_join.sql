USE archivo;
SELECT d.id, d.name, u.id AS user_id, u.fullName AS director_name, u.role
FROM departaments d
LEFT JOIN users u ON d.id = u.departament_id AND LOWER(u.role) = 'director' AND u.active = 1
WHERE d.id IN (5, 71, 196);
