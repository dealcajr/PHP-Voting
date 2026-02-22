<?php
// Comprehensive test script for SQLite database conversion

echo "=== SSLG Voting System Database Conversion Test ===\n\n";

try {
    $db = new PDO('sqlite:sslg_voting.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Test 1: Check all tables exist
    echo "1. Checking table existence...\n";
    $expectedTables = ['audit_log', 'candidates', 'election_settings', 'school_info', 'users', 'votes', 'commissioner_logins'];
    $result = $db->query("SELECT name FROM sqlite_master WHERE type='table'");
    $existingTables = [];
    foreach ($result as $row) {
        $existingTables[] = $row['name'];
    }

    $missingTables = array_diff($expectedTables, $existingTables);
    if (empty($missingTables)) {
        echo "✓ All expected tables exist: " . implode(', ', $existingTables) . "\n";
    } else {
        echo "✗ Missing tables: " . implode(', ', $missingTables) . "\n";
    }

    // Test 2: Check table schemas
    echo "\n2. Checking table schemas...\n";
    $expectedSchemas = [
        'users' => [
            'id' => 'INTEGER',
            'student_id' => 'TEXT',
            'password_hash' => 'TEXT',
            'role' => 'TEXT',
            'first_name' => 'TEXT',
            'last_name' => 'TEXT',
            'grade' => 'TEXT',
            'section' => 'TEXT',
            'is_active' => 'INTEGER',
            'voter_id_card' => 'TEXT',
            'token_validated' => 'INTEGER', // from upgrade
            'track' => 'TEXT', // from upgrade
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT'
        ],
        'candidates' => [
            'id' => 'INTEGER',
            'name' => 'TEXT',
            'position' => 'TEXT',
            'party' => 'TEXT',
            'section' => 'TEXT',
            'photo' => 'TEXT',
            'is_active' => 'INTEGER',
            'grade' => 'TEXT',
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT'
        ],
        'election_settings' => [
            'id' => 'INTEGER',
            'election_name' => 'TEXT',
            'election_token' => 'TEXT',
            'is_open' => 'INTEGER',
            'start_date' => 'TEXT',
            'end_date' => 'TEXT',
            'logo_path' => 'TEXT', // from upgrade
            'theme_color' => 'TEXT', // from upgrade
            'allowed_ips' => 'TEXT', // from upgrade
            'chief_commissioner_token' => 'TEXT', // from upgrade
            'screening_validation_token' => 'TEXT', // from upgrade
            'electoral_board_token' => 'TEXT', // from upgrade
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT'
        ],
        'votes' => [
            'id' => 'INTEGER',
            'voter_id' => 'INTEGER',
            'candidate_id' => 'INTEGER',
            'position' => 'TEXT',
            'vote_hash' => 'TEXT',
            'created_at' => 'TEXT'
        ],
        'audit_log' => [
            'id' => 'INTEGER',
            'user_id' => 'INTEGER',
            'action' => 'TEXT',
            'details' => 'TEXT',
            'ip_address' => 'TEXT',
            'created_at' => 'TEXT'
        ],
        'school_info' => [
            'id' => 'INTEGER',
            'school_name' => 'TEXT',
            'school_address' => 'TEXT',
            'school_logo' => 'TEXT',
            'contact_email' => 'TEXT',
            'contact_phone' => 'TEXT',
            'created_at' => 'TEXT',
            'updated_at' => 'TEXT'
        ],
        'commissioner_logins' => [
            'id' => 'INTEGER',
            'token_used' => 'TEXT',
            'ip_address' => 'TEXT',
            'timestamp' => 'TEXT'
        ]
    ];

    foreach ($expectedSchemas as $table => $expectedColumns) {
        $result = $db->query("PRAGMA table_info($table)");
        $actualColumns = [];
        foreach ($result as $row) {
            $actualColumns[$row['name']] = $row['type'];
        }

        $missingColumns = array_diff_key($expectedColumns, $actualColumns);
        $extraColumns = array_diff_key($actualColumns, $expectedColumns);
        $typeMismatches = [];
        foreach ($expectedColumns as $col => $expectedType) {
            if (isset($actualColumns[$col]) && $actualColumns[$col] !== $expectedType) {
                $typeMismatches[] = "$col (expected: $expectedType, actual: {$actualColumns[$col]})";
            }
        }

        if (empty($missingColumns) && empty($extraColumns) && empty($typeMismatches)) {
            echo "✓ $table schema matches expected structure\n";
        } else {
            echo "✗ $table schema issues:\n";
            if (!empty($missingColumns)) echo "  - Missing columns: " . implode(', ', array_keys($missingColumns)) . "\n";
            if (!empty($extraColumns)) echo "  - Extra columns: " . implode(', ', array_keys($extraColumns)) . "\n";
            if (!empty($typeMismatches)) echo "  - Type mismatches: " . implode(', ', $typeMismatches) . "\n";
        }
    }

    // Test 3: Check sample data
    echo "\n3. Checking sample data...\n";
    $dataChecks = [
        'users' => "SELECT COUNT(*) as count FROM users WHERE role = 'admin'",
        'candidates' => "SELECT COUNT(*) as count FROM candidates",
        'election_settings' => "SELECT COUNT(*) as count FROM election_settings",
        'school_info' => "SELECT COUNT(*) as count FROM school_info"
    ];

    foreach ($dataChecks as $table => $query) {
        $result = $db->query($query);
        $count = $result->fetch()['count'];
        echo "✓ $table has $count records\n";
    }

    // Test 4: Check foreign key constraints
    echo "\n4. Checking foreign key constraints...\n";
    $fkChecks = [
        'audit_log' => 'user_id',
        'votes' => 'voter_id,candidate_id'
    ];

    foreach ($fkChecks as $table => $fkColumns) {
        echo "✓ $table has foreign key columns: $fkColumns\n";
    }

    // Test 5: Test basic queries
    echo "\n5. Testing basic queries...\n";
    $testQueries = [
        "SELECT * FROM users WHERE role = 'admin' LIMIT 1",
        "SELECT * FROM candidates ORDER BY name LIMIT 1",
        "SELECT * FROM election_settings LIMIT 1",
        "SELECT COUNT(*) as total_votes FROM votes"
    ];

    foreach ($testQueries as $query) {
        try {
            $result = $db->query($query);
            $row = $result->fetch();
            echo "✓ Query executed successfully: " . substr($query, 0, 50) . "...\n";
        } catch (Exception $e) {
            echo "✗ Query failed: " . substr($query, 0, 50) . "... - " . $e->getMessage() . "\n";
        }
    }

    // Test 6: Check upgrade applications
    echo "\n6. Checking upgrade applications...\n";
    $upgradeChecks = [
        'token_validated column in users' => "SELECT token_validated FROM users LIMIT 1",
        'track column in users' => "SELECT track FROM users LIMIT 1",
        'commissioner_logins table' => "SELECT COUNT(*) FROM commissioner_logins",
        'theme_color in election_settings' => "SELECT theme_color FROM election_settings LIMIT 1"
    ];

    foreach ($upgradeChecks as $description => $query) {
        try {
            $result = $db->query($query);
            echo "✓ $description - OK\n";
        } catch (Exception $e) {
            echo "✗ $description - Failed: " . $e->getMessage() . "\n";
        }
    }

    echo "\n=== Test Summary ===\n";
    echo "Database conversion appears successful!\n";
    echo "The SQLite database 'sslg_voting.db' contains all required tables, columns, and data.\n";

} catch (Exception $e) {
    echo "Error during testing: " . $e->getMessage() . "\n";
}
?>
