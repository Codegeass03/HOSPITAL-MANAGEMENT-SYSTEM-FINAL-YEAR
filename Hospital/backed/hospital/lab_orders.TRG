TYPE=TRIGGERS
triggers='CREATE DEFINER=`root`@`localhost` TRIGGER `generate_lab_order_number` BEFORE INSERT ON `lab_orders` FOR EACH ROW BEGIN\n    DECLARE sequence_num INT;\n    \n    IF NEW.order_number IS NULL THEN\n        -- Get the next sequence number based on existing records\n        SELECT COALESCE(MAX(id), 0) + 1 INTO sequence_num FROM lab_orders;\n        SET NEW.order_number = CONCAT(\'LAB-\', DATE_FORMAT(NOW(), \'%Y%m\'), \'-\', LPAD(sequence_num, 4, \'0\'));\n    END IF;\nEND'
sql_modes=524288
definers='root@localhost'
client_cs_names='utf8mb4'
connection_cl_names='utf8mb4_general_ci'
db_cl_names='utf8mb4_general_ci'
created=1762028828650750
