<?php
// Create SQLite database from MySQL schema

try {
    // Delete existing database if it exists
    if (file_exists('sslg_voting.db')) {
        unlink('sslg_voting.db');
    }

    // Create SQLite database
    $db = new PDO('sqlite:sslg_voting.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create tables based on MySQL schema
    $tables = [
        'audit_log' => "CREATE TABLE audit_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            action TEXT NOT NULL,
            details TEXT,
            ip_address TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",

        'candidates' => "CREATE TABLE candidates (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            position TEXT NOT NULL,
            party TEXT,
            section TEXT,
            photo TEXT,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
            grade TEXT
        )",

        'election_settings' => "CREATE TABLE election_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            election_name TEXT NOT NULL DEFAULT 'SSLG Election',
            election_token TEXT,
            is_open INTEGER NOT NULL DEFAULT 0,
            start_date TEXT,
            end_date TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",

        'school_info' => "CREATE TABLE school_info (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            school_name TEXT NOT NULL DEFAULT 'Supreme Secondary Learner Government',
            school_address TEXT,
            school_logo TEXT,
            contact_email TEXT,
            contact_phone TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",

        'users' => "CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'voter',
            first_name TEXT,
            last_name TEXT,
            grade TEXT,
            section TEXT,
            is_active INTEGER NOT NULL DEFAULT 1,
            voter_id_card TEXT UNIQUE,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT DEFAULT CURRENT_TIMESTAMP
        )",

        'votes' => "CREATE TABLE votes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            voter_id INTEGER NOT NULL,
            candidate_id INTEGER NOT NULL,
            position TEXT NOT NULL,
            vote_hash TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (voter_id) REFERENCES users (id),
            FOREIGN KEY (candidate_id) REFERENCES candidates (id)
        )"
    ];

    // Create tables
    foreach ($tables as $tableName => $createSQL) {
        $db->exec($createSQL);
        echo "Created table: $tableName\n";
    }

    // Insert sample data
    $sampleData = [
        'election_settings' => "INSERT INTO election_settings (election_name, is_open) VALUES ('SSLG Election 2024', 0)",
        'school_info' => "INSERT INTO school_info (school_name, school_address, contact_email) VALUES ('Supreme Secondary Learner Government', 'School Address Here', 'admin@sslg.edu')",
        'users' => [
            "INSERT INTO users (student_id, password_hash, role, first_name, last_name) VALUES ('ADMIN001', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System', 'Administrator')",
            "INSERT INTO users (student_id, password_hash, role, first_name, last_name, grade, section) VALUES ('STU001', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'voter', 'John', 'Doe', '12', 'A')",
            "INSERT INTO users (student_id, password_hash, role, first_name, last_name, grade, section) VALUES ('STU002', '\$2y\$10\$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'voter', 'Jane', 'Smith', '12', 'B')"
        ],
        'candidates' => [
            "INSERT INTO candidates (name, position, party, section, grade) VALUES ('John Doe', 'President', 'Party A', 'Grade 12-A', '12')",
            "INSERT INTO candidates (name, position, party, section, grade) VALUES ('Jane Smith', 'Vice President', 'Party B', 'Grade 12-B', '12')",
            "INSERT INTO candidates (name, position, party, section, grade) VALUES ('Bob Johnson', 'Secretary', 'Party A', 'Grade 11-A', '11')"
        ]
    ];

    // Insert sample data
    foreach ($sampleData as $table => $data) {
        if (is_array($data)) {
            foreach ($data as $insertSQL) {
                $db->exec($insertSQL);
            }
        } else {
            $db->exec($data);
        }
        echo "Inserted sample data into: $table\n";
    }

    // Apply upgrades
    $upgrades = [
        'ALTER TABLE users ADD COLUMN token_validated INTEGER DEFAULT 0',
        'ALTER TABLE users ADD COLUMN track TEXT',
        'ALTER TABLE election_settings ADD COLUMN logo_path TEXT',
        'ALTER TABLE election_settings ADD COLUMN theme_color TEXT DEFAULT \'#343a40\'',
        'ALTER TABLE election_settings ADD COLUMN allowed_ips TEXT',
        'ALTER TABLE election_settings ADD COLUMN chief_commissioner_token TEXT',
        'ALTER TABLE election_settings ADD COLUMN screening_validation_token TEXT',
        'ALTER TABLE election_settings ADD COLUMN electoral_board_token TEXT',
        'CREATE TABLE commissioner_logins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            token_used TEXT NOT NULL,
            ip_address TEXT NOT NULL,
            timestamp TEXT DEFAULT CURRENT_TIMESTAMP
        )'
    ];

    foreach ($upgrades as $upgradeSQL) {
        $db->exec($upgradeSQL);
        echo "Applied upgrade: " . substr($upgradeSQL, 0, 50) . "...\n";
    }

    echo "\nSQLite database 'sslg_voting.db' created successfully!\n";
    echo "Database contains all tables and sample data from the MySQL schema.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
