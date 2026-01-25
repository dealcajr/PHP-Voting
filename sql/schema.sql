-- SSLG Voting System Database Schema
-- Optimized for MySQL

-- Create database
CREATE DATABASE IF NOT EXISTS sslg_voting;
USE sslg_voting;

-- Users table (for both voters and admins)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('voter', 'admin') NOT NULL DEFAULT 'voter',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Candidates table
CREATE TABLE candidates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    position VARCHAR(50) NOT NULL,
    party VARCHAR(50),
    section VARCHAR(50),
    photo VARCHAR(255), -- Path to photo file
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Votes table (anonymous, linked by voter_id but not directly traceable)
CREATE TABLE votes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voter_id INT NOT NULL,
    candidate_id INT NOT NULL,
    position VARCHAR(50) NOT NULL,
    vote_hash VARCHAR(64) NOT NULL, -- SHA256 hash for integrity
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voter_id) REFERENCES users(id),
    FOREIGN KEY (candidate_id) REFERENCES candidates(id),
    UNIQUE KEY unique_vote (voter_id, position) -- Prevent multiple votes per position
);

-- Election settings
CREATE TABLE election_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    election_name VARCHAR(100) NOT NULL DEFAULT 'SSLG Election',
    is_open BOOLEAN NOT NULL DEFAULT FALSE,
    start_date DATETIME,
    end_date DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Audit log
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert default election settings
INSERT INTO election_settings (election_name, is_open) VALUES ('SSLG Election 2024', FALSE);

-- Insert sample admin user (password: admin123 - hashed)
INSERT INTO users (student_id, password_hash, role) VALUES ('ADMIN001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample voters
INSERT INTO users (student_id, password_hash, role) VALUES
('STU001', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'voter'),
('STU002', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'voter');

-- Insert sample candidates
INSERT INTO candidates (name, position, party, section) VALUES
('John Doe', 'President', 'Party A', 'Grade 12-A'),
('Jane Smith', 'Vice President', 'Party B', 'Grade 12-B'),
('Bob Johnson', 'Secretary', 'Party A', 'Grade 11-A');
