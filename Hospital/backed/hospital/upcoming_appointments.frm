TYPE=VIEW
query=select `a`.`id` AS `id`,`a`.`appointment_number` AS `appointment_number`,`a`.`appointment_date` AS `appointment_date`,`a`.`appointment_time` AS `appointment_time`,`a`.`duration` AS `duration`,`a`.`appointment_type` AS `appointment_type`,`a`.`status` AS `status`,concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`,`p`.`patient_id` AS `patient_id`,`p`.`phone` AS `patient_phone`,`p`.`email` AS `patient_email`,concat(`d`.`first_name`,\' \',`d`.`last_name`) AS `doctor_name`,`a`.`reason` AS `reason`,`a`.`notes` AS `notes` from ((`hospital`.`appointments` `a` join `hospital`.`patients` `p` on(`a`.`patient_id` = `p`.`id`)) join `hospital`.`users` `d` on(`a`.`doctor_id` = `d`.`id`)) where `a`.`appointment_date` >= curdate() and `a`.`status` in (\'scheduled\',\'confirmed\') order by `a`.`appointment_date`,`a`.`appointment_time`
md5=e280668b09fd7705c73f1fc52138bec6
updatable=1
algorithm=0
definer_user=root
definer_host=localhost
suid=1
with_check_option=0
timestamp=0001762028832320714
create-version=2
source=SELECT `a`.`id` AS `id`, `a`.`appointment_number` AS `appointment_number`, `a`.`appointment_date` AS `appointment_date`, `a`.`appointment_time` AS `appointment_time`, `a`.`duration` AS `duration`, `a`.`appointment_type` AS `appointment_type`, `a`.`status` AS `status`, concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`, `p`.`patient_id` AS `patient_id`, `p`.`phone` AS `patient_phone`, `p`.`email` AS `patient_email`, concat(`d`.`first_name`,\' \',`d`.`last_name`) AS `doctor_name`, `a`.`reason` AS `reason`, `a`.`notes` AS `notes` FROM ((`appointments` `a` join `patients` `p` on(`a`.`patient_id` = `p`.`id`)) join `users` `d` on(`a`.`doctor_id` = `d`.`id`)) WHERE `a`.`appointment_date` >= curdate() AND `a`.`status` in (\'scheduled\',\'confirmed\') ORDER BY `a`.`appointment_date` ASC, `a`.`appointment_time` ASC
client_cs_name=utf8mb4
connection_cl_name=utf8mb4_general_ci
view_body_utf8=select `a`.`id` AS `id`,`a`.`appointment_number` AS `appointment_number`,`a`.`appointment_date` AS `appointment_date`,`a`.`appointment_time` AS `appointment_time`,`a`.`duration` AS `duration`,`a`.`appointment_type` AS `appointment_type`,`a`.`status` AS `status`,concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`,`p`.`patient_id` AS `patient_id`,`p`.`phone` AS `patient_phone`,`p`.`email` AS `patient_email`,concat(`d`.`first_name`,\' \',`d`.`last_name`) AS `doctor_name`,`a`.`reason` AS `reason`,`a`.`notes` AS `notes` from ((`hospital`.`appointments` `a` join `hospital`.`patients` `p` on(`a`.`patient_id` = `p`.`id`)) join `hospital`.`users` `d` on(`a`.`doctor_id` = `d`.`id`)) where `a`.`appointment_date` >= curdate() and `a`.`status` in (\'scheduled\',\'confirmed\') order by `a`.`appointment_date`,`a`.`appointment_time`
mariadb-version=100432
