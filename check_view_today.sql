USE archivo;
SELECT * FROM procedures_created_at WHERE DATE(order_date) = CURDATE();
