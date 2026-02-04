<?php
// Script to convert MySQL schema to SQLite database

function convertMySQLToSQLite($mysqlSQL) {
    // Split into lines first to handle comments properly
    $lines = explode("\n", $mysqlSQL);
    $cleanLines = [];

    foreach ($lines as $line) {
        $line = trim($line);
        // Skip empty lines and comments
        if (empty($line) || preg_match('/^--/', $line) || preg_match('/^\/\*/', $line)) continue;
        $cleanLines[] = $line;
    }

    $cleanSQL = implode("\n", $cleanLines);

    // Now split into statements
    $statements = array_filter(array_map('trim', explode(';', $cleanSQL)));
    $convertedStatements = [];

    foreach ($statements as $statement) {
        if (empty($statement)) continue;

        // Skip MySQL specific statements
        if (preg_match('/^(SET|START|COMMIT)/', $statement)) continue;
        if (preg_match('/CREATE DATABASE/', $statement)) continue;
        if (preg_match('/USE /', $statement)) continue;

        // Convert CREATE TABLE statements
        if (preg_match('/CREATE TABLE/', $statement)) {
            // Convert AUTO_INCREMENT to AUTOINCREMENT
            $statement = str_replace('AUTO_INCREMENT', 'AUTOINCREMENT', $statement);

            // Convert MySQL data types to SQLite
            $statement = preg_replace('/\bint\(\d+\)/i', 'INTEGER', $statement);
            $statement = preg_replace('/\bvarchar\(\d+\)/i', 'TEXT', $statement);
            $statement = preg_replace('/\btext/i', 'TEXT', $statement);
            $statement = preg_replace('/\btinyint\(\d+\)/i', 'INTEGER', $statement);
            $statement = preg_replace('/\btimestamp/i', 'TEXT', $statement);
            $statement = preg_replace('/\bdatetime/i', 'TEXT', $statement);

            // Remove ENGINE and CHARSET specifications
            $statement = preg_replace('/ENGINE=\w+/', '', $statement);
            $statement = preg_replace('/DEFAULT CHARSET=\w+/', '', $statement);
            $statement = preg_replace('/COLLATE=\w+/', '', $statement);

            // Convert ENUM to TEXT
            $statement = preg_replace_callback('/ENUM\((.*?)\)/', function($matches) {
                return 'TEXT';
            }, $statement);

            // Remove trailing commas before closing parentheses
            $statement = preg_replace('/,(\s*\)\s*$)/m', '$1', $statement);
        }

        // Convert ALTER TABLE statements
        if (preg_match('/ALTER TABLE/', $statement)) {
            // Convert AUTO_INCREMENT to AUTOINCREMENT
            $statement = str_replace('AUTO_INCREMENT', 'AUTOINCREMENT', $statement);

            // Convert TINYINT(1) to INTEGER
            $statement = preg_replace('/TINYINT\(1\)/', 'INTEGER', $statement);
        }

        $convertedStatements[] = trim($statement);
    }

    return implode(";\n", $convertedStatements) . ";";
}

try {
    // Create SQLite database
    $db = new PDO('sqlite:sslg_voting.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Read and convert main schema
    $schemaSQL = file_get_contents('sql/schema.sql');
    $sqliteSchema = convertMySQLToSQLite($schemaSQL);

    // Execute schema
    $statements = array_filter(array_map('trim', explode(';', $sqliteSchema)));
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            $db->exec($statement);
        }
    }

    // Apply upgrades
    $upgrades = [
        'add_token_validated_column.sql',
        'add_track_column.sql',
        'V2_upgrade.sql'
    ];

    foreach ($upgrades as $upgradeFile) {
        if (file_exists($upgradeFile)) {
            $upgradeSQL = file_get_contents($upgradeFile);
            $sqliteUpgrade = convertMySQLToSQLite($upgradeSQL);
            $statements = array_filter(array_map('trim', explode(';', $sqliteUpgrade)));
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $db->exec($statement);
                }
            }
            echo "Applied $upgradeFile\n";
        }
    }

    echo "SQLite database 'sslg_voting.db' created successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
