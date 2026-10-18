TYPE=TRIGGERS
triggers='CREATE DEFINER=`root`@`localhost` TRIGGER `log_employment_change` AFTER UPDATE ON `staff_profiles` FOR EACH ROW BEGIN\n\n    IF OLD.job_title != NEW.job_title OR OLD.department != NEW.department OR OLD.salary != NEW.salary THEN\n\n        INSERT INTO staff_employment_history (\n\n            user_id, employee_number, department, job_title, \n\n            employment_type, salary, start_date, end_date\n\n        ) VALUES (\n\n            OLD.user_id, OLD.employee_number, OLD.department, OLD.job_title,\n\n            \'full-time\', OLD.salary, OLD.hire_date, CURDATE()\n\n        );\n\n    END IF;\n\nEND'
sql_modes=524288
definers='root@localhost'
client_cs_names='utf8mb4'
connection_cl_names='utf8mb4_general_ci'
db_cl_names='utf8mb4_general_ci'
created=1762028831108715
