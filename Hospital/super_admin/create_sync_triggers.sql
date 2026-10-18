-- Switch to php_ims database
USE php_ims;

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS sync_purchase_insert;
DROP TRIGGER IF EXISTS sync_purchase_update;
DROP TRIGGER IF EXISTS sync_purchase_delete;
DROP TRIGGER IF EXISTS sync_stock_insert;
DROP TRIGGER IF EXISTS sync_stock_update;
DROP TRIGGER IF EXISTS sync_purchase_before_insert;

-- Create BEFORE INSERT trigger to sync IDs
DELIMITER $$

CREATE TRIGGER sync_purchase_before_insert
BEFORE INSERT ON purchase_master
FOR EACH ROW
BEGIN
    DECLARE hospital_next_id INT DEFAULT NULL;
    
    -- Get next AUTO_INCREMENT value from hospital database
    SELECT AUTO_INCREMENT INTO hospital_next_id
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = 'hospital'
    AND TABLE_NAME = 'purchase_master';
    
    -- Use hospital's next ID if not specified
    IF NEW.id IS NULL OR NEW.id = 0 THEN
        IF hospital_next_id IS NOT NULL THEN
            SET NEW.id = hospital_next_id;
        END IF;
    END IF;
END$$

DELIMITER ;

-- Create AFTER INSERT trigger for purchase_master
DELIMITER $$

CREATE TRIGGER sync_purchase_insert
AFTER INSERT ON purchase_master
FOR EACH ROW
BEGIN
    INSERT INTO hospital.purchase_master (
        id, productcategory, company_name, product_name, unit, 
        packing_size, quantity, received_quantity, price, product_selling_price,
        party_name, purchase_method, expiry_date, purchase_date, username,
        status, received_date, received_by, notes
    ) VALUES (
        NEW.id, NEW.productcategory, NEW.company_name, NEW.product_name, NEW.unit,
        NEW.packing_size, NEW.quantity, NEW.received_quantity, NEW.price, NEW.product_selling_price,
        NEW.party_name, NEW.purchase_method, NEW.expiry_date, NEW.purchase_date, NEW.username,
        NEW.status, NEW.received_date, NEW.received_by, NEW.notes
    )
    ON DUPLICATE KEY UPDATE
        productcategory = NEW.productcategory,
        company_name = NEW.company_name,
        product_name = NEW.product_name,
        unit = NEW.unit,
        packing_size = NEW.packing_size,
        quantity = NEW.quantity,
        received_quantity = NEW.received_quantity,
        price = NEW.price,
        product_selling_price = NEW.product_selling_price,
        party_name = NEW.party_name,
        purchase_method = NEW.purchase_method,
        expiry_date = NEW.expiry_date,
        purchase_date = NEW.purchase_date,
        username = NEW.username,
        status = NEW.status,
        received_date = NEW.received_date,
        received_by = NEW.received_by,
        notes = NEW.notes;
END$$

DELIMITER ;

-- Create AFTER UPDATE trigger for purchase_master
DELIMITER $$

CREATE TRIGGER sync_purchase_update
AFTER UPDATE ON purchase_master
FOR EACH ROW
BEGIN
    UPDATE hospital.purchase_master
    SET 
        productcategory = NEW.productcategory,
        company_name = NEW.company_name,
        product_name = NEW.product_name,
        unit = NEW.unit,
        packing_size = NEW.packing_size,
        quantity = NEW.quantity,
        received_quantity = NEW.received_quantity,
        price = NEW.price,
        product_selling_price = NEW.product_selling_price,
        party_name = NEW.party_name,
        purchase_method = NEW.purchase_method,
        expiry_date = NEW.expiry_date,
        purchase_date = NEW.purchase_date,
        username = NEW.username,
        status = NEW.status,
        received_date = NEW.received_date,
        received_by = NEW.received_by,
        notes = NEW.notes
    WHERE id = NEW.id;
END$$

DELIMITER ;

-- Create AFTER INSERT trigger for stock_master
DELIMITER $$

CREATE TRIGGER sync_stock_insert
AFTER INSERT ON stock_master
FOR EACH ROW
BEGIN
    INSERT INTO hospital.stock_master (
        id, product_company, product_name, product_unit, packing_size,
        product_quantity, product_selling_price, product_base_price
    ) VALUES (
        NEW.id, NEW.product_company, NEW.product_name, NEW.product_unit, NEW.packing_size,
        NEW.product_quantity, NEW.product_selling_price, NEW.product_base_price
    )
    ON DUPLICATE KEY UPDATE
        product_company = NEW.product_company,
        product_name = NEW.product_name,
        product_unit = NEW.product_unit,
        packing_size = NEW.packing_size,
        product_quantity = NEW.product_quantity,
        product_selling_price = NEW.product_selling_price,
        product_base_price = NEW.product_base_price;
END$$

DELIMITER ;

-- Create AFTER UPDATE trigger for stock_master
DELIMITER $$

CREATE TRIGGER sync_stock_update
AFTER UPDATE ON stock_master
FOR EACH ROW
BEGIN
    UPDATE hospital.stock_master
    SET 
        product_company = NEW.product_company,
        product_name = NEW.product_name,
        product_unit = NEW.product_unit,
        packing_size = NEW.packing_size,
        product_quantity = NEW.product_quantity,
        product_selling_price = NEW.product_selling_price,
        product_base_price = NEW.product_base_price
    WHERE id = NEW.id;
END$$

DELIMITER ;

-- Show all created triggers
SHOW TRIGGERS FROM php_ims;