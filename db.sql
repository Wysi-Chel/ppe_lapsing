CREATE DATABASE IF NOT EXISTS ppe_ai_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE ppe_ai_system;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('Admin', 'Accounting Staff', 'Auditor') DEFAULT 'Accounting Staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_category_name (category_name)
);

CREATE TABLE IF NOT EXISTS departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    department_name VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_department_name (department_name)
);

CREATE TABLE IF NOT EXISTS assets (
    asset_id INT AUTO_INCREMENT PRIMARY KEY,
    asset_code VARCHAR(50) NOT NULL UNIQUE,
    asset_name VARCHAR(150) NOT NULL,
    category_id INT NULL,
    department_id INT NULL,
    acquisition_date DATE NOT NULL,
    acquisition_cost DECIMAL(15,2) NOT NULL,
    salvage_value DECIMAL(15,2) DEFAULT 0,
    useful_life INT NOT NULL,
    depreciation_method VARCHAR(50) DEFAULT 'Straight-line',
    location VARCHAR(150) DEFAULT NULL,
    status ENUM('Active', 'Disposed', 'Fully Depreciated') DEFAULT 'Active',
    remarks TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_assets_status (status),
    INDEX idx_assets_category (category_id),
    INDEX idx_assets_department (department_id),
    CONSTRAINT fk_assets_category
        FOREIGN KEY (category_id) REFERENCES categories(category_id),
    CONSTRAINT fk_assets_department
        FOREIGN KEY (department_id) REFERENCES departments(department_id)
);

CREATE TABLE IF NOT EXISTS depreciation_schedule (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    asset_id INT NOT NULL,
    depreciation_year YEAR NOT NULL,
    beginning_value DECIMAL(15,2) NOT NULL,
    depreciation_expense DECIMAL(15,2) NOT NULL,
    accumulated_depreciation DECIMAL(15,2) NOT NULL,
    ending_value DECIMAL(15,2) NOT NULL,
    CONSTRAINT fk_depreciation_asset
        FOREIGN KEY (asset_id) REFERENCES assets(asset_id) ON DELETE CASCADE
);

INSERT INTO categories (category_name)
VALUES
    ('Computer Equipment'),
    ('Office Furniture'),
    ('Office Equipment'),
    ('Building'),
    ('Vehicle')
ON DUPLICATE KEY UPDATE
    category_name = VALUES(category_name);

INSERT INTO departments (department_id, department_name)
VALUES
    (1, 'ACCOUNTING'),
    (2, 'SALES'),
    (3, 'SERVICE'),
    (4, 'PARTS'),
    (5, 'BNC'),
    (6, 'CNC'),
    (7, 'MANILA'),
    (8, 'BRP')
ON DUPLICATE KEY UPDATE
    department_name = VALUES(department_name);

INSERT INTO users (full_name, email, password, role)
VALUES
    ('System Administrator', 'admin@ppe.local', '$2y$10$gbEduvqVuZoG/HRLLe/MT.6xH47Yqc6SMycxRijuoy3l9nnLeOs1O', 'Admin')
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    password = VALUES(password),
    role = VALUES(role);

INSERT INTO assets (
    asset_code,
    asset_name,
    category_id,
    department_id,
    acquisition_date,
    acquisition_cost,
    salvage_value,
    useful_life,
    depreciation_method,
    location,
    status,
    remarks
)
VALUES
    (
        'PPE-2026-001',
        'Acer TravelMate Laptop',
        (SELECT category_id FROM categories WHERE category_name = 'Computer Equipment' LIMIT 1),
        1,
        '2024-06-15',
        45000.00,
        5000.00,
        5,
        'Straight-line',
        'Finance Hub',
        'Active',
        'Used for monthly reporting and reconciliations'
    ),
    (
        'PPE-2026-002',
        'Ricoh Multifunction Printer',
        (SELECT category_id FROM categories WHERE category_name = 'Office Equipment' LIMIT 1),
        2,
        '2023-08-20',
        32000.00,
        4000.00,
        5,
        'Straight-line',
        'Records Counter',
        'Active',
        'Prints official forms and school clearances'
    ),
    (
        'PPE-2026-003',
        'Executive Workstation Desk',
        (SELECT category_id FROM categories WHERE category_name = 'Office Furniture' LIMIT 1),
        3,
        '2020-03-10',
        18000.00,
        3000.00,
        10,
        'Straight-line',
        'Admin Office',
        'Active',
        'Main records review desk'
    ),
    (
        'PPE-2026-004',
        'Toyota Service Van',
        (SELECT category_id FROM categories WHERE category_name = 'Vehicle' LIMIT 1),
        4,
        '2019-01-05',
        850000.00,
        100000.00,
        8,
        'Straight-line',
        'Motor Pool',
        'Active',
        'Used for inter-campus document and equipment transport'
    ),
    (
        'PPE-2026-005',
        'Biometric Attendance Terminal',
        (SELECT category_id FROM categories WHERE category_name = 'Office Equipment' LIMIT 1),
        5,
        '2021-11-12',
        28000.00,
        2000.00,
        4,
        'Straight-line',
        'Server Room',
        'Disposed',
        'Replaced after hardware failure'
    )
ON DUPLICATE KEY UPDATE
    asset_name = VALUES(asset_name),
    category_id = VALUES(category_id),
    department_id = VALUES(department_id),
    acquisition_date = VALUES(acquisition_date),
    acquisition_cost = VALUES(acquisition_cost),
    salvage_value = VALUES(salvage_value),
    useful_life = VALUES(useful_life),
    depreciation_method = VALUES(depreciation_method),
    location = VALUES(location),
    status = VALUES(status),
    remarks = VALUES(remarks);
