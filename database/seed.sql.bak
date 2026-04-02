-- Sample data for PeacePlot Cemetery Management System

-- Insert sample cemetery lots (skip if already exists)
INSERT OR IGNORE INTO cemetery_lots (lot_number, section, block, position, status, size_sqm, price) VALUES
('A-001', 'Section A', 'Block 1', 'Row 1, Plot 1', 'Occupied', 4.00, 50000.00),
('A-002', 'Section A', 'Block 1', 'Row 1, Plot 2', 'Vacant', 4.00, 50000.00),
('A-003', 'Section A', 'Block 1', 'Row 1, Plot 3', 'Occupied', 4.00, 50000.00),
('A-004', 'Section A', 'Block 1', 'Row 1, Plot 4', 'Vacant', 4.00, 50000.00),
('A-005', 'Section A', 'Block 1', 'Row 1, Plot 5', 'Vacant', 4.00, 50000.00),
('B-001', 'Section B', 'Block 2', 'Row 1, Plot 1', 'Occupied', 6.00, 75000.00),
('B-002', 'Section B', 'Block 2', 'Row 1, Plot 2', 'Vacant', 6.00, 75000.00),
('B-003', 'Section B', 'Block 2', 'Row 1, Plot 3', 'Occupied', 6.00, 75000.00);

-- Insert sample deceased records (skip if already exists)
INSERT OR IGNORE INTO deceased_records (lot_id, full_name, date_of_birth, date_of_death, date_of_burial, age, next_of_kin, next_of_kin_contact) VALUES
(1, 'John Smith', '1945-03-15', '2023-06-20', '2023-06-25', 78, 'Jane Smith', '+1-555-0101'),
(3, 'Mary Johnson', '1950-08-22', '2023-09-10', '2023-09-15', 73, 'Robert Johnson', '+1-555-0102'),
(6, 'Robert Williams', '1938-12-05', '2023-04-18', '2023-04-23', 85, 'Sarah Williams', '+1-555-0103'),
(8, 'Patricia Brown', '1955-07-30', '2023-11-02', '2023-11-07', 68, 'Michael Brown', '+1-555-0104');

-- Insert default admin user (password: admin123 - should be changed in production)
-- Note: In production, use proper password hashing
INSERT OR IGNORE INTO users (username, password_hash, full_name, email, role, is_active) VALUES
('admin', 'admin123', 'Admin User', 'admin@peaceplot.com', 'admin', 1);
