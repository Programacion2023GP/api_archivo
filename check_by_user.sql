USE archivo;
-- Ver procedures del Enlace JOVANNY (id=5, dept=196)
SELECT p.id, p.user_id, p.departament_id, p.statu_id, p.error, 
       COALESCE(sbp.name, st.name) AS display_status
FROM procedures p
LEFT JOIN signedbyprocedure sbp ON sbp.procedure_id = p.id AND sbp.signedBy = 1
LEFT JOIN status st ON st.id = p.statu_id
WHERE p.user_id = 5 AND p.departament_id = 196
ORDER BY p.created_at DESC;

-- Ver procedures del director LUIS ANGEL (id=3, dept=71) 
-- pero en dept 196 (que es donde dice que los creo)
SELECT p.id, p.user_id, p.departament_id, p.statu_id, p.error, 
       COALESCE(sbp.name, st.name) AS display_status
FROM procedures p
LEFT JOIN signedbyprocedure sbp ON sbp.procedure_id = p.id AND sbp.signedBy = 1
LEFT JOIN status st ON st.id = p.statu_id
WHERE p.departament_id = 196
ORDER BY p.created_at DESC;
