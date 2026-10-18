-- db_medicare_hms.sql
-- MediCare HMS - Phase 1 database schema
-- MySQL 8.0+ assumed. InnoDB, utf8mb4.

DROP DATABASE IF EXISTS hospital;
CREATE DATABASE hospital CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE hospital;

-- ---------- ROLES & PERMISSIONS ----------
CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(64) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- USERS (AUTHENTICATION) ----------
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50),
    department VARCHAR(100),
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(30),
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- If you want separate staff details (for HR)
CREATE TABLE staff_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    employee_number VARCHAR(50) UNIQUE,
    department VARCHAR(100),
    job_title VARCHAR(100),
    hire_date DATE,
    salary DECIMAL(12,2) DEFAULT 0.00,
    address TEXT,
    date_of_birth DATE,
    gender ENUM('female','male','other') DEFAULT 'female',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- PATIENTS ----------
-- Patients table structure
CREATE TABLE IF NOT EXISTS patients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id VARCHAR(20) UNIQUE NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    age INT UNSIGNED NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    height DECIMAL(5,2) COMMENT 'Height in cm',
    weight DECIMAL(5,2) COMMENT 'Weight in kg',
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'),
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    insurance_provider VARCHAR(100),
    insurance_id VARCHAR(50),
    primary_physician VARCHAR(100),
    medical_history TEXT,
    allergies TEXT,
    current_medications TEXT,
    status ENUM('Active', 'Inactive', 'Deceased') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Function to generate patient ID
DELIMITER $$
CREATE FUNCTION generate_patient_id() RETURNS VARCHAR(20)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE new_id VARCHAR(20);
    DECLARE sequence_num INT;
    
    SELECT COALESCE(MAX(CAST(SUBSTRING(patient_id, 3) AS UNSIGNED)), 0) + 1 
    INTO sequence_num 
    FROM patients 
    WHERE patient_id LIKE 'PT%';
    
    SET new_id = CONCAT('PT', LPAD(sequence_num, 6, '0'));
    RETURN new_id;
END$$
DELIMITER ;

-- ---------- INSURANCE ----------
CREATE TABLE insurance_providers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    contact_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- WARDS / ROOMS / BEDS ----------
CREATE TABLE wards (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ward_id INT UNSIGNED NOT NULL,
    room_number VARCHAR(30) NOT NULL,
    floor VARCHAR(20),
    type ENUM('general','private','icu','maternity','surgical') DEFAULT 'general',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_room_ward FOREIGN KEY (ward_id) REFERENCES wards (id) ON DELETE CASCADE,
    UNIQUE (ward_id, room_number)
) ENGINE=InnoDB;

CREATE TABLE beds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id INT UNSIGNED NOT NULL,
    bed_number VARCHAR(30) NOT NULL,
    status ENUM('available','occupied','maintenance','cleaning') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_bed_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE,
    UNIQUE (room_id, bed_number)
) ENGINE=InnoDB;

