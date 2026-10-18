TYPE=VIEW
query=select `mr`.`id` AS `id`,`mr`.`record_number` AS `record_number`,`mr`.`record_type` AS `record_type`,`mr`.`record_date` AS `record_date`,`mr`.`diagnosis` AS `diagnosis`,`mr`.`treatment` AS `treatment`,`mr`.`status` AS `status`,`mr`.`priority` AS `priority`,`p`.`patient_id` AS `patient_id`,concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`,`p`.`age` AS `age`,concat(`d`.`first_name`,\' \',`d`.`last_name`) AS `doctor_name`,`mr`.`department` AS `department`,`mr`.`notes` AS `notes`,`mr`.`created_at` AS `created_at` from ((`backed`.`medical_records` `mr` join `backed`.`patients` `p` on(`mr`.`patient_id` = `p`.`id`)) join `backed`.`users` `d` on(`mr`.`doctor_id` = `d`.`id`)) order by `mr`.`record_date` desc,`mr`.`created_at` desc
md5=24ecc9f47514159a57cebb3fc635094c
updatable=1
algorithm=0
definer_user=root
definer_host=localhost
suid=1
with_check_option=0
timestamp=0001762033703742683
create-version=2
source=SELECT `mr`.`id` AS `id`, `mr`.`record_number` AS `record_number`, `mr`.`record_type` AS `record_type`, `mr`.`record_date` AS `record_date`, `mr`.`diagnosis` AS `diagnosis`, `mr`.`treatment` AS `treatment`, `mr`.`status` AS `status`, `mr`.`priority` AS `priority`, `p`.`patient_id` AS `patient_id`, concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`, `p`.`age` AS `age`, concat(`d`.`first_name`,\' \',`d`.`last_name`) AS `doctor_name`, `mr`.`department` AS `department`, `mr`.`notes` AS `notes`, `mr`.`created_at` AS `created_at` FROM ((`medical_records` `mr` join `patients` `p` on(`mr`.`patient_id` = `p`.`id`)) join `users` `d` on(`mr`.`doctor_id` = `d`.`id`)) ORDER BY `mr`.`record_date` DESC, `mr`.`created_at` DESC
client_cs_name=utf8mb4
connection_cl_name=utf8mb4_general_ci
view_body_utf8=select `mr`.`id` AS `id`,`mr`.`record_number` AS `record_number`,`mr`.`record_type` AS `record_type`,`mr`.`record_date` AS `record_date`,`mr`.`diagnosis` AS `diagnosis`,`mr`.`treatment` AS `treatment`,`mr`.`status` AS `status`,`mr`.`priority` AS `priority`,`p`.`patient_id` AS `patient_id`,concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`,`p`.`age` AS `age`,concat(`d`.`first_name`,\' \',`d`.`last_name`) AS `doctor_name`,`mr`.`department` AS `department`,`mr`.`notes` AS `notes`,`mr`.`created_at` AS `created_at` from ((`backed`.`medical_records` `mr` join `backed`.`patients` `p` on(`mr`.`patient_id` = `p`.`id`)) join `backed`.`users` `d` on(`mr`.`doctor_id` = `d`.`id`)) order by `mr`.`record_date` desc,`mr`.`created_at` desc
mariadb-version=100432
