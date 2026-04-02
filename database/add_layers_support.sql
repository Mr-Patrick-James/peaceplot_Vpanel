-- Add Layer Support to Cemetery Lots
-- This migration adds layer functionality to support multiple burial layers per lot

-- Add layer column to cemetery_lots table
ALTER TABLE cemetery_lots ADD COLUMN layers INTEGER DEFAULT 1;

-- Add layer column to deceased_records table to track which layer the deceased is buried in
ALTER TABLE deceased_records ADD COLUMN layer INTEGER DEFAULT 1;

-- Create a table to track layer availability for each lot
CREATE TABLE IF NOT EXISTS lot_layers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    lot_id INTEGER NOT NULL,
    layer_number INTEGER NOT NULL,
    is_occupied BOOLEAN DEFAULT 0,
    burial_record_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lot_id) REFERENCES cemetery_lots(id) ON DELETE CASCADE,
    FOREIGN KEY (burial_record_id) REFERENCES deceased_records(id) ON DELETE SET NULL,
    UNIQUE(lot_id, layer_number)
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_lot_layers_lot ON lot_layers(lot_id);
CREATE INDEX IF NOT EXISTS idx_lot_layers_occupied ON lot_layers(is_occupied);
CREATE INDEX IF NOT EXISTS idx_deceased_layer ON deceased_records(layer);

-- Initialize existing lots with default layer structure
INSERT OR IGNORE INTO lot_layers (lot_id, layer_number, is_occupied)
SELECT id, 1, 0 FROM cemetery_lots;

-- Update existing lots to have at least 1 layer
UPDATE cemetery_lots SET layers = 1 WHERE layers IS NULL OR layers = 0;
