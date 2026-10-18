TYPE=VIEW
query=select `mr`.`patient_id` AS `patient_id`,`p`.`patient_id` AS `patient_number`,concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`,`p`.`age` AS `age`,`p`.`gender` AS `gender`,`mr`.`record_date` AS `record_date`,`ta`.`triage_category` AS `triage_category`,`ta`.`priority` AS `triage_priority`,`mr`.`workflow_status` AS `workflow_status`,`mr`.`diagnosis` AS `diagnosis`,`ta`.`chief_complaint` AS `chief_complaint` from ((`backed`.`medical_records` `mr` join `backed`.`patients` `p` on(`mr`.`patient_id` = `p`.`id`)) left join `backed`.`triage_assessments` `ta` on(`mr`.`triage_assessment_id` = `ta`.`id`)) where `mr`.`workflow_status` in (\'triage_completed\',\'doctor_review\') and `mr`.`assigned_doctor_id` is not null order by `ta`.`priority` desc,`mr`.`record_date` desc
md5=61a30cf744dbaf3c28781837938f46a2
updatable=0
algorithm=0
definer_user=root
definer_host=localhost
suid=1
with_check_option=0
timestamp=0001762033703701995
create-version=2
source=SELECT `mr`.`patient_id` AS `patient_id`, `p`.`patient_id` AS `patient_number`, concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`, `p`.`age` AS `age`, `p`.`gender` AS `gender`, `mr`.`record_date` AS `record_date`, `ta`.`triage_category` AS `triage_category`, `ta`.`priority` AS `triage_priority`, `mr`.`workflow_status` AS `workflow_status`, `mr`.`diagnosis` AS `diagnosis`, `ta`.`chief_complaint` AS `chief_complaint` FROM ((`medical_records` `mr` join `patients` `p` on(`mr`.`patient_id` = `p`.`id`)) left join `triage_assessments` `ta` on(`mr`.`triage_assessment_id` = `ta`.`id`)) WHERE `mr`.`workflow_status` in (\'triage_completed\',\'doctor_review\') AND `mr`.`assigned_doctor_id` is not null ORDER BY `ta`.`priority` DESC, `mr`.`record_date` DESC
client_cs_name=utf8mb4
connection_cl_name=utf8mb4_general_ci
view_body_utf8=select `mr`.`patient_id` AS `patient_id`,`p`.`patient_id` AS `patient_number`,concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`,`p`.`age` AS `age`,`p`.`gender` AS `gender`,`mr`.`record_date` AS `record_date`,`ta`.`triage_category` AS `triage_category`,`ta`.`priority` AS `triage_priority`,`mr`.`workflow_status` AS `workflow_status`,`mr`.`diagnosis` AS `diagnosis`,`ta`.`chief_complaint` AS `chief_complaint` from ((`backed`.`medical_records` `mr` join `backed`.`patients` `p` on(`mr`.`patient_id` = `p`.`id`)) left join `backed`.`triage_assessments` `ta` on(`mr`.`triage_assessment_id` = `ta`.`id`)) where `mr`.`workflow_status` in (\'triage_completed\',\'doctor_review\') and `mr`.`assigned_doctor_id` is not null order by `ta`.`priority` desc,`mr`.`record_date` desc
mariadb-version=100432
