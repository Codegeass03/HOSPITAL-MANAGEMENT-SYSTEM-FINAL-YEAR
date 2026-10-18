TYPE=TRIGGERS
triggers='CREATE DEFINER=`root`@`localhost` TRIGGER `generate_record_number` BEFORE INSERT ON `medical_records` FOR EACH ROW BEGIN\n\n    IF NEW.record_number IS NULL THEN\n\n        SET NEW.record_number = CONCAT(\'EHR\', DATE_FORMAT(NOW(), \'%Y%m\'), \'-\', LPAD(LAST_INSERT_ID() + 1, 5, \'0\'));\n\n    END IF;\n\nEND'
sql_modes=524288
definers='root@localhost'
client_cs_names='utf8mb4'
connection_cl_names='utf8mb4_general_ci'
db_cl_names='utf8mb4_general_ci'
created=1762033700545455
