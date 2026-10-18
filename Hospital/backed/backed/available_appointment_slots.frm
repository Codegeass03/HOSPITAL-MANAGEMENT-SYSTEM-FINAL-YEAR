TYPE=VIEW
query=select `asls`.`id` AS `slot_id`,`asls`.`doctor_id` AS `doctor_id`,concat(`u`.`first_name`,\' \',`u`.`last_name`) AS `doctor_name`,`u`.`role` AS `role`,`sp`.`department` AS `department`,`asls`.`day_of_week` AS `day_of_week`,case `asls`.`day_of_week` when 0 then \'Sunday\' when 1 then \'Monday\' when 2 then \'Tuesday\' when 3 then \'Wednesday\' when 4 then \'Thursday\' when 5 then \'Friday\' when 6 then \'Saturday\' end AS `day_name`,`asls`.`start_time` AS `start_time`,`asls`.`end_time` AS `end_time`,`asls`.`slot_duration` AS `slot_duration`,`asls`.`max_appointments` AS `max_appointments`,`asls`.`is_available` AS `is_available` from ((`backed`.`appointment_slots` `asls` join `backed`.`users` `u` on(`asls`.`doctor_id` = `u`.`id`)) left join `backed`.`staff_profiles` `sp` on(`u`.`id` = `sp`.`user_id`)) where `asls`.`is_available` = 1 and `u`.`status` = \'active\' order by `asls`.`doctor_id`,`asls`.`day_of_week`,`asls`.`start_time`
md5=cef904a762bb652698960b7d5c302c41
updatable=0
algorithm=0
definer_user=root
definer_host=localhost
suid=1
with_check_option=0
timestamp=0001762033703587913
create-version=2
source=SELECT `asls`.`id` AS `slot_id`, `asls`.`doctor_id` AS `doctor_id`, concat(`u`.`first_name`,\' \',`u`.`last_name`) AS `doctor_name`, `u`.`role` AS `role`, `sp`.`department` AS `department`, `asls`.`day_of_week` AS `day_of_week`, CASE `asls`.`day_of_week` WHEN 0 THEN \'Sunday\' WHEN 1 THEN \'Monday\' WHEN 2 THEN \'Tuesday\' WHEN 3 THEN \'Wednesday\' WHEN 4 THEN \'Thursday\' WHEN 5 THEN \'Friday\' WHEN 6 THEN \'Saturday\' END AS `day_name`, `asls`.`start_time` AS `start_time`, `asls`.`end_time` AS `end_time`, `asls`.`slot_duration` AS `slot_duration`, `asls`.`max_appointments` AS `max_appointments`, `asls`.`is_available` AS `is_available` FROM ((`appointment_slots` `asls` join `users` `u` on(`asls`.`doctor_id` = `u`.`id`)) left join `staff_profiles` `sp` on(`u`.`id` = `sp`.`user_id`)) WHERE `asls`.`is_available` = 1 AND `u`.`status` = \'active\' ORDER BY `asls`.`doctor_id` ASC, `asls`.`day_of_week` ASC, `asls`.`start_time` ASC
client_cs_name=utf8mb4
connection_cl_name=utf8mb4_general_ci
view_body_utf8=select `asls`.`id` AS `slot_id`,`asls`.`doctor_id` AS `doctor_id`,concat(`u`.`first_name`,\' \',`u`.`last_name`) AS `doctor_name`,`u`.`role` AS `role`,`sp`.`department` AS `department`,`asls`.`day_of_week` AS `day_of_week`,case `asls`.`day_of_week` when 0 then \'Sunday\' when 1 then \'Monday\' when 2 then \'Tuesday\' when 3 then \'Wednesday\' when 4 then \'Thursday\' when 5 then \'Friday\' when 6 then \'Saturday\' end AS `day_name`,`asls`.`start_time` AS `start_time`,`asls`.`end_time` AS `end_time`,`asls`.`slot_duration` AS `slot_duration`,`asls`.`max_appointments` AS `max_appointments`,`asls`.`is_available` AS `is_available` from ((`backed`.`appointment_slots` `asls` join `backed`.`users` `u` on(`asls`.`doctor_id` = `u`.`id`)) left join `backed`.`staff_profiles` `sp` on(`u`.`id` = `sp`.`user_id`)) where `asls`.`is_available` = 1 and `u`.`status` = \'active\' order by `asls`.`doctor_id`,`asls`.`day_of_week`,`asls`.`start_time`
mariadb-version=100432
