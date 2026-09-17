USE archivo;
-- Crear trámite de prueba para JOVANNY (user_id=5, dept=196)
INSERT INTO procedures (user_id, departament_id, process_id, statu_id, error, errorFieldsKey, errorDescriptionField, boxes, year, startDate, endDate, description, totalPages, fisic, electronic, accounting_fiscal_value, retention_period_current, retention_period_archive, location_building, location_furniture, location_position, observation, created_at, updated_at)
VALUES (5, 196, 1, 4, 1, 'boxes,year', 'REVISAR CAJAS Y AÑO', 2, 2026, '2026-09-10', '2026-09-10', 'PRUEBA USUARIO REVISADO', 10, 1, 0, 1, 1, 1, 1, 1, 1, 'Trámite de prueba', NOW(), NOW());
