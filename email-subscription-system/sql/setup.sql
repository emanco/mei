-- Email Subscription System Database Setup
-- Run this script to create the database and tables

-- Create database
CREATE DATABASE IF NOT EXISTS email_subscription
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE email_subscription;

-- Main subscribers table (validated, clean emails)
CREATE TABLE IF NOT EXISTS subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    subscribed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    status ENUM('active', 'unsubscribed') DEFAULT 'active',
    unsubscribe_token VARCHAR(64) UNIQUE,
    validation_score TINYINT DEFAULT 0,
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_subscribed_at (subscribed_at),
    INDEX idx_unsubscribe_token (unsubscribe_token)
) ENGINE=InnoDB;

-- Rejected emails table (for review and analysis)
CREATE TABLE IF NOT EXISTS rejected_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    rejection_reason VARCHAR(100),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    INDEX idx_submitted_at (submitted_at),
    INDEX idx_rejection_reason (rejection_reason)
) ENGINE=InnoDB;

-- Email templates table (for future campaigns)
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    html_content TEXT,
    text_content TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
) ENGINE=InnoDB;

-- Admin users table (simple authentication)
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL
) ENGINE=InnoDB;

-- Insert default admin user (username: admin, password: admin123)
-- In production, change this password immediately!
INSERT IGNORE INTO admin_users (username, password_hash)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Create default email template
INSERT IGNORE INTO email_templates (name, subject, html_content, text_content)
VALUES (
    'Welcome Email',
    'Welcome to our newsletter!',
    '<h1>Welcome!</h1><p>Thank you for subscribing to our newsletter.</p><p>You will receive updates about our latest news and offers.</p>',
    'Welcome!\n\nThank you for subscribing to our newsletter.\n\nYou will receive updates about our latest news and offers.'
);

-- Show tables created
SHOW TABLES;