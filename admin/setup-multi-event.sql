-- ============================================================
-- Multi-Event Support Migration
-- Adds events table and links all data to specific events
-- ============================================================

-- 1. Create events table
CREATE TABLE IF NOT EXISTS events (
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,
    slug            VARCHAR(100) UNIQUE NOT NULL,
    ceremony_type   VARCHAR(50) DEFAULT 'Wedding',
    couple_name_1   VARCHAR(100),
    couple_name_2   VARCHAR(100),
    event_date      DATE,
    is_active       BOOLEAN DEFAULT FALSE,
    is_published    BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Add event_id to existing tables
ALTER TABLE guests ADD COLUMN IF NOT EXISTS event_id INTEGER REFERENCES events(id) ON DELETE CASCADE;
ALTER TABLE rsvp_responses ADD COLUMN IF NOT EXISTS event_id INTEGER REFERENCES events(id) ON DELETE CASCADE;
ALTER TABLE time_capsule ADD COLUMN IF NOT EXISTS event_id INTEGER REFERENCES events(id) ON DELETE CASCADE;
ALTER TABLE gallery ADD COLUMN IF NOT EXISTS event_id INTEGER REFERENCES events(id) ON DELETE CASCADE;
ALTER TABLE map_pins ADD COLUMN IF NOT EXISTS event_id INTEGER REFERENCES events(id) ON DELETE CASCADE;

-- 3. Create event_settings table (replaces global settings)
CREATE TABLE IF NOT EXISTS event_settings (
    id              SERIAL PRIMARY KEY,
    event_id        INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    setting_key     VARCHAR(100) NOT NULL,
    setting_value   TEXT,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(event_id, setting_key)
);

-- 4. Migrate existing settings to first event
DO $$
DECLARE
    first_event_id INTEGER;
    setting_record RECORD;
BEGIN
    -- Only run if events table is empty
    IF NOT EXISTS (SELECT 1 FROM events) THEN
        -- Create first event from existing settings
        INSERT INTO events (name, slug, ceremony_type, couple_name_1, couple_name_2, event_date, is_active, is_published)
        SELECT 
            COALESCE((SELECT setting_value FROM settings WHERE setting_key = 'couple_name_1'), 'Event') || ' & ' ||
            COALESCE((SELECT setting_value FROM settings WHERE setting_key = 'couple_name_2'), 'Partner'),
            'event-1',
            COALESCE((SELECT setting_value FROM settings WHERE setting_key = 'ceremony_type'), 'Wedding'),
            (SELECT setting_value FROM settings WHERE setting_key = 'couple_name_1'),
            (SELECT setting_value FROM settings WHERE setting_key = 'couple_name_2'),
            (SELECT setting_value::date FROM settings WHERE setting_key = 'event_date'),
            TRUE,
            TRUE
        RETURNING id INTO first_event_id;

        -- Migrate all settings to event_settings
        FOR setting_record IN SELECT * FROM settings LOOP
            INSERT INTO event_settings (event_id, setting_key, setting_value)
            VALUES (first_event_id, setting_record.setting_key, setting_record.setting_value)
            ON CONFLICT (event_id, setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value;
        END LOOP;

        -- Update slug to be unique
        UPDATE events SET slug = 'event-' || first_event_id WHERE id = first_event_id;

        -- Migrate existing data to link to first event
        UPDATE guests SET event_id = first_event_id WHERE event_id IS NULL;
        UPDATE rsvp_responses SET event_id = first_event_id WHERE event_id IS NULL;
        UPDATE time_capsule SET event_id = first_event_id WHERE event_id IS NULL;
        UPDATE gallery SET event_id = first_event_id WHERE event_id IS NULL;
        UPDATE map_pins SET event_id = first_event_id WHERE event_id IS NULL;
    END IF;
END $$;

-- 5. Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_guests_event ON guests(event_id);
CREATE INDEX IF NOT EXISTS idx_rsvp_event ON rsvp_responses(event_id);
CREATE INDEX IF NOT EXISTS idx_gallery_event ON gallery(event_id);
CREATE INDEX IF NOT EXISTS idx_event_settings_event ON event_settings(event_id);

-- 6. Create function to get settings for active/current event
CREATE OR REPLACE FUNCTION get_event_settings(p_event_id INTEGER)
RETURNS TABLE(setting_key VARCHAR, setting_value TEXT) AS $$
BEGIN
    RETURN QUERY
    SELECT es.setting_key, es.setting_value
    FROM event_settings es
    WHERE es.event_id = p_event_id;
END;
$$ LANGUAGE plpgsql;
