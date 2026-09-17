USE archivo;
-- Check user 3 info
SELECT id, firstName, paternalSurname, role, departament_id, active FROM users WHERE id=3;
-- Check all procedures and their statuses
SELECT p.id, p.user_id, p.departament_id, p.statu_id, p.error, p.errorDescriptionField, st.name as status_name
FROM procedures p
LEFT JOIN status st ON st.id = p.statu_id
ORDER BY p.id;
