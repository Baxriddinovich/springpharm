<?php
// setup_db.php - Ma'lumotlar bazasi jadvallarini yaratish
require_once 'db.php';

// SQL jadvallar
 $sql = "
-- Foydalanuvchilar
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    role ENUM('super_admin', 'bosh_auditor', 'auditor', 'viewer') DEFAULT 'auditor',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sites (Korxonalar)
CREATE TABLE IF NOT EXISTS sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    address TEXT,
    country VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- GMP Bo'limlari
CREATE TABLE IF NOT EXISTS gmp_sections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_number VARCHAR(10) NOT NULL,
    section_name VARCHAR(200) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Checklist savollari
CREATE TABLE IF NOT EXISTS checklist_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    section_id INT NOT NULL,
    question_text TEXT NOT NULL,
    score DECIMAL(5,2) DEFAULT 1.00,
    is_required TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES gmp_sections(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Severity turlari
CREATE TABLE IF NOT EXISTS severity_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    name_en VARCHAR(50) NOT NULL,
    color_code VARCHAR(7) NOT NULL,
    sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Auditlar
CREATE TABLE IF NOT EXISTS audits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_code VARCHAR(50) UNIQUE NOT NULL,
    site_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    status ENUM('draft', 'in_progress', 'completed', 'cancelled') DEFAULT 'draft',
    total_score DECIMAL(10,2) DEFAULT 0,
    max_score DECIMAL(10,2) DEFAULT 0,
    progress_percent DECIMAL(5,2) DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES sites(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit biriktirmalar
CREATE TABLE IF NOT EXISTS audit_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_id INT NOT NULL,
    auditor_id INT NOT NULL,
    section_id INT NOT NULL,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    assigned_by INT NOT NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (audit_id) REFERENCES audits(id),
    FOREIGN KEY (auditor_id) REFERENCES users(id),
    FOREIGN KEY (section_id) REFERENCES gmp_sections(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit javoblari
CREATE TABLE IF NOT EXISTS audit_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_id INT NOT NULL,
    question_id INT NOT NULL,
    auditor_id INT NOT NULL,
    answer ENUM('ha', 'yoq', 'na') DEFAULT 'na',
    score DECIMAL(5,2) DEFAULT 0,
    notes TEXT,
    answered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_answer (audit_id, question_id),
    FOREIGN KEY (audit_id) REFERENCES audits(id),
    FOREIGN KEY (question_id) REFERENCES checklist_questions(id),
    FOREIGN KEY (auditor_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nomuvofiqliklar
CREATE TABLE IF NOT EXISTS non_conformities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nc_code VARCHAR(50) UNIQUE NOT NULL,
    audit_id INT NOT NULL,
    question_id INT NOT NULL,
    answer_id INT NOT NULL,
    nc_number INT NOT NULL,
    severity_id INT NOT NULL,
    description TEXT NOT NULL,
    status ENUM('open', 'in_review', 'closed') DEFAULT 'open',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (audit_id) REFERENCES audits(id),
    FOREIGN KEY (question_id) REFERENCES checklist_questions(id),
    FOREIGN KEY (answer_id) REFERENCES audit_answers(id),
    FOREIGN KEY (severity_id) REFERENCES severity_types(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Hisobotlar
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    audit_id INT NOT NULL,
    report_type VARCHAR(50) DEFAULT 'full',
    file_path VARCHAR(500),
    generated_by INT NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (audit_id) REFERENCES audits(id),
    FOREIGN KEY (generated_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

try {
    $pdo->exec($sql);
    
    // Standart ma'lumotlar qo'shish
    $seedData = "
    -- Severity turlari
    INSERT IGNORE INTO severity_types (id, name, name_en, color_code, sort_order) VALUES
    (1, 'Jiddiy bo\\'lmagan', 'Minor', '#10B981', 1),
    (2, 'Jiddiy', 'Major', '#F59E0B', 2),
    (3, 'O\\'ta jiddiy', 'Critical', '#EF4444', 3);
    
    -- GMP Bo'limlari
    INSERT IGNORE INTO gmp_sections (id, section_number, section_name, description, sort_order) VALUES
    (1, 'I', 'Umumiy talablar', 'GMP asosiy talablari va umumiy qoidalar', 1),
    (2, 'II', 'Xodimlar', 'Xodimlar malakasi va javobgarligi', 2),
    (3, 'III', 'Binolar va jihozlar', 'Ishlab chiqarish binolari va jihozlari', 3),
    (4, 'IV', 'Uskunalar', 'Ishlab chiqarish uskunalari', 4),
    (5, 'V', 'Hujjatlashtirish', 'Hujjatlar va yozuvlar', 5),
    (6, 'VI', 'Ishlab chiqarish', 'Ishlab chiqarish jarayonlari', 6),
    (7, 'VII', 'Sifat nazorati', 'QC laboratoriyasi va nazorat', 7),
    (8, 'VIII', 'Shikoyatlar va qaytarish', 'Shikoyatlar va mahsulot qaytarish', 8),
    (9, 'IX', 'O\\'z-o\\'zini tekshirish', 'Ichki audit va o\\'z-o\\'zini baholash', 9);
    
    -- Super Admin
    INSERT IGNORE INTO users (id, username, email, password, full_name, role) VALUES
    (1, 'admin', 'admin@gmp.uz', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Tizim Administratori', 'super_admin');
    
    -- Namunaviy site
    INSERT IGNORE INTO sites (id, name, address, country) VALUES
    (1, 'PharmaTech LLC', 'Toshkent sh., Sergeli tum., Pharma ko\\'chasi 1', 'O\\'zbekiston');
    
    -- Namunaviy savollar
    INSERT IGNORE INTO checklist_questions (section_id, question_text, score, is_required, sort_order) VALUES
    (1, 'Korxonada GMP bo\\'yicha qo\\'llanma mavjudmi?', 2.00, 1, 1),
    (1, 'Sifat siyosati hujjati tasdiqlangan va e\\'lon qilinganmi?', 2.00, 1, 2),
    (1, 'Sifat boshqaruvi tizimi samarali ishlamoqdami?', 3.00, 1, 3),
    (2, 'Xodimlar malaka oshirish dasturi mavjudmi?', 2.00, 1, 1),
    (2, 'Har bir xodim uchun lavozim talablari belgilanganmi?', 1.50, 1, 2),
    (2, 'Xodimlar salomatlik tekshiruvidan o\\'tganmi?', 2.00, 1, 3),
    (3, 'Ishlab chiqarish maydonlari yetarli miqdordami?', 2.00, 1, 1),
    (3, 'Binolar toza va tartibli holatda saqlanadimi?', 2.50, 1, 2),
    (3, 'Havo, yorug\\'lik va harorat nazorati mavjudmi?', 2.00, 1, 3),
    (4, 'Uskunalar kalibrlash sertifikatlari mavjudmi?', 3.00, 1, 1),
    (4, 'Uskunalar tozalash protseduralari amalga oshiriladimi?', 2.00, 1, 2),
    (5, 'Hujjatlar boshqaruvi tizimi mavjudmi?', 2.50, 1, 1),
    (5, 'Yozuvlar saqlash muddatlari belgilanganmi?', 1.50, 1, 2),
    (6, 'Ishlab chiqarish jarayonlari tasdiqlanganmi?', 3.00, 1, 1),
    (6, 'Bach yozuvlari to\\'g\\'ri yuritiladimi?', 2.00, 1, 2),
    (7, 'QC laboratoriyasi jihozlanganmi?', 3.00, 1, 1),
    (7, 'Namuna olish protseduralari mavjudmi?', 2.00, 1, 2),
    (8, 'Shikoyatlar ro\\'yxati yuritiladimi?', 2.00, 1, 1),
    (9, 'Ichki audit rejalari tuzilganmi?', 2.50, 1, 1);
    ";
    
    $pdo->exec($seedData);
    
    echo "<div style='font-family: monospace; background: #0f172a; color: #10b981; padding: 40px; border-radius: 12px; max-width: 600px; margin: 50px auto;'>";
    echo "<h2 style='color: #38bdf8;'>✓ Ma'lumotlar bazasi muvaffaqiyatli yaratildi!</h2>";
    echo "<p style='color: #94a3b8;'>Barcha jadvallar va standart ma'lumotlar qo'shildi.</p>";
    echo "<hr style='border-color: #334155; margin: 20px 0;'>";
    echo "<p><strong style='color: #f8fafc;'>Login ma'lumotlari:</strong></p>";
    echo "<p style='color: #fbbf24;'>Username: <code style='background: #1e293b; padding: 4px 8px; border-radius: 4px;'>admin</code></p>";
    echo "<p style='color: #fbbf24;'>Password: <code style='background: #1e293b; padding: 4px 8px; border-radius: 4px;'>password</code></p>";
    echo "<a href='index.php' style='display: inline-block; margin-top: 20px; background: linear-gradient(135deg, #0ea5e9, #06b6d4); color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold;'>Tizimga kirish →</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='font-family: monospace; background: #1e1e1e; color: #ef4444; padding: 40px; border-radius: 12px;'>";
    echo "<h2>Xatolik yuz berdi:</h2>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    echo "</div>";
}
?>