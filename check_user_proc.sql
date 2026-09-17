USE archivo;
-- Check all procedures for user 3
SELECT p.id, p.user_id, p.departament_id, p.statu_id, p.error, p.created_at,
       COALESCE(s.name, st.name) AS status_name
FROM procedures p
LEFT JOIN signedbyprocedure s ON s.procedure_id = p.id AND s.signedBy = 1
LEFT JOIN status st ON st.id = p.statu_id
WHERE p.user_id = 3
ORDER BY p.created_at DESC;
