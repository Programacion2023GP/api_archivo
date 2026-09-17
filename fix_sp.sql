USE archivo;

DROP PROCEDURE IF EXISTS sp_authorization_chain;

DELIMITER $$

CREATE PROCEDURE sp_authorization_chain(IN dept_id INT)
BEGIN
    WITH RECURSIVE auth_path AS (
        SELECT 
            d.id,
            d.departament_id,
            d.name,
            d.authorized,
            u.id as user_id,
            u.fullName as director_name,
            0 as nivel,
            CAST(d.id AS CHAR(1000)) as path
        FROM departaments d
        LEFT JOIN users u ON d.id = u.departament_id AND u.role = 'director'
        WHERE d.id = dept_id 
        
        UNION ALL
        
        SELECT 
            p.id,
            p.departament_id,
            p.name,
            p.authorized,
            pu.id as user_id,
            pu.fullName as director_name,
            ap.nivel + 1,
            CONCAT(ap.path, ' <- ', p.id)
        FROM auth_path ap
        INNER JOIN departaments p ON ap.departament_id = p.id
        LEFT JOIN users pu ON p.id = pu.departament_id AND pu.role = 'director'
        WHERE p.authorized = 1
    )
    SELECT 
        id as department_id,
        user_id,
        nivel as level,
        name as `group`,
        CASE 
            WHEN director_name IS NOT NULL THEN director_name
            ELSE CONCAT('(Sin director - ', name, ')')
        END as name,
        authorized,
        path as hierarchy_path
    FROM auth_path
    WHERE authorized = 1
    ORDER BY nivel asc;
END$$

DELIMITER ;
