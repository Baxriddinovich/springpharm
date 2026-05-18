-- GXP PHARM - OMBOR + QC TIZIMI DATABASE SCHEMA
-- Tayyorlovchi: GXP Service Pharm

-- 1. Foydalanuvchilar jadvali
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fullname VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('Admin', 'Ombor mudiri', 'QC xodimi', 'QC rahbari', 'Ishlab chiqarish', 'Ta\'minotchi', 'Auditor') NOT NULL DEFAULT 'Ta\'minotchi',
    status TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Materiallar (mahsulotlar) справочниги
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_name VARCHAR(255) NOT NULL,
    material_type ENUM('API', 'Yordamchi modda', 'Birlamchi qadoqlash', 'Ikkilamchi qadoqlash') NOT NULL,
    unit ENUM('kg', 'g', 'dona', 'litr', 'ml', 'mkg', 'mml') NOT NULL,
    category VARCHAR(100),
    supplier_id INT DEFAULT NULL,
    min_stock_level DECIMAL(10,2) DEFAULT 0,
    storage_temp VARCHAR(50),
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_material_name (material_name),
    INDEX idx_type (material_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Ta'minotchilar
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(100),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    status TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Inbound (Kirim) - Partiyalar
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_id INT NOT NULL,
    batch_number VARCHAR(100) UNIQUE NOT NULL,
    supplier_id INT DEFAULT NULL,
    manufacturer VARCHAR(255),
    received_date DATE NOT NULL,
    production_date DATE NOT NULL,
    exp_date DATE NOT NULL,
    quantity DECIMAL(15,2) NOT NULL,
    current_quantity DECIMAL(15,2) NOT NULL DEFAULT 0,
    unit VARCHAR(20) NOT NULL,
    storage_location VARCHAR(100),
    storage_temp VARCHAR(50),
    status ENUM('KARANTIN', 'RUXSAT ETILGAN', 'RAD ETILGAN', 'UTILIZATSIYA') DEFAULT 'KARANTIN',
    received_by INT,
    qr_code VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_batch_number (batch_number),
    INDEX idx_material_id (material_id),
    INDEX idx_status (status),
    INDEX idx_exp_date (exp_date),
    FOREIGN KEY (material_id) REFERENCES materials(id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES users(id),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. QC Tahlillar
CREATE TABLE IF NOT EXISTS qc_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    test_type ENUM('Mikrobiologik', 'Kimyoviy', 'Fizik', 'Fizika-kimyoviy', 'Boshqa') NOT NULL,
    test_date DATE,
    sample_taken_date DATE,
    sample_taken_by INT,
    test_result ENUM('Qabul qilish', 'Rad etish') DEFAULT 'Qabul qilish',
    test_report_file VARCHAR(255),
    comments TEXT,
    approved_by INT,
    approved_date DATETIME,
    status ENUM('Kutilmoqda', 'Natija keltirilgan', 'Rad etilgan') DEFAULT 'Kutilmoqda',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inventory_id (inventory_id),
    INDEX idx_status (status),
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (sample_taken_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. QC hujjatlari (CoA, GMP, Invoice, Packing list)
CREATE TABLE IF NOT EXISTS qc_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    document_type ENUM('CoA', 'GMP sertifikat', 'Invoice', 'Packing list', 'Transport', 'Boshqa') NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    description TEXT,
    INDEX idx_inventory_id (inventory_id),
    FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Outbound (Chiqim)
CREATE TABLE IF NOT EXISTS outbound (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    quantity DECIMAL(15,2) NOT NULL,
    issued_to INT,
    issued_by INT,
    production_order_no VARCHAR(100),
    issue_date DATE NOT NULL,
    delivery_date DATE,
    recipient_name VARCHAR(100),
    electronic_signature VARCHAR(255),
    notes TEXT,
    status ENUM('Kutilmoqda', 'Tasdiqlangan', 'Bekor qilingan') DEFAULT 'Kutilmoqda',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inventory_id (inventory_id),
    INDEX idx_issue_date (issue_date),
    FOREIGN KEY (inventory_id) REFERENCES inventory(id),
    FOREIGN KEY (issued_to) REFERENCES users(id),
    FOREIGN KEY (issued_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Utilizatsiya (Rad etilgan mahsulotlar)
CREATE TABLE IF NOT EXISTS utilization (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    reason TEXT NOT NULL,
    decision_by INT,
    decision_date DATETIME,
    utilization_date DATE,
    utilization_method ENUM('Yo'q qilish', 'Qayta ishlash', 'Boshqa'),
    documents_attached VARCHAR(255),
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_inventory_id (inventory_id),
    FOREIGN KEY (inventory_id) REFERENCES inventory(id),
    FOREIGN KEY (decision_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Audit Trail (Barcha harakatlar logi)
CREATE TABLE IF NOT EXISTS audit_trail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(50),
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_action (action),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Production Orders (Ishlab chiqarish buyurtmalari)
CREATE TABLE IF NOT EXISTS production_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_no VARCHAR(100) UNIQUE NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity DECIMAL(15,2) NOT NULL,
    status ENUM('Kutilmoqda', 'Bajarilmoqda', 'Tayyor', 'Bekor qilingan') DEFAULT 'Kutilmoqda',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME,
    INDEX idx_order_no (order_no),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Production Order Materials (Buyurtma materiallari)
CREATE TABLE IF NOT EXISTS production_order_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    production_order_id INT NOT NULL,
    material_id INT NOT NULL,
    required_quantity DECIMAL(15,2) NOT NULL,
    allocated_quantity DECIMAL(15,2) DEFAULT 0,
    issued_quantity DECIMAL(15,2) DEFAULT 0,
    status ENUM('Kutilmoqda', 'Ajratilgan', 'Chiqarilgan') DEFAULT 'Kutilmoqda',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_id (production_order_id),
    FOREIGN KEY (production_order_id) REFERENCES production_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (material_id) REFERENCES materials(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. QR Code Log (Yorliq chiqarish logi)
CREATE TABLE IF NOT EXISTS qr_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inventory_id INT NOT NULL,
    printer_ip VARCHAR(45),
    print_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    INDEX idx_inventory_id (inventory_id),
    FOREIGN KEY (inventory_id) REFERENCES inventory(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- BOSHLANG'ICH MA'LUMOTLAR (DEFAULT DATA)
-- ============================================

-- Admin foydalanuvchisi (parol: admin123)
INSERT INTO users (username, password, fullname, email, role, status) 
VALUES ('admin', '$2y$10$92IXUNskEg8rj2q6G6JvUuFk5wC5Q5bF2vL5wC5Q5Q5Q5Q5Q5Q5Q5Q', 'System Administrator', 'admin@gxppharm.com', 'Admin', 1);

-- Ombor mudiri
INSERT INTO users (username, password, fullname, email, role, status) 
VALUES ('ombor', '$2y$10$92IXUNskEg8rj2q6G6JvUuFk5wC5Q5bF2vL5wC5Q5Q5Q5Q5Q5Q5Q', 'Ombor Mudiri', 'ombor@gxppharm.com', 'Ombor mudiri', 1);

-- QC xodimi
INSERT INTO users (username, password, fullname, email, role, status) 
VALUES ('qc', '$2y$10$92IXUNskEg8rj2q6G6JvUuFk5wC5Q5bF2vL5wC5Q5Q5Q5Q5Q5Q5Q', 'QC Xodimi', 'qc@gxppharm.com', 'QC xodimi', 1);

-- QC rahbari
INSERT INTO users (username, password, fullname, email, role, status) 
VALUES ('qc_head', '$2y$10$92IXUNskEg8rj2q6G6JvUuFk5wC5Q5bF2vL5wC5Q5Q5Q5Q5Q5Q5Q', 'QC Rahbari', 'qc_head@gxppharm.com', 'QC rahbari', 1);

-- Ishlab chiqarish bo'limi
INSERT INTO users (username, password, fullname, email, role, status) 
VALUES ('production', '$2y$10$92IXUNskEg8rj2q6G6JvUuFk5wC5Q5bF2vL5wC5Q5Q5Q5Q5Q5Q5Q', 'Ishlab Chiqarish', 'production@gxppharm.com', 'Ishlab chiqarish', 1);

-- Ta'minotchi
INSERT INTO users (username, password, fullname, email, role, status) 
VALUES ('supplier', '$2y$10$92IXUNskEg8rj2q6G6JvUuFk5wC5Q5bF2vL5wC5Q5Q5Q5Q5Q5Q5Q', 'Ta\'minotchi Portal', 'supplier@gxppharm.com', 'Ta\'minotchi', 1);

-- Auditor
INSERT INTO users (username, password, fullname, email, role, status) 
VALUES ('auditor', '$2y$10$92IXUNskEg8rj2q6G6JvUuFk5wC5Q5bF2vL5wC5Q5Q5Q5Q5Q5Q5Q', 'Auditor', 'auditor@gxppharm.com', 'Auditor', 1);

-- Material turlari справочниги
INSERT INTO materials (material_name, material_type, unit, category, storage_temp) VALUES
('Paracetamol API', 'API', 'kg', 'Farmatsevtik xom-ashyo', '15-25°C'),
('Aspirin API', 'API', 'kg', 'Farmatsevtik xom-ashyo', '15-25°C'),
('Laktoz', 'Yordamchi modda', 'kg', 'Qoldiruvchi moddalar', '15-25°C'),
('Magneziy stearat', 'Yordamchi modda', 'kg', 'Lubrikantlar', '15-25°C'),
('PVC/Al foil blistery', 'Birlamchi qadoqlash', 'dona', 'Birlamchi qadoqlash', '15-25°C'),
('Carton qutilar', 'Ikkilamchi qadoqlash', 'dona', 'Ikkilamchi qadoqlash', '15-25°C'),
('Etil spirti 96%', 'Yordamchi modda', 'litr', 'Disinfektantlar', '15-25°C'),
('Distillangan suv', 'Yordamchi modda', 'litr', 'Suv', '15-25°C');

-- Ta'minotchilar
INSERT INTO suppliers (company_name, contact_person, phone, email, address) VALUES
('Pharma Supply Co.', 'Ali Valiyev', '+998901234567', 'ali@pharmasupply.com', 'Toshkent, Yashnabod'),
('Medicines International', 'Sara Karimova', '+998909876543', 'sara@medint.com', 'Samarkand, Urgut'),
('Local Chemicals', 'Bekzod Muminov', '+998905551234', 'bekzod@localchem.uz', 'Namangan, Markaziy'),
('Global Pharma Ltd', 'Dilnoza Rahimova', '+998907778899', 'dilnoza@globalpharma.com', 'Andijon, Shaxrikhan');

-- ============================================
-- INDEXLAR YARATISH (PERFORMANS UCHUN)
-- ============================================
CREATE INDEX idx_inventory_status ON inventory(status);
CREATE INDEX idx_inventory_exp ON inventory(exp_date);
CREATE INDEX idx_inventory_batch ON inventory(batch_number);
CREATE INDEX idx_qc_status ON qc_tests(status);
CREATE INDEX idx_outbound_status ON outbound(status);

-- ============================================
-- VIEWLAR (HISOBOTLAR UCHUN)
-- ============================================

-- Karantindagi mahsulotlar view
CREATE OR REPLACE VIEW v_karantin_items AS
SELECT i.*, m.material_name, m.material_type, s.company_name as supplier_name
FROM inventory i
JOIN materials m ON i.material_id = m.id
LEFT JOIN suppliers s ON i.supplier_id = s.id
WHERE i.status = 'KARANTIN';

-- Ruxsat etilgan mahsulotlar view
CREATE OR REPLACE VIEW v_approved_items AS
SELECT i.*, m.material_name, m.material_type, s.company_name as supplier_name
FROM inventory i
JOIN materials m ON i.material_id = m.id
LEFT JOIN suppliers s ON i.supplier_id = s.id
WHERE i.status = 'RUXSAT ETILGAN';

-- FEFO alert view (6 oy ichida muddati o'tadiganlar)
CREATE OR REPLACE VIEW v_fefo_alert AS
SELECT i.batch_number, i.exp_date, m.material_name, m.material_type, i.current_quantity, i.storage_location
FROM inventory i
JOIN materials m ON i.material_id = m.id
WHERE i.exp_date <= DATE_ADD(CURDATE(), INTERVAL 6 MONTH)
  AND i.current_quantity > 0
  AND i.status = 'RUXSAT ETILGAN';

-- ============================================
-- TRIGGERLAR (FEFO AUTOMATIK HISOBLOV UCHUN)
-- ============================================

DELIMITER //
CREATE TRIGGER before_inventory_insert
BEFORE INSERT ON inventory
FOR EACH ROW
BEGIN
    SET NEW.current_quantity = NEW.quantity;
END//
DELIMITER ;

DELIMITER //
CREATE TRIGGER before_inventory_update
BEFORE UPDATE ON inventory
FOR EACH ROW
BEGIN
    IF NEW.status = 'RAD ETILGAN' AND OLD.status != 'RAD ETILGAN' THEN
        INSERT INTO utilization (inventory_id, reason, decision_by, decision_date, utilization_method)
        VALUES (NEW.id, 'Rad etilgan sifatida', NEW.received_by, NOW(), 'Yo\'q qilish');
    END IF;
END//
DELIMITER ;

-- ============================================
-- END OF SCHEMA
-- ============================================
