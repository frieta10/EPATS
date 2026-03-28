-- E-Invitation Platform Database Schema
CREATE DATABASE IF NOT EXISTS e_invitation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE e_invitation;

-- ============================================================
-- Settings table (all invitation customization)
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- Admin users
-- ============================================================
CREATE TABLE IF NOT EXISTS admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Guest list (admin-created personalized invites)
-- ============================================================
CREATE TABLE IF NOT EXISTS guests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(200),
    phone VARCHAR(50),
    invite_token VARCHAR(64) UNIQUE NOT NULL,
    table_number VARCHAR(20),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- RSVP Responses
-- ============================================================
CREATE TABLE IF NOT EXISTS rsvp_responses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    guest_id INT NULL,
    name VARCHAR(200) NOT NULL,
    email VARCHAR(200),
    phone VARCHAR(50),
    attending ENUM('yes', 'no', 'maybe') NOT NULL DEFAULT 'yes',
    plus_one TINYINT(1) DEFAULT 0,
    plus_one_name VARCHAR(200),
    dietary_requirements TEXT,
    message TEXT,
    city VARCHAR(100),
    country VARCHAR(100),
    lat DECIMAL(10, 8),
    lng DECIMAL(11, 8),
    qr_token VARCHAR(64) UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE SET NULL
);

-- ============================================================
-- Time Capsule Wishes (unique feature)
-- ============================================================
CREATE TABLE IF NOT EXISTS time_capsule (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rsvp_id INT,
    guest_name VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    photo_path VARCHAR(255),
    is_revealed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rsvp_id) REFERENCES rsvp_responses(id) ON DELETE CASCADE
);

-- ============================================================
-- Photo Gallery
-- ============================================================
CREATE TABLE IF NOT EXISTS gallery (
    id INT PRIMARY KEY AUTO_INCREMENT,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255),
    caption TEXT,
    is_cover TINYINT(1) DEFAULT 0,
    is_couple_photo TINYINT(1) DEFAULT 0,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Guest Map Pins (for the "Journey Map" unique feature)
-- ============================================================
CREATE TABLE IF NOT EXISTS map_pins (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rsvp_id INT,
    guest_name VARCHAR(200),
    city VARCHAR(100),
    country VARCHAR(100),
    lat DECIMAL(10, 8),
    lng DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (rsvp_id) REFERENCES rsvp_responses(id) ON DELETE CASCADE
);

-- ============================================================
-- Default Settings
-- ============================================================
INSERT INTO settings (setting_key, setting_value) VALUES
('ceremony_type',        'Wedding'),
('couple_name_1',        'Jenıl'),
('couple_name_2',        'Abram'),
('couple_surname_1',     'Quayson'),
('couple_surname_2',     'Yawskovi'),
('tagline',              'Invite You To Their Holy Matrimony'),
('event_date',           '2024-09-30'),
('event_time',           '10:00 AM'),
('event_timezone',       'GMT'),
('venue_name',           'The Marriotte Hotel'),
('venue_address',        'Greater Accra, Ghana'),
('rsvp_phone_1',         '+233541707589'),
('rsvp_phone_2',         '+233555784655'),
('rsvp_deadline',        '2024-09-20'),
('time_capsule_unlock',  '2024-09-30'),
('color_bg',             '#2D0A1E'),
('color_accent',         '#C9A84C'),
('color_text',           '#F5E6D0'),
('custom_message',       'We joyfully request the honour of your presence as we celebrate our love.'),
('cover_photo',          ''),
('couple_photo',         ''),
('music_url',            ''),
('music_autoplay',       '0'),
('show_map',             '1'),
('show_time_capsule',    '1'),
('show_guest_garden',    '1'),
('site_password',        ''),
('admin_setup_done',     '0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ============================================================
-- Default Admin User (password: admin123)
-- ============================================================
INSERT INTO admin_users (username, password_hash) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON DUPLICATE KEY UPDATE username = username;
