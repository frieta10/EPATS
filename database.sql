-- ============================================================
-- E-Invitation Platform — PostgreSQL Schema
-- Compatible with: Neon.tech, Supabase, Railway, any Postgres
-- ============================================================

-- Settings table (all invitation customisation)
CREATE TABLE IF NOT EXISTS settings (
    id          SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admin users
CREATE TABLE IF NOT EXISTS admin_users (
    id            SERIAL PRIMARY KEY,
    username      VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Guest list (admin-created personalised invites)
CREATE TABLE IF NOT EXISTS guests (
    id           SERIAL PRIMARY KEY,
    name         VARCHAR(200) NOT NULL,
    email        VARCHAR(200),
    phone        VARCHAR(50),
    invite_token VARCHAR(64) UNIQUE NOT NULL,
    table_number VARCHAR(20),
    notes        TEXT,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- RSVP Responses
CREATE TABLE IF NOT EXISTS rsvp_responses (
    id                   SERIAL PRIMARY KEY,
    guest_id             INTEGER NULL REFERENCES guests(id) ON DELETE SET NULL,
    name                 VARCHAR(200) NOT NULL,
    email                VARCHAR(200),
    phone                VARCHAR(50),
    attending            VARCHAR(10) NOT NULL DEFAULT 'yes' CHECK (attending IN ('yes','no','maybe')),
    plus_one             BOOLEAN DEFAULT FALSE,
    plus_one_name        VARCHAR(200),
    dietary_requirements TEXT,
    message              TEXT,
    city                 VARCHAR(100),
    country              VARCHAR(100),
    lat                  DECIMAL(10, 8),
    lng                  DECIMAL(11, 8),
    qr_token             VARCHAR(64) UNIQUE,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Time Capsule Wishes (unique feature)
CREATE TABLE IF NOT EXISTS time_capsule (
    id          SERIAL PRIMARY KEY,
    rsvp_id     INTEGER REFERENCES rsvp_responses(id) ON DELETE CASCADE,
    guest_name  VARCHAR(200) NOT NULL,
    message     TEXT NOT NULL,
    photo_path  VARCHAR(255),
    is_revealed BOOLEAN DEFAULT FALSE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Photo Gallery
CREATE TABLE IF NOT EXISTS gallery (
    id              SERIAL PRIMARY KEY,
    filename        VARCHAR(255) NOT NULL,
    original_name   VARCHAR(255),
    caption         TEXT,
    is_cover        BOOLEAN DEFAULT FALSE,
    is_couple_photo BOOLEAN DEFAULT FALSE,
    display_order   INTEGER DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Guest Map Pins (for the "Journey Map" unique feature)
CREATE TABLE IF NOT EXISTS map_pins (
    id         SERIAL PRIMARY KEY,
    rsvp_id    INTEGER REFERENCES rsvp_responses(id) ON DELETE CASCADE,
    guest_name VARCHAR(200),
    city       VARCHAR(100),
    country    VARCHAR(100),
    lat        DECIMAL(10, 8),
    lng        DECIMAL(11, 8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- PHP Sessions table (required for Vercel / serverless)
CREATE TABLE IF NOT EXISTS php_sessions (
    session_id   VARCHAR(128) PRIMARY KEY,
    session_data TEXT        NOT NULL,
    expires_at   TIMESTAMP   NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_sessions_expires ON php_sessions (expires_at);

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
ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value;

-- ============================================================
-- Default Admin User (password is set by setup.php at runtime)
-- ============================================================
INSERT INTO admin_users (username, password_hash) VALUES
('admin', '$2y$10$placeholder.hash.will.be.replaced.by.setup.php')
ON CONFLICT (username) DO NOTHING;
