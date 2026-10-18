TYPE=TRIGGERS
triggers='CREATE DEFINER=`root`@`localhost` TRIGGER `generate_visit_number` BEFORE INSERT ON `patient_visits` FOR EACH ROW BEGIN\n    IF NEW.visit_number IS NULL THEN\n        SET NEW.visit_number = CONCAT(\'VISIT-\', DATE_FORMAT(NOW(), \'%Y%m%d-\'), LPAD(FLOOR(RAND() * 10000), 4, \'0\'));\n    END IF;\nEND'
sql_modes=524288
definers='root@localhost'
client_cs_names='utf8mb4'
connection_cl_names='utf8mb4_general_ci'
db_cl_names='utf8mb4_general_ci'
created=1762033701328754
