TYPE=VIEW
query=select `p`.`id` AS `patient_id`,`p`.`patient_id` AS `patient_number`,concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`,`p`.`age` AS `age`,`p`.`gender` AS `gender`,`p`.`phone` AS `phone`,`a`.`appointment_date` AS `appointment_date`,`a`.`appointment_time` AS `appointment_time`,`a`.`reason` AS `visit_reason` from (`hospital`.`patients` `p` left join `hospital`.`appointments` `a` on(`p`.`id` = `a`.`patient_id` and cast(`a`.`appointment_date` as date) = curdate() and `a`.`status` = \'scheduled\')) where !exists(select 1 from `hospital`.`triage_assessments` `ta` where `ta`.`patient_id` = `p`.`id` and cast(`ta`.`assessment_date` as date) = curdate() and `ta`.`status` = \'completed\' limit 1) order by `a`.`appointment_time`
md5=bf81823e97d332c1cfbf46c737d44def
updatable=0
algorithm=0
definer_user=root
definer_host=localhost
suid=1
with_check_option=0
timestamp=0001762028832283577
create-version=2
source=SELECT `p`.`id` AS `patient_id`, `p`.`patient_id` AS `patient_number`, concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`, `p`.`age` AS `age`, `p`.`gender` AS `gender`, `p`.`phone` AS `phone`, `a`.`appointment_date` AS `appointment_date`, `a`.`appointment_time` AS `appointment_time`, `a`.`reason` AS `visit_reason` FROM (`patients` `p` left join `appointments` `a` on(`p`.`id` = `a`.`patient_id` and cast(`a`.`appointment_date` as date) = curdate() and `a`.`status` = \'scheduled\')) WHERE !exists(select 1 from `triage_assessments` `ta` where `ta`.`patient_id` = `p`.`id` AND cast(`ta`.`assessment_date` as date) = curdate() AND `ta`.`status` = \'completed\' limit 1) ORDER BY `a`.`appointment_time` ASC
client_cs_name=utf8mb4
connection_cl_name=utf8mb4_general_ci
view_body_utf8=select `p`.`id` AS `patient_id`,`p`.`patient_id` AS `patient_number`,concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`,`p`.`age` AS `age`,`p`.`gender` AS `gender`,`p`.`phone` AS `phone`,`a`.`appointment_date` AS `appointment_date`,`a`.`appointment_time` AS `appointment_time`,`a`.`reason` AS `visit_reason` from (`hospital`.`patients` `p` left join `hospital`.`appointments` `a` on(`p`.`id` = `a`.`patient_id` and cast(`a`.`appointment_date` as date) = curdate() and `a`.`status` = \'scheduled\')) where !exists(select 1 from `hospital`.`triage_assessments` `ta` where `ta`.`patient_id` = `p`.`id` and cast(`ta`.`assessment_date` as date) = curdate() and `ta`.`status` = \'completed\' limit 1) order by `a`.`appointment_time`
mariadb-version=100432
