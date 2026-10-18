TYPE=VIEW
query=select `pv`.`id` AS `id`,`pv`.`patient_id` AS `patient_id`,`p`.`patient_id` AS `patient_number`,concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`,`pv`.`blood_pressure_systolic` AS `blood_pressure_systolic`,`pv`.`blood_pressure_diastolic` AS `blood_pressure_diastolic`,concat(`pv`.`blood_pressure_systolic`,\'/\',`pv`.`blood_pressure_diastolic`) AS `blood_pressure`,`pv`.`heart_rate` AS `heart_rate`,`pv`.`temperature` AS `temperature`,`pv`.`oxygen_saturation` AS `oxygen_saturation`,`pv`.`weight` AS `weight`,`pv`.`height` AS `height`,`pv`.`bmi` AS `bmi`,`pv`.`recorded_at` AS `recorded_at`,concat(`u`.`first_name`,\' \',`u`.`last_name`) AS `recorded_by_name` from ((`hospital`.`patient_vitals` `pv` join `hospital`.`patients` `p` on(`pv`.`patient_id` = `p`.`id`)) left join `hospital`.`users` `u` on(`pv`.`recorded_by` = `u`.`id`)) order by `pv`.`recorded_at` desc
md5=525c4e22a65326be4d7eb940a572d409
updatable=0
algorithm=0
definer_user=root
definer_host=localhost
suid=1
with_check_option=0
timestamp=0001762028832207167
create-version=2
source=SELECT `pv`.`id` AS `id`, `pv`.`patient_id` AS `patient_id`, `p`.`patient_id` AS `patient_number`, concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`, `pv`.`blood_pressure_systolic` AS `blood_pressure_systolic`, `pv`.`blood_pressure_diastolic` AS `blood_pressure_diastolic`, concat(`pv`.`blood_pressure_systolic`,\'/\',`pv`.`blood_pressure_diastolic`) AS `blood_pressure`, `pv`.`heart_rate` AS `heart_rate`, `pv`.`temperature` AS `temperature`, `pv`.`oxygen_saturation` AS `oxygen_saturation`, `pv`.`weight` AS `weight`, `pv`.`height` AS `height`, `pv`.`bmi` AS `bmi`, `pv`.`recorded_at` AS `recorded_at`, concat(`u`.`first_name`,\' \',`u`.`last_name`) AS `recorded_by_name` FROM ((`patient_vitals` `pv` join `patients` `p` on(`pv`.`patient_id` = `p`.`id`)) left join `users` `u` on(`pv`.`recorded_by` = `u`.`id`)) ORDER BY `pv`.`recorded_at` DESC
client_cs_name=utf8mb4
connection_cl_name=utf8mb4_general_ci
view_body_utf8=select `pv`.`id` AS `id`,`pv`.`patient_id` AS `patient_id`,`p`.`patient_id` AS `patient_number`,concat(`p`.`first_name`,\' \',`p`.`last_name`) AS `patient_name`,`pv`.`blood_pressure_systolic` AS `blood_pressure_systolic`,`pv`.`blood_pressure_diastolic` AS `blood_pressure_diastolic`,concat(`pv`.`blood_pressure_systolic`,\'/\',`pv`.`blood_pressure_diastolic`) AS `blood_pressure`,`pv`.`heart_rate` AS `heart_rate`,`pv`.`temperature` AS `temperature`,`pv`.`oxygen_saturation` AS `oxygen_saturation`,`pv`.`weight` AS `weight`,`pv`.`height` AS `height`,`pv`.`bmi` AS `bmi`,`pv`.`recorded_at` AS `recorded_at`,concat(`u`.`first_name`,\' \',`u`.`last_name`) AS `recorded_by_name` from ((`hospital`.`patient_vitals` `pv` join `hospital`.`patients` `p` on(`pv`.`patient_id` = `p`.`id`)) left join `hospital`.`users` `u` on(`pv`.`recorded_by` = `u`.`id`)) order by `pv`.`recorded_at` desc
mariadb-version=100432
