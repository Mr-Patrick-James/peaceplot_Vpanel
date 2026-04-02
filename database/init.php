<?php
/**
 * Database Initialization Script
 * Run this file once to create and seed the database
 */

require_once __DIR__ . '/../config/database.php';

echo "Initializing PeacePlot Database...\n\n";

// Create database instance
$database = new Database();
$conn = $database->getConnection();

if ($conn) {
    echo "✓ Database connection established\n";
    
    // Initialize schema
    echo "Creating database schema...\n";
    if ($database->initializeDatabase()) {
        echo "✓ Database schema created successfully\n";
    } else {
        echo "✗ Failed to create database schema\n";
        exit(1);
    }
    
    // Seed database
    echo "Seeding database with sample data...\n";
    if ($database->seedDatabase()) {
        echo "✓ Database seeded successfully\n";
    } else {
        echo "✗ Failed to seed database\n";
        exit(1);
    }
    
    echo "\n✓ Database initialization complete!\n";
    echo "Database file location: " . __DIR__ . "/peaceplot.db\n";
    
} else {
    echo "✗ Failed to connect to database\n";
    exit(1);
}

$database->closeConnection();
?>
