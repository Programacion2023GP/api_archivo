USE archivo;
SELECT d.id, d.name, d.departament_id AS parent_id, d.authorized, d.active AS dept_active,
       u.id AS user_id, u.fullName AS director_name, u.role, u.active AS user_active
FROM departaments d
LEFT JOIN users u ON d.id = u.departament_id AND u.role LIKE '%irector%' AND u.active = 1
WHERE d.active = 1
ORDER BY d.id;
