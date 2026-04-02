<?php
/**
 * Database Configuration for PeacePlot Cemetery Management System
 * SQLite Database Connection
 */

class Database {
    private $db_file;
    private $conn;
    
    public function __construct() {
        // Set the database file path
        $this->db_file = __DIR__ . '/../database/peaceplot.db';
        
        // Create database directory if it doesn't exist
        $db_dir = dirname($this->db_file);
        if (!file_exists($db_dir)) {
            mkdir($db_dir, 0755, true);
        }
    }
    
    /**
     * Get database connection
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            // Create SQLite connection
            $this->conn = new PDO("sqlite:" . $this->db_file);
            
            // Set error mode to exception
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Enable foreign key constraints
            $this->conn->exec('PRAGMA foreign_keys = ON;');
            
            // Set default fetch mode
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }
        
        return $this->conn;
    }
    
    /**
     * Initialize database with schema
     */
    public function initializeDatabase() {
        try {
            $schema_file = __DIR__ . '/../database/schema.sql';
            
            if (file_exists($schema_file)) {
                $schema = file_get_contents($schema_file);
                $this->conn->exec($schema);
                return true;
            }
            
            return false;
        } catch(PDOException $e) {
            echo "Schema Error: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Seed database with sample data
     */
    public function seedDatabase() {
        try {
            $seed_file = __DIR__ . '/../database/seed.sql';
            
            if (file_exists($seed_file)) {
                $seed = file_get_contents($seed_file);
                $this->conn->exec($seed);
                return true;
            }
            
            return false;
        } catch(PDOException $e) {
            echo "Seed Error: " . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Close database connection
     */
    public function closeConnection() {
        $this->conn = null;
    }
}
?>
