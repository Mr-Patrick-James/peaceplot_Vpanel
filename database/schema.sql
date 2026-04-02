-- PeacePlot Cemetery Management System Database Schema
-- SQLite Database

-- Blocks Table
CREATE TABLE IF NOT EXISTS blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Cemetery Lots Table
CREATE TABLE IF NOT EXISTS sections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    block_id INTEGER,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS cemetery_lots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lot_number VARCHAR(20) NOT NULL,
    section_id INTEGER,
    position VARCHAR(50),
    status VARCHAR(20) NOT NULL CHECK(status IN ('Vacant', 'Occupied', 'Maintenance')),
    size_sqm DECIMAL(10,2),
    price DECIMAL(10,2),
    map_x DECIMAL(10,4),
    map_y DECIMAL(10,4),
    map_width DECIMAL(10,4),
    map_height DECIMAL(10,4),
    layers INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL,
    UNIQUE(lot_number, section_id)
);

-- Deceased Records Table
CREATE TABLE IF NOT EXISTS deceased_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lot_id INTEGER,
    full_name VARCHAR(255) NOT NULL,
    date_of_birth DATE,
    date_of_death DATE,
    date_of_burial DATE,
    age INTEGER,
    cause_of_death TEXT,
    next_of_kin VARCHAR(255),
    next_of_kin_contact VARCHAR(100),
    deceased_info TEXT,
    remarks TEXT,
    is_archived BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lot_id) REFERENCES cemetery_lots(id) ON DELETE SET NULL
);

-- Payments Table
CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lot_id INTEGER,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    reference_number VARCHAR(100),
    remarks TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lot_id) REFERENCES cemetery_lots(id) ON DELETE SET NULL
);

-- Maintenance Records Table
CREATE TABLE IF NOT EXISTS maintenance_records (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lot_id INTEGER NOT NULL,
    maintenance_date DATE NOT NULL,
    maintenance_type VARCHAR(100),
    description TEXT,
    cost DECIMAL(10,2),
    performed_by VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lot_id) REFERENCES cemetery_lots(id) ON DELETE CASCADE
);

-- Users Table (for admin access)
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    email VARCHAR(255),
    role VARCHAR(50) DEFAULT 'staff' CHECK(role IN ('admin', 'staff', 'viewer')),
    is_active BOOLEAN DEFAULT 1,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Activity Logs Table
CREATE TABLE IF NOT EXISTS activity_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(100),
    record_id INTEGER,
    description TEXT,
    ip_address VARCHAR(45),
    is_archived BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Indexes for better query performance
CREATE INDEX IF NOT EXISTS idx_lots_status ON cemetery_lots(status);
CREATE INDEX IF NOT EXISTS idx_lots_section ON cemetery_lots(section);
CREATE INDEX IF NOT EXISTS idx_lots_number ON cemetery_lots(lot_number);
CREATE INDEX IF NOT EXISTS idx_deceased_lot ON deceased_records(lot_id);
CREATE INDEX IF NOT EXISTS idx_maintenance_lot ON maintenance_records(lot_id);
CREATE INDEX IF NOT EXISTS idx_logs_user ON activity_logs(user_id);
CREATE INDEX IF NOT EXISTS idx_logs_created ON activity_logs(created_at);
