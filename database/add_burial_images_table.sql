-- Add burial record images table
-- This table stores multiple images for each burial record

CREATE TABLE IF NOT EXISTS burial_record_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    burial_record_id INTEGER NOT NULL,
    image_path VARCHAR(500) NOT NULL,
    image_caption VARCHAR(255),
    image_type VARCHAR(50) DEFAULT 'grave_photo', -- grave_photo, headstone, cemetery_view, etc.
    display_order INTEGER DEFAULT 0,
    is_primary BOOLEAN DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (burial_record_id) REFERENCES deceased_records(id) ON DELETE CASCADE
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_burial_images_record ON burial_record_images(burial_record_id);
CREATE INDEX IF NOT EXISTS idx_burial_images_primary ON burial_record_images(burial_record_id, is_primary);
CREATE INDEX IF NOT EXISTS idx_burial_images_order ON burial_record_images(burial_record_id, display_order);