CREATE TABLE admissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    admitted_by INT UNSIGNED, -- user id
    admission_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    discharge_date DATETIME NULL,
    admission_reason TEXT,
    bed_id INT UNSIGNED NULL,
    status ENUM('admitted','discharged','transferred') DEFAULT 'admitted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_adm_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE,
    CONSTRAINT fk_adm_bed FOREIGN KEY (bed_id) REFERENCES beds (id) ON DELETE SET NULL,
    CONSTRAINT fk_adm_by FOREIGN KEY (admitted_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE bed_transfers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admission_id INT UNSIGNED NOT NULL,
    from_bed_id INT UNSIGNED NULL,
    to_bed_id INT UNSIGNED NULL,
    transferred_by INT UNSIGNED,
    transfer_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    reason TEXT,
    CONSTRAINT fk_bt_adm FOREIGN KEY (admission_id) REFERENCES admissions (id) ON DELETE CASCADE,
    CONSTRAINT fk_bt_from FOREIGN KEY (from_bed_id) REFERENCES beds (id) ON DELETE SET NULL,
    CONSTRAINT fk_bt_to FOREIGN KEY (to_bed_id) REFERENCES beds (id) ON DELETE SET NULL,
    CONSTRAINT fk_bt_by FOREIGN KEY (transferred_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- DOCTORS / SCHEDULES / LEAVES ----------
-- We'll store doctors as users with role_id = doctor, but keep a lightweight doctors table for specialty and schedule
CREATE TABLE doctor_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    specialty VARCHAR(150),
    qualifications VARCHAR(255),
    bio TEXT,
    consultation_fee DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_doc_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE doctor_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_user_id INT UNSIGNED NOT NULL,
    day_of_week TINYINT UNSIGNED NOT NULL, -- 0(Sun)-6(Sat)
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(150),
    CONSTRAINT fk_ds_doc FOREIGN KEY (doctor_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE staff_leaves (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    leave_type VARCHAR(100),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_leave_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- APPOINTMENTS ----------
CREATE TABLE appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    doctor_user_id INT UNSIGNED NOT NULL,
    scheduled_date DATETIME NOT NULL,
    status ENUM('scheduled','completed','cancelled','no_show') DEFAULT 'scheduled',
    reason TEXT,
    created_by INT UNSIGNED, -- user (receptionist) who set the appointment
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_app_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE,
    CONSTRAINT fk_app_doc FOREIGN KEY (doctor_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_app_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Appointment Scheduling Module Database Schema

-- Create appointments table (enhanced version)
DROP TABLE IF EXISTS appointments;
CREATE TABLE appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_number VARCHAR(50) UNIQUE,
    patient_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    duration INT DEFAULT 30 COMMENT 'Duration in minutes',
    appointment_type ENUM('consultation','followup','emergency','procedure','checkup') DEFAULT 'consultation',
    department VARCHAR(100),
    reason TEXT,
    notes TEXT,
    status ENUM('scheduled','confirmed','in_progress','completed','cancelled','no_show','rescheduled') DEFAULT 'scheduled',
    created_by INT UNSIGNED,
    cancelled_by INT UNSIGNED NULL,
    cancellation_reason TEXT NULL,
    reminder_sent BOOLEAN DEFAULT FALSE,
    reminder_sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_app_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_app_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_app_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_app_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_appointment_date (appointment_date, appointment_time),
    INDEX idx_patient_appointments (patient_id, appointment_date),
    INDEX idx_doctor_appointments (doctor_id, appointment_date),
    INDEX idx_appointment_status (status)
) ENGINE=InnoDB;

-- Create appointment slots table for managing doctor availability
CREATE TABLE appointment_slots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT UNSIGNED NOT NULL,
    day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 6=Saturday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_duration INT DEFAULT 30 COMMENT 'Minutes per slot',
    max_appointments INT DEFAULT 1,
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_slot_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_doctor_schedule (doctor_id, day_of_week)
) ENGINE=InnoDB;

-- Create appointment reminders log
CREATE TABLE appointment_reminders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    appointment_id INT UNSIGNED NOT NULL,
    reminder_type ENUM('sms','email','call') DEFAULT 'sms',
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('sent','failed','delivered') DEFAULT 'sent',
    message TEXT,
    CONSTRAINT fk_reminder_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Create waiting list for when no slots available
CREATE TABLE appointment_waiting_list (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    preferred_date DATE,
    preferred_time TIME,
    reason TEXT,
    priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
    status ENUM('waiting','scheduled','cancelled') DEFAULT 'waiting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_waiting_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_waiting_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Trigger to auto-generate appointment number
DELIMITER //
CREATE TRIGGER generate_appointment_number 
BEFORE INSERT ON appointments
FOR EACH ROW
BEGIN
    IF NEW.appointment_number IS NULL THEN
        SET NEW.appointment_number = CONCAT('APT-', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD(LAST_INSERT_ID() + 1, 5, '0'));
    END IF;
END//
DELIMITER ;

-- Insert sample appointment slots for doctors (8 AM - 5 PM, Monday to Friday)
INSERT INTO appointment_slots (doctor_id, day_of_week, start_time, end_time, slot_duration, max_appointments)
SELECT 
    u.id,
    wd.day_num,
    '08:00:00',
    '17:00:00',
    30,
    1
FROM users u
CROSS JOIN (
    SELECT 1 AS day_num UNION ALL
    SELECT 2 UNION ALL
    SELECT 3 UNION ALL
    SELECT 4 UNION ALL
    SELECT 5
) wd
WHERE u.role = 'Doctor' AND u.status = 'active'
ON DUPLICATE KEY UPDATE doctor_id = doctor_id;

-- View for available appointment slots
CREATE OR REPLACE VIEW available_appointment_slots AS
SELECT 
    asls.id AS slot_id,
    asls.doctor_id,
    CONCAT(u.first_name, ' ', u.last_name) AS doctor_name,
    u.role,
    sp.department,
    asls.day_of_week,
    CASE asls.day_of_week
        WHEN 0 THEN 'Sunday'
        WHEN 1 THEN 'Monday'
        WHEN 2 THEN 'Tuesday'
        WHEN 3 THEN 'Wednesday'
        WHEN 4 THEN 'Thursday'
        WHEN 5 THEN 'Friday'
        WHEN 6 THEN 'Saturday'
    END AS day_name,
    asls.start_time,
    asls.end_time,
    asls.slot_duration,
    asls.max_appointments,
    asls.is_available
FROM appointment_slots asls
JOIN users u ON asls.doctor_id = u.id
LEFT JOIN staff_profiles sp ON u.id = sp.user_id
WHERE asls.is_available = TRUE AND u.status = 'active'
ORDER BY asls.doctor_id, asls.day_of_week, asls.start_time;

-- View for upcoming appointments
CREATE OR REPLACE VIEW upcoming_appointments AS
SELECT 
    a.id,
    a.appointment_number,
    a.appointment_date,
    a.appointment_time,
    a.duration,
    a.appointment_type,
    a.status,
    CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
    p.patient_id,
    p.phone AS patient_phone,
    p.email AS patient_email,
    CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
    a.reason,
    a.notes
FROM appointments a
JOIN patients p ON a.patient_id = p.id
JOIN users d ON a.doctor_id = d.id
WHERE a.appointment_date >= CURDATE() 
  AND a.status IN ('scheduled', 'confirmed')
ORDER BY a.appointment_date, a.appointment_time;

-- ---------- ELECTRONIC MEDICAL RECORDS (EMR) ----------
CREATE TABLE patient_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    recorded_by INT UNSIGNED, -- doctor/nurse
    record_type ENUM('visit','note','diagnosis','procedure','allergy','immunization') DEFAULT 'note',
    title VARCHAR(255),
    description TEXT,
    icd10_code VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pr_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE,
    CONSTRAINT fk_pr_by FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- PRESCRIPTIONS ----------
CREATE TABLE prescriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    doctor_user_id INT UNSIGNED NOT NULL,
    date_issued DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    status ENUM('active','dispensed','cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pres_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE,
    CONSTRAINT fk_pres_doc FOREIGN KEY (doctor_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE prescription_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT UNSIGNED NOT NULL,
    medicine_id INT UNSIGNED NOT NULL,
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    duration VARCHAR(100),
    instructions TEXT,
    CONSTRAINT fk_pi_pres FOREIGN KEY (prescription_id) REFERENCES prescriptions (id) ON DELETE CASCADE,
    CONSTRAINT fk_pi_med FOREIGN KEY (medicine_id) REFERENCES medicines (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------- MEDICINES & INVENTORY ----------
CREATE TABLE suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(150),
    phone VARCHAR(50),
    email VARCHAR(150),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE medicines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NULL,
    brand_name VARCHAR(150) NOT NULL,
    generic_name VARCHAR(150),
    unit VARCHAR(50), -- e.g. tablet, ml
    packing_size VARCHAR(50),
    batch_number VARCHAR(100),
    expiry_date DATE,
    min_stock INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_med_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE SET NULL,
    INDEX idx_medicine_generic (generic_name),
    INDEX idx_medicine_brand (brand_name)
) ENGINE=InnoDB;

CREATE TABLE inventory_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT UNSIGNED NOT NULL,
    location VARCHAR(150), -- e.g. main pharmacy, store
    quantity INT DEFAULT 0,
    cost_price DECIMAL(12,2) DEFAULT 0.00,
    selling_price DECIMAL(12,2) DEFAULT 0.00,
    last_stocked_at TIMESTAMP NULL,
    CONSTRAINT fk_inv_med FOREIGN KEY (medicine_id) REFERENCES medicines (id) ON DELETE CASCADE,
    UNIQUE (medicine_id, location)
) ENGINE=InnoDB;

CREATE TABLE stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inventory_item_id INT UNSIGNED NOT NULL,
    movement_type ENUM('in','out','adjustment','sale','expiry') NOT NULL,
    quantity INT NOT NULL,
    reference VARCHAR(150), -- e.g., PO #, invoice, prescription id
    performed_by INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sm_inv FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE CASCADE,
    CONSTRAINT fk_sm_by FOREIGN KEY (performed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT UNSIGNED NOT NULL,
    created_by INT UNSIGNED,
    order_date DATE DEFAULT CURRENT_DATE,
    expected_date DATE,
    status ENUM('pending','received','cancelled') DEFAULT 'pending',
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers (id) ON DELETE RESTRICT,
    CONSTRAINT fk_po_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE purchase_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id INT UNSIGNED NOT NULL,
    medicine_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) DEFAULT 0.00,
    CONSTRAINT fk_poi_po FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_poi_med FOREIGN KEY (medicine_id) REFERENCES medicines (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------- PHARMACY SALES ----------
CREATE TABLE pharmacy_sales (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NULL,
    cashier_id INT UNSIGNED NULL,
    sale_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    payment_method ENUM('cash','card','insurance') DEFAULT 'cash',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ps_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE SET NULL,
    CONSTRAINT fk_ps_cashier FOREIGN KEY (cashier_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE pharmacy_sale_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pharmacy_sale_id INT UNSIGNED NOT NULL,
    inventory_item_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) DEFAULT 0.00,
    CONSTRAINT fk_psi_sale FOREIGN KEY (pharmacy_sale_id) REFERENCES pharmacy_sales (id) ON DELETE CASCADE,
    CONSTRAINT fk_psi_inv FOREIGN KEY (inventory_item_id) REFERENCES inventory_items (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------- LABS ----------
CREATE TABLE lab_tests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE lab_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    ordered_by INT UNSIGNED, -- doctor user id
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','in_progress','completed','cancelled') DEFAULT 'pending',
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    CONSTRAINT fk_lo_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE CASCADE,
    CONSTRAINT fk_lo_by FOREIGN KEY (ordered_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE lab_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lab_order_id INT UNSIGNED NOT NULL,
    lab_test_id INT UNSIGNED NOT NULL,
    result_text TEXT,
    result_file VARCHAR(255),
    status ENUM('pending','done') DEFAULT 'pending',
    CONSTRAINT fk_loi_order FOREIGN KEY (lab_order_id) REFERENCES lab_orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_loi_test FOREIGN KEY (lab_test_id) REFERENCES lab_tests (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------- FILES / IMAGING ----------
CREATE TABLE files (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NULL,
    uploaded_by INT UNSIGNED NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100),
    description VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_files_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE SET NULL,
    CONSTRAINT fk_files_by FOREIGN KEY (uploaded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- BILLING / INVOICING / PAYMENTS ----------
CREATE TABLE invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(100) NOT NULL UNIQUE,
    patient_id INT UNSIGNED NULL,
    admission_id INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    invoice_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    due_date DATE NULL,
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    status ENUM('draft','issued','paid','partially_paid','cancelled') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inv_patient FOREIGN KEY (patient_id) REFERENCES patients (id) ON DELETE SET NULL,
    CONSTRAINT fk_inv_adm FOREIGN KEY (admission_id) REFERENCES admissions (id) ON DELETE SET NULL,
    CONSTRAINT fk_inv_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE invoice_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(12,2) DEFAULT 0.00,
    total_price DECIMAL(12,2) AS (quantity * unit_price) STORED,
    CONSTRAINT fk_ii_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    payment_method ENUM('cash','card','insurance','online') DEFAULT 'cash',
    received_by INT UNSIGNED NULL,
    note TEXT,
    CONSTRAINT fk_pay_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE SET NULL,
    CONSTRAINT fk_pay_by FOREIGN KEY (received_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- INSURANCE CLAIMS ----------
CREATE TABLE insurance_claims (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    insurance_provider_id INT UNSIGNED NOT NULL,
    claim_reference VARCHAR(150),
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','approved','rejected','paid') DEFAULT 'pending',
    amount DECIMAL(12,2) DEFAULT 0.00,
    CONSTRAINT fk_ic_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE,
    CONSTRAINT fk_ic_provider FOREIGN KEY (insurance_provider_id) REFERENCES insurance_providers (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------- FINANCE (BASIC LEDGER) ----------
CREATE TABLE gl_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    type ENUM('asset','liability','equity','income','expense') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE journal_entries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE journal_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT UNSIGNED NOT NULL,
    gl_account_id INT UNSIGNED NOT NULL,
    debit DECIMAL(12,2) DEFAULT 0.00,
    credit DECIMAL(12,2) DEFAULT 0.00,
    CONSTRAINT fk_jl_entry FOREIGN KEY (journal_entry_id) REFERENCES journal_entries (id) ON DELETE CASCADE,
    CONSTRAINT fk_jl_gl FOREIGN KEY (gl_account_id) REFERENCES gl_accounts (id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------- EXPENSES ----------
CREATE TABLE expenses (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(150),
    expense_date DATE DEFAULT CURRENT_DATE,
    amount DECIMAL(12,2) NOT NULL,
    category VARCHAR(150),
    notes TEXT,
    created_by INT UNSIGNED,
    CONSTRAINT fk_exp_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------- HR - ATTENDANCE / PAYROLL (BASIC) ----------
CREATE TABLE attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    time_in TIME,
    time_out TIME,
    status ENUM('present','absent','on_leave') DEFAULT 'present',
    CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    UNIQUE (user_id, date)
) ENGINE=InnoDB;

CREATE TABLE payrolls (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    period_start DATE,
    period_end DATE,
    gross_pay DECIMAL(12,2) DEFAULT 0.00,
    deductions DECIMAL(12,2) DEFAULT 0.00,
    net_pay DECIMAL(12,2) GENERATED ALWAYS AS (gross_pay - deductions) STORED,
    paid_at DATETIME NULL,
    CONSTRAINT fk_payroll_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- REPORTING / BATCH TABLES ----------
-- (No tables yet, reports will be generated via queries from above tables.)

-- ---------- UTILITIES ----------
CREATE TABLE blood_donors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(30),
    email VARCHAR(150),
    blood_group VARCHAR(5),
    city VARCHAR(100),
    last_donation_date DATE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE consultants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    specialty VARCHAR(150),
    contact_info TEXT,
    available BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- SYSTEM LOGS / SMS SIMULATION ----------
CREATE TABLE system_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level VARCHAR(20),
    message TEXT,
    meta JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE sms_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    to_phone VARCHAR(50),
    message TEXT,
    status ENUM('queued','sent','failed') DEFAULT 'queued',
    sent_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- INDEXES / SAMPLE DATA ----------
-- Add a few default roles (you can insert more later)
INSERT INTO roles (name, description) VALUES
('Super Admin','Full system access'),
('Admin','Administrative user'),
('Doctor','Medical practitioner'),
('Nurse','Nursing staff'),
('Pharmacist','Pharmacy staff'),
('Lab Technician','Laboratory staff'),
('Accountant','Finance staff'),
('Receptionist','Front desk'),
('Patient','Patient account');

-- Add minimal permissions (expand later)
INSERT INTO permissions (code, description) VALUES
('manage_users','Create/update/delete users and roles'),
('manage_patients','Create/update patient records'),
('manage_appointments','Manage appointments'),
('manage_inventory','Manage inventory and pharmacy'),
('manage_billing','Generate invoices and payments'),
('view_reports','View reporting dashboard');

-- Link Super Admin to all permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id FROM roles r CROSS JOIN permissions p WHERE r.name = 'Super Admin';

-- create sample gl accounts
INSERT INTO gl_accounts (code, name, type) VALUES
('1000','Cash','asset'),
('2000','Accounts Payable','liability'),
('3000','Equity','equity'),
('4000','Service Revenue','income'),
('5000','Operating Expenses','expense');


INSERT INTO users (role_id, username, email, password_hash, first_name, last_name, phone, status, last_login)
VALUES
-- Super Admin
(1, 'superadmin', 'superadmin@medicare.com', 
'$2y$10$QJjMkjY8QbKZbTnKDC9/NeWqH3z.K3z5M8B6T3y7USX0R3nUgwxHi', 
'Samuel', 'Maina', '0712345678', 'active', NULL),

-- Admin
(2, 'admin1', 'admin1@medicare.com', 
'$2y$10$QJjMkjY8QbKZbTnKDC9/NeWqH3z.K3z5M8B6T3y7USX0R3nUgwxHi',
'Grace', 'Wanjiku', '0712345679', 'active', NULL),

-- Doctor
(3, 'drjohn', 'drjohn@medicare.com', 
'$2y$10$QJjMkjY8QbKZbTnKDC9/NeWqH3z.K3z5M8B6T3y7USX0R3nUgwxHi',
'John', 'Doe', '0712345680', 'active', NULL),

-- Nurse
(4, 'nursejane', 'nursejane@medicare.com',
'$2y$10$QJjMkjY8QbKZbTnKDC9/NeWqH3z.K3z5M8B6T3y7USX0R3nUgwxHi',
'Jane', 'Njeri', '0712345681', 'active', NULL),

-- Pharmacist
(5, 'pharma1', 'pharma1@medicare.com',
'$2y$10$QJjMkjY8QbKZbTnKDC9/NeWqH3z.K3z5M8B6T3y7USX0R3nUgwxHi',
'Brian', 'Omondi', '0712345682', 'active', NULL),

-- Lab Technician
(6, 'labtech', 'labtech@medicare.com',
'$2y$10$QJjMkjY8QbKZbTnKDC9/NeWqH3z.K3z5M8B6T3y7USX0R3nUgwxHi',
'Peter', 'Otieno', '0712345683', 'active', NULL),

-- Accountant
(7, 'acct1', 'acct1@medicare.com',
'$2y$10$QJjMkjY8QbKZbTnKDC9/NeWqH3z.K3z5M8B6T3y7USX0R3nUgwxHi',
'Mary', 'Kariuki', '0712345684', 'active', NULL),

-- Receptionist
(8, 'recept1', 'recept1@medicare.com',
'$2y$10$QJjMkjY8QbKZbTnKDC9/NeWqH3z.K3z5M8B6T3y7USX0R3nUgwxHi',
'Linda', 'Wairimu', '0712345685', 'active', NULL),

-- Patient
(9, 'patient1', 'patient1@medicare.com',
'$2y$10$QJjMkjY8QbKZbTnKDC9/NeWqH3z.K3z5M8B6T3y7USX0R3nUgwxHi',
'Kevin', 'Mutiso', '0712345686', 'active', NULL);

-- done
-- EHR (Electronic Health Records) System Database Schema

-- Medical Records Main Table
CREATE TABLE IF NOT EXISTS medical_records (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_number VARCHAR(50) UNIQUE,
    patient_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    record_type ENUM('consultation','lab_result','prescription','diagnosis','procedure','vaccination','imaging','note') NOT NULL,
    record_date DATE NOT NULL,
    department VARCHAR(100),
    diagnosis TEXT,
    treatment TEXT,
    notes TEXT,
    status ENUM('draft','completed','reviewed','archived') DEFAULT 'completed',
    priority ENUM('normal','high','critical') DEFAULT 'normal',
    created_by INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_mr_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_mr_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_mr_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_record_date (record_date),
    INDEX idx_patient_records (patient_id, record_date),
    INDEX idx_record_type (record_type),
    INDEX idx_record_status (status)
) ENGINE=InnoDB;

-- Patient Vitals Table
CREATE TABLE IF NOT EXISTS patient_vitals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    record_id INT UNSIGNED NULL,
    blood_pressure_systolic INT,
    blood_pressure_diastolic INT,
    heart_rate INT COMMENT 'BPM',
    temperature DECIMAL(4,1) COMMENT 'Fahrenheit',
    oxygen_saturation INT COMMENT 'Percentage',
    respiratory_rate INT COMMENT 'Breaths per minute',
    weight DECIMAL(5,2) COMMENT 'Kilograms',
    height DECIMAL(5,2) COMMENT 'Centimeters',
    bmi DECIMAL(4,2),
    recorded_by INT UNSIGNED,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    CONSTRAINT fk_vitals_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_vitals_record FOREIGN KEY (record_id) REFERENCES medical_records(id) ON DELETE SET NULL,
    CONSTRAINT fk_vitals_recorded_by FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_patient_vitals (patient_id, recorded_at)
) ENGINE=InnoDB;

-- Medications/Prescriptions Details
CREATE TABLE IF NOT EXISTS patient_medications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id INT UNSIGNED NOT NULL,
    record_id INT UNSIGNED NULL,
    medication_name VARCHAR(255) NOT NULL,
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    route ENUM('oral','injection','topical','intravenous','inhalation','other') DEFAULT 'oral',
    duration VARCHAR(100),
    start_date DATE,
    end_date DATE NULL,
    prescribing_doctor INT UNSIGNED,
    instructions TEXT,
    status ENUM('active','completed','discontinued','on_hold') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_med_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_med_record FOREIGN KEY (record_id) REFERENCES medical_records(id) ON DELETE SET NULL,
    CONSTRAINT fk_med_doctor FOREIGN KEY (prescribing_doctor) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_patient_medications (patient_id, status)
) ENGINE=InnoDB;

-- Lab Test Results
CREATE TABLE IF NOT EXISTS lab_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_id INT UNSIGNED NOT NULL,
    patient_id INT UNSIGNED NOT NULL,
    test_name VARCHAR(255) NOT NULL,
    test_category VARCHAR(100),
    result_value VARCHAR(255),
    unit VARCHAR(50),
    reference_range VARCHAR(100),
    status ENUM('normal','abnormal','critical') DEFAULT 'normal',
    performed_date DATE,
    result_date DATE,
    technician_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lab_record FOREIGN KEY (record_id) REFERENCES medical_records(id) ON DELETE CASCADE,
    CONSTRAINT fk_lab_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_patient_labs (patient_id, performed_date)
) ENGINE=InnoDB;

-- Medical Documents/Attachments
CREATE TABLE IF NOT EXISTS medical_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_id INT UNSIGNED NOT NULL,
    patient_id INT UNSIGNED NOT NULL,
    document_type ENUM('lab_report','imaging','prescription','consent_form','discharge_summary','other') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT COMMENT 'Size in bytes',
    mime_type VARCHAR(100),
    uploaded_by INT UNSIGNED,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_doc_record FOREIGN KEY (record_id) REFERENCES medical_records(id) ON DELETE CASCADE,
    CONSTRAINT fk_doc_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_doc_uploaded_by FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Medical Record Access Log (Audit Trail)
CREATE TABLE IF NOT EXISTS medical_record_access_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    record_id INT UNSIGNED NOT NULL,
    patient_id INT UNSIGNED NOT NULL,
    accessed_by INT UNSIGNED,
    access_type ENUM('view','edit','create','delete','download') NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_access_record FOREIGN KEY (record_id) REFERENCES medical_records(id) ON DELETE CASCADE,
    CONSTRAINT fk_access_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_access_user FOREIGN KEY (accessed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_access_log (patient_id, accessed_at),
    INDEX idx_user_access (accessed_by, accessed_at)
) ENGINE=InnoDB;

-- Trigger to generate record number
DELIMITER //
CREATE TRIGGER generate_record_number 
BEFORE INSERT ON medical_records
FOR EACH ROW
BEGIN
    IF NEW.record_number IS NULL THEN
        SET NEW.record_number = CONCAT('EHR', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD(LAST_INSERT_ID() + 1, 5, '0'));
    END IF;
END//
DELIMITER ;

-- View for complete patient medical history
CREATE OR REPLACE VIEW patient_medical_history AS
SELECT 
    mr.id,
    mr.record_number,
    mr.record_type,
    mr.record_date,
    mr.diagnosis,
    mr.treatment,
    mr.status,
    mr.priority,
    p.patient_id,
    CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
    p.age,
    CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
    mr.department,
    mr.notes,
    mr.created_at
FROM medical_records mr
JOIN patients p ON mr.patient_id = p.id
JOIN users d ON mr.doctor_id = d.id
ORDER BY mr.record_date DESC, mr.created_at DESC;

-- View for recent patient vitals
CREATE OR REPLACE VIEW recent_patient_vitals AS
SELECT 
    pv.id,
    pv.patient_id,
    p.patient_id AS patient_number,
    CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
    pv.blood_pressure_systolic,
    pv.blood_pressure_diastolic,
    CONCAT(pv.blood_pressure_systolic, '/', pv.blood_pressure_diastolic) AS blood_pressure,
    pv.heart_rate,
    pv.temperature,
    pv.oxygen_saturation,
    pv.weight,
    pv.height,
    pv.bmi,
    pv.recorded_at,
    CONCAT(u.first_name, ' ', u.last_name) AS recorded_by_name
FROM patient_vitals pv
JOIN patients p ON pv.patient_id = p.id
LEFT JOIN users u ON pv.recorded_by = u.id
ORDER BY pv.recorded_at DESC;

-- Insert sample data
INSERT INTO medical_records (patient_id, doctor_id, record_type, record_date, department, diagnosis, treatment, status, priority, created_by)
VALUES
(9, 3, 'consultation', '2024-01-15', 'Cardiology', 'Hypertension', 'Patient shows elevated blood pressure. Prescribed medication and recommended dietary changes.', 'completed', 'normal', 1);

-- Insert sample vitals
INSERT INTO patient_vitals (patient_id, blood_pressure_systolic, blood_pressure_diastolic, heart_rate, temperature, oxygen_saturation, recorded_by)
VALUES
(9, 130, 85, 72, 98.6, 98, 1);

-- Insert sample medication
INSERT INTO patient_medications (patient_id, medication_name, dosage, frequency, route, duration, start_date, prescribing_doctor, instructions, status)
VALUES
(9, 'Lisinopril', '10mg', 'Once daily', 'oral', '30 days', CURDATE(), 3, 'Take in the morning with food', 'active');



-- PHARMACY & INVENTORY MANAGEMENT SYSTEM DATABASE SCHEMA

-- ============================================
-- MEDICINES & PHARMACY TABLES
-- ============================================

-- Medicine Master Table
CREATE TABLE IF NOT EXISTS medicines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_code VARCHAR(50) UNIQUE,
    brand_name VARCHAR(255) NOT NULL,
    generic_name VARCHAR(255),
    category ENUM('medication','consumable','equipment','reagent') DEFAULT 'medication',
    dosage_form VARCHAR(100) COMMENT 'Tablet, Capsule, Syrup, Injection, etc.',
    strength VARCHAR(100),
    manufacturer VARCHAR(255),
    unit_of_measure VARCHAR(50) COMMENT 'tablets, ml, pieces, etc.',
    description TEXT,
    requires_prescription BOOLEAN DEFAULT TRUE,
    storage_conditions VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_medicine_name (brand_name, generic_name),
    INDEX idx_category (category)
) ENGINE=InnoDB;

-- Medicine Stock/Inventory
CREATE TABLE IF NOT EXISTS medicine_stock (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT UNSIGNED NOT NULL,
    batch_number VARCHAR(100) NOT NULL,
    quantity_available INT NOT NULL DEFAULT 0,
    quantity_allocated INT DEFAULT 0 COMMENT 'Reserved for prescriptions',
    unit_cost DECIMAL(10,2),
    selling_price DECIMAL(10,2),
    manufacturing_date DATE,
    expiry_date DATE NOT NULL,
    location VARCHAR(100) COMMENT 'Pharmacy location/shelf',
    supplier_id INT UNSIGNED,
    reorder_level INT DEFAULT 10,
    status ENUM('in-stock','low-stock','out-of-stock','expired') DEFAULT 'in-stock',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    UNIQUE KEY unique_batch (medicine_id, batch_number),
    INDEX idx_expiry (expiry_date),
    INDEX idx_stock_status (status)
) ENGINE=InnoDB;

-- Prescriptions (Enhanced)
CREATE TABLE IF NOT EXISTS prescriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prescription_number VARCHAR(50) UNIQUE,
    patient_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    prescription_date DATE NOT NULL,
    valid_until DATE,
    diagnosis VARCHAR(500),
    notes TEXT,
    status ENUM('pending','dispensed','partial','cancelled','expired') DEFAULT 'pending',
    dispensed_by INT UNSIGNED NULL,
    dispensed_at DATETIME NULL,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_presc_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    CONSTRAINT fk_presc_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_presc_dispensed_by FOREIGN KEY (dispensed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_prescription_status (status),
    INDEX idx_prescription_date (prescription_date)
) ENGINE=InnoDB;

-- Prescription Items
CREATE TABLE IF NOT EXISTS prescription_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT UNSIGNED NOT NULL,
    medicine_id INT UNSIGNED NOT NULL,
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    duration VARCHAR(100),
    quantity_prescribed INT NOT NULL,
    quantity_dispensed INT DEFAULT 0,
    instructions TEXT,
    unit_price DECIMAL(10,2),
    total_price DECIMAL(10,2),
    status ENUM('pending','dispensed','partial') DEFAULT 'pending',
    CONSTRAINT fk_presc_item_prescription FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE,
    CONSTRAINT fk_presc_item_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Stock Movements/Transactions
CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT UNSIGNED NOT NULL,
    stock_id INT UNSIGNED NOT NULL,
    movement_type ENUM('in','out','adjustment','return','expired','damaged') NOT NULL,
    quantity INT NOT NULL,
    batch_number VARCHAR(100),
    reference_type ENUM('purchase','sale','prescription','adjustment','return') NOT NULL,
    reference_id INT UNSIGNED,
    unit_cost DECIMAL(10,2),
    total_cost DECIMAL(10,2),
    performed_by INT UNSIGNED,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_movement_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    CONSTRAINT fk_movement_stock FOREIGN KEY (stock_id) REFERENCES medicine_stock(id) ON DELETE CASCADE,
    CONSTRAINT fk_movement_user FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_movement_date (created_at),
    INDEX idx_movement_type (movement_type)
) ENGINE=InnoDB;

-- ============================================
-- INVENTORY MANAGEMENT TABLES
-- ============================================

-- Suppliers/Vendors
CREATE TABLE IF NOT EXISTS suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_code VARCHAR(50) UNIQUE,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    email VARCHAR(150),
    phone VARCHAR(30),
    address TEXT,
    city VARCHAR(100),
    country VARCHAR(100),
    tax_id VARCHAR(100),
    payment_terms VARCHAR(255),
    status ENUM('active','inactive','blacklisted') DEFAULT 'active',
    rating DECIMAL(3,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_supplier_name (name),
    INDEX idx_supplier_status (status)
) ENGINE=InnoDB;

-- Purchase Orders
CREATE TABLE IF NOT EXISTS purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_number VARCHAR(50) UNIQUE,
    supplier_id INT UNSIGNED NOT NULL,
    order_date DATE NOT NULL,
    expected_delivery_date DATE,
    actual_delivery_date DATE NULL,
    payment_terms VARCHAR(255),
    delivery_address TEXT,
    subtotal DECIMAL(12,2) DEFAULT 0.00,
    tax_amount DECIMAL(12,2) DEFAULT 0.00,
    shipping_cost DECIMAL(12,2) DEFAULT 0.00,
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    status ENUM('draft','submitted','approved','rejected','ordered','partially_received','received','cancelled') DEFAULT 'draft',
    created_by INT UNSIGNED,
    approved_by INT UNSIGNED NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_po_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_po_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_po_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_po_status (status),
    INDEX idx_po_date (order_date)
) ENGINE=InnoDB;

-- Purchase Order Items
CREATE TABLE IF NOT EXISTS purchase_order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    po_id INT UNSIGNED NOT NULL,
    medicine_id INT UNSIGNED NOT NULL,
    quantity_ordered INT NOT NULL,
    quantity_received INT DEFAULT 0,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2),
    tax_rate DECIMAL(5,2) DEFAULT 0.00,
    discount DECIMAL(10,2) DEFAULT 0.00,
    notes TEXT,
    CONSTRAINT fk_poi_po FOREIGN KEY (po_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_poi_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Reorder Requests
CREATE TABLE IF NOT EXISTS reorder_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT UNSIGNED NOT NULL,
    current_stock INT NOT NULL,
    reorder_level INT NOT NULL,
    quantity_requested INT NOT NULL,
    priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
    status ENUM('pending','approved','ordered','rejected') DEFAULT 'pending',
    requested_by INT UNSIGNED,
    approved_by INT UNSIGNED NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reorder_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    CONSTRAINT fk_reorder_requested_by FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_reorder_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_reorder_status (status)
) ENGINE=InnoDB;

-- Quotations
CREATE TABLE IF NOT EXISTS quotations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quotation_number VARCHAR(50) UNIQUE,
    supplier_id INT UNSIGNED NOT NULL,
    quotation_date DATE NOT NULL,
    valid_until DATE,
    total_amount DECIMAL(12,2),
    status ENUM('received','under_review','accepted','rejected','expired') DEFAULT 'received',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_quot_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Low Stock Alerts
CREATE TABLE IF NOT EXISTS stock_alerts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT UNSIGNED NOT NULL,
    alert_type ENUM('low_stock','out_of_stock','expiring_soon','expired') NOT NULL,
    current_quantity INT,
    reorder_level INT,
    expiry_date DATE NULL,
    status ENUM('active','acknowledged','resolved') DEFAULT 'active',
    acknowledged_by INT UNSIGNED NULL,
    acknowledged_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_alert_medicine FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    CONSTRAINT fk_alert_acknowledged_by FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_alert_status (status),
    INDEX idx_alert_type (alert_type)
) ENGINE=InnoDB;

-- Triggers for auto-generating codes
DELIMITER //

CREATE TRIGGER generate_medicine_code
BEFORE INSERT ON medicines
FOR EACH ROW
BEGIN
    IF NEW.medicine_code IS NULL THEN
        SET NEW.medicine_code = CONCAT('MED', LPAD(LAST_INSERT_ID() + 1, 5, '0'));
    END IF;
END//

CREATE TRIGGER generate_prescription_number
BEFORE INSERT ON prescriptions
FOR EACH ROW
BEGIN
    IF NEW.prescription_number IS NULL THEN
        SET NEW.prescription_number = CONCAT('P-', DATE_FORMAT(NOW(), '%Y%m'), '-RX-', LPAD(LAST_INSERT_ID() + 1, 4, '0'));
    END IF;
END//

CREATE TRIGGER generate_po_number
BEFORE INSERT ON purchase_orders
FOR EACH ROW
BEGIN
    IF NEW.po_number IS NULL THEN
        SET NEW.po_number = CONCAT('PO-', DATE_FORMAT(NOW(), '%Y%m'), '-', LPAD(LAST_INSERT_ID() + 1, 4, '0'));
    END IF;
END//

-- Trigger to update stock status based on quantity
CREATE TRIGGER update_stock_status
BEFORE UPDATE ON medicine_stock
FOR EACH ROW
BEGIN
    IF NEW.quantity_available <= 0 THEN
        SET NEW.status = 'out-of-stock';
    ELSEIF NEW.quantity_available <= NEW.reorder_level THEN
        SET NEW.status = 'low-stock';
    ELSEIF NEW.expiry_date < CURDATE() THEN
        SET NEW.status = 'expired';
    ELSE
        SET NEW.status = 'in-stock';
    END IF;
END//

DELIMITER ;

-- Sample Data
INSERT INTO medicines (brand_name, generic_name, category, dosage_form, strength, manufacturer, unit_of_measure, requires_prescription)
VALUES 
('Acetaminophen 500mg', 'Paracetamol', 'medication', 'Tablet', '500mg', 'PharmaCorp', 'tablets', FALSE),
('Ibuprofen 200mg', 'Ibuprofen', 'medication', 'Tablet', '200mg', 'MediLabs', 'tablets', FALSE),
('Surgical Gloves (Medium)', 'Latex Gloves', 'consumable', 'N/A', 'Medium', 'MedSupply Inc', 'boxes', FALSE),
('Disposable Syringes 5ml', '5ml Syringe', 'consumable', 'N/A', '5ml', 'SafeMed', 'pieces', FALSE);

INSERT INTO suppliers (name, contact_person, email, phone, address, status)
VALUES
('MediSupply Inc', 'John Doe', 'john@medisupply.com', '+254712345678', '123 Medical Plaza, Nairobi', 'active'),
('PharmaCorp Ltd', 'Jane Smith', 'jane@pharmacorp.com', '+254723456789', '456 Health Street, Mombasa', 'active');


-- ================================================
-- BED AND WARD MANAGEMENT MODULE - DATABASE SCHEMA
-- ================================================

-- 1. Wards Table
CREATE TABLE IF NOT EXISTS wards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ward_number VARCHAR(50) UNIQUE NOT NULL,
    ward_name VARCHAR(100) NOT NULL,
    ward_type ENUM('General', 'ICU', 'NICU', 'Pediatric', 'Maternity', 'Surgical', 'Emergency', 'Private', 'Semi-Private') NOT NULL,
    floor_number INT NOT NULL,
    department VARCHAR(100),
    total_beds INT DEFAULT 0,
    occupied_beds INT DEFAULT 0,
    available_beds INT GENERATED ALWAYS AS (total_beds - occupied_beds) STORED,
    capacity INT NOT NULL,
    description TEXT,
    facilities TEXT COMMENT 'JSON or comma-separated list of facilities',
    status ENUM('active', 'inactive', 'maintenance', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Beds Table
CREATE TABLE IF NOT EXISTS beds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bed_number VARCHAR(50) NOT NULL,
    ward_id INT NOT NULL,
    bed_type ENUM('Standard', 'ICU', 'Ventilator', 'Oxygen Support', 'Isolation', 'Electric', 'Manual') NOT NULL,
    bed_status ENUM('available', 'occupied', 'reserved', 'maintenance', 'cleaning') DEFAULT 'available',
    price_per_day DECIMAL(10, 2) DEFAULT 0.00,
    description TEXT,
    features TEXT COMMENT 'JSON or comma-separated list of features',
    last_maintenance_date DATE,
    next_maintenance_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_bed_ward (bed_number, ward_id),
    FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE CASCADE,
    INDEX idx_bed_status (bed_status),
    INDEX idx_ward_id (ward_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Bed Assignments/Allocations Table
CREATE TABLE IF NOT EXISTS bed_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_number VARCHAR(50) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    bed_id INT NOT NULL,
    ward_id INT NOT NULL,
    admission_id INT,
    assigned_by INT COMMENT 'User ID who assigned the bed',
    admission_date DATETIME NOT NULL,
    expected_discharge_date DATE,
    actual_discharge_date DATETIME,
    discharge_reason TEXT,
    admission_type ENUM('emergency', 'planned', 'transfer') DEFAULT 'planned',
    patient_condition ENUM('critical', 'serious', 'stable', 'improving') DEFAULT 'stable',
    special_requirements TEXT,
    total_cost DECIMAL(10, 2) DEFAULT 0.00,
    payment_status ENUM('unpaid', 'partial', 'paid') DEFAULT 'unpaid',
    status ENUM('active', 'discharged', 'transferred', 'cancelled') DEFAULT 'active',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (bed_id) REFERENCES beds(id) ON DELETE RESTRICT,
    FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE RESTRICT,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_patient_id (patient_id),
    INDEX idx_bed_id (bed_id),
    INDEX idx_status (status),
    INDEX idx_admission_date (admission_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Bed Transfer History Table
CREATE TABLE IF NOT EXISTS bed_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    patient_id INT NOT NULL,
    from_bed_id INT NOT NULL,
    from_ward_id INT NOT NULL,
    to_bed_id INT NOT NULL,
    to_ward_id INT NOT NULL,
    transfer_date DATETIME NOT NULL,
    transfer_reason TEXT NOT NULL,
    transferred_by INT,
    approved_by INT,
    patient_condition_before VARCHAR(100),
    patient_condition_after VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES bed_assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (from_bed_id) REFERENCES beds(id) ON DELETE RESTRICT,
    FOREIGN KEY (from_ward_id) REFERENCES wards(id) ON DELETE RESTRICT,
    FOREIGN KEY (to_bed_id) REFERENCES beds(id) ON DELETE RESTRICT,
    FOREIGN KEY (to_ward_id) REFERENCES wards(id) ON DELETE RESTRICT,
    FOREIGN KEY (transferred_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_assignment_id (assignment_id),
    INDEX idx_patient_id (patient_id),
    INDEX idx_transfer_date (transfer_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Bed Maintenance Records Table
CREATE TABLE IF NOT EXISTS bed_maintenance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bed_id INT NOT NULL,
    maintenance_type ENUM('routine', 'repair', 'cleaning', 'inspection', 'emergency') NOT NULL,
    maintenance_date DATETIME NOT NULL,
    completed_date DATETIME,
    description TEXT NOT NULL,
    cost DECIMAL(10, 2) DEFAULT 0.00,
    performed_by VARCHAR(255),
    status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bed_id) REFERENCES beds(id) ON DELETE CASCADE,
    INDEX idx_bed_id (bed_id),
    INDEX idx_maintenance_date (maintenance_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Ward Staff Assignment Table (Optional - for tracking which staff work in which wards)
CREATE TABLE IF NOT EXISTS ward_staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ward_id INT NOT NULL,
    user_id INT NOT NULL,
    shift ENUM('morning', 'afternoon', 'night', 'rotating') DEFAULT 'morning',
    assignment_date DATE NOT NULL,
    end_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_ward_staff (ward_id, user_id, assignment_date),
    INDEX idx_ward_id (ward_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================
-- TRIGGERS TO MAINTAIN BED COUNTS
-- ================================================

-- Trigger to update ward occupied_beds count when bed assignment is created
DELIMITER //
CREATE TRIGGER after_bed_assignment_insert
AFTER INSERT ON bed_assignments
FOR EACH ROW
BEGIN
    IF NEW.status = 'active' THEN
        UPDATE wards SET occupied_beds = occupied_beds + 1 WHERE id = NEW.ward_id;
        UPDATE beds SET bed_status = 'occupied' WHERE id = NEW.bed_id;
    END IF;
END//

-- Trigger to update ward occupied_beds count when bed assignment is updated
CREATE TRIGGER after_bed_assignment_update
AFTER UPDATE ON bed_assignments
FOR EACH ROW
BEGIN
    -- If status changed from active to discharged/transferred
    IF OLD.status = 'active' AND NEW.status IN ('discharged', 'transferred', 'cancelled') THEN
        UPDATE wards SET occupied_beds = occupied_beds - 1 WHERE id = OLD.ward_id;
        UPDATE beds SET bed_status = 'available' WHERE id = OLD.bed_id;
    END IF;
    
    -- If status changed from discharged/transferred to active
    IF OLD.status IN ('discharged', 'transferred', 'cancelled') AND NEW.status = 'active' THEN
        UPDATE wards SET occupied_beds = occupied_beds + 1 WHERE id = NEW.ward_id;
        UPDATE beds SET bed_status = 'occupied' WHERE id = NEW.bed_id;
    END IF;
END//

-- Trigger to update total_beds in wards when a bed is added
CREATE TRIGGER after_bed_insert
AFTER INSERT ON beds
FOR EACH ROW
BEGIN
    UPDATE wards SET total_beds = total_beds + 1 WHERE id = NEW.ward_id;
END//

-- Trigger to update total_beds in wards when a bed is deleted
CREATE TRIGGER after_bed_delete
AFTER DELETE ON beds
FOR EACH ROW
BEGIN
    UPDATE wards SET total_beds = total_beds - 1 WHERE id = OLD.ward_id;
END//

DELIMITER ;

-- ================================================
-- SAMPLE DATA (Optional)
-- ================================================

-- Sample Wards
INSERT INTO wards (ward_number, ward_name, ward_type, floor_number, department, capacity, facilities, status) VALUES
('W-101', 'General Ward A', 'General', 1, 'General Medicine', 20, 'AC, TV, Nurse Station', 'active'),
('W-102', 'General Ward B', 'General', 1, 'General Medicine', 20, 'AC, TV, Nurse Station', 'active'),
('W-201', 'ICU Ward', 'ICU', 2, 'Critical Care', 10, 'Ventilators, Monitoring Equipment, Emergency Equipment', 'active'),
('W-202', 'Pediatric Ward', 'Pediatric', 2, 'Pediatrics', 15, 'Play Area, Colorful Rooms, Child-friendly', 'active'),
('W-301', 'Maternity Ward', 'Maternity', 3, 'Obstetrics', 12, 'Labor Rooms, Nursery, Mother Care', 'active'),
('W-302', 'Private Ward', 'Private', 3, 'VIP Services', 8, 'Private Rooms, AC, TV, Attached Bathroom, WiFi', 'active');

-- Sample Beds (First 10 beds for Ward 1)
INSERT INTO beds (bed_number, ward_id, bed_type, bed_status, price_per_day, features) VALUES
('B-101-01', 1, 'Standard', 'available', 500.00, 'Adjustable, Side Rails'),
('B-101-02', 1, 'Standard', 'available', 500.00, 'Adjustable, Side Rails'),
('B-101-03', 1, 'Oxygen Support', 'available', 750.00, 'Oxygen Support, Adjustable'),
('B-101-04', 1, 'Standard', 'available', 500.00, 'Adjustable, Side Rails'),
('B-101-05', 1, 'Standard', 'available', 500.00, 'Adjustable, Side Rails');

-- ================================================
-- USEFUL QUERIES
-- ================================================

-- Get ward occupancy statistics
-- SELECT 
--     w.ward_number,
--     w.ward_name,
--     w.ward_type,
--     w.total_beds,
--     w.occupied_beds,
--     w.available_beds,
--     ROUND((w.occupied_beds / w.total_beds * 100), 2) as occupancy_percentage
-- FROM wards w
-- WHERE w.status = 'active'
-- ORDER BY occupancy_percentage DESC;

-- Get available beds by ward type
-- SELECT 
--     w.ward_name,
--     b.bed_number,
--     b.bed_type,
--     b.price_per_day
-- FROM beds b
-- JOIN wards w ON b.ward_id = w.id
-- WHERE b.bed_status = 'available' 
-- AND w.status = 'active'
-- ORDER BY w.ward_name, b.bed_number;

-- Get current bed assignments with patient details
-- SELECT 
--     ba.assignment_number,
--     CONCAT(p.first_name, ' ', p.last_name) as patient_name,
--     p.patient_id,
--     w.ward_name,
--     b.bed_number,
--     ba.admission_date,
--     ba.patient_condition,
--     DATEDIFF(NOW(), ba.admission_date) as days_admitted
-- FROM bed_assignments ba
-- JOIN patients p ON ba.patient_id = p.id
-- JOIN wards w ON ba.ward_id = w.id
-- JOIN beds b ON ba.bed_id = b.id
-- WHERE ba.status = 'active'
-- ORDER BY ba.admission_date DESC;