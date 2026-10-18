TYPE=TRIGGERS
triggers='CREATE DEFINER=`root`@`localhost` TRIGGER `generate_prescription_number` BEFORE INSERT ON `prescriptions` FOR EACH ROW BEGIN\n    DECLARE next_num INT;\n    \n    IF NEW.prescription_number IS NULL THEN\n        SELECT COALESCE(\n            MAX(CAST(SUBSTRING(prescription_number, -4) AS UNSIGNED)), \n            0\n        ) + 1 INTO next_num\n        FROM prescriptions\n        WHERE prescription_number LIKE CONCAT(\'RX-\', DATE_FORMAT(NOW(), \'%Y%m\'), \'-%\');\n        \n        SET NEW.prescription_number = CONCAT(\'RX-\', DATE_FORMAT(NOW(), \'%Y%m\'), \'-\', LPAD(next_num, 4, \'0\'));\n    END IF;\nEND'
sql_modes=524288
definers='root@localhost'
client_cs_names='utf8mb4'
connection_cl_names='utf8mb4_general_ci'
db_cl_names='utf8mb4_general_ci'
created=1762028830208648
