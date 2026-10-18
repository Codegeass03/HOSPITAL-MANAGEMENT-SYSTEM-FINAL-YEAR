TYPE=TRIGGERS
triggers='CREATE DEFINER=`root`@`localhost` TRIGGER `generate_appointment_number` BEFORE INSERT ON `appointments` FOR EACH ROW BEGIN\n\n    IF NEW.appointment_number IS NULL THEN\n\n        SET NEW.appointment_number = CONCAT(\'APT-\', DATE_FORMAT(NOW(), \'%Y%m\'), \'-\', LPAD(LAST_INSERT_ID() + 1, 5, \'0\'));\n\n    END IF;\n\nEND'
sql_modes=524288
definers='root@localhost'
client_cs_names='utf8mb4'
connection_cl_names='utf8mb4_general_ci'
db_cl_names='utf8mb4_general_ci'
created=1762028827682393
