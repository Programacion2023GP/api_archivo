USE archivo;
-- Ver todos los procedures y sus usuarios/departamentos
SELECT p.id, p.user_id, p.departament_id, p.statu_id, p.error, p.created_at,
       u.firstName, u.paternalSurname, u.role AS user_role, u.departament_id AS user_dept,
       d.name AS dept_name,
       st.name AS status_name
FROM procedures p
JOIN users u ON u.id = p.user_id
JOIN departaments d ON d.id = p.departament_id
LEFT JOIN status st ON st.id = p.statu_id
ORDER BY p.id;
