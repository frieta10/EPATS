# EPATS — E-Invitation Platform

> **EPATS** is a digital wedding/event invitation platform (E-Platform for Event E-Cards). It generates elegant, customizable e-invitations with RSVP management, time capsule wishes, guest maps, and QR code check-ins.

---

## Project Overview

A PHP-based web application that creates beautiful, interactive digital invitations. Supports both local development (Laragon) and serverless deployment (Vercel).

### Key Features

- **Customizable Invitation Pages** — Couple names, event details, photos, colors
- **RSVP System** — Guest responses with +1 support, dietary requirements
- **QR Code Check-in** — Each guest gets a unique QR code for event entry
- **Time Capsule Wishes** — Sealed messages revealed on the event date
- **Guest Journey Map** — Interactive map showing where guests are traveling from
- **Guest Garden** — Animated visualization of confirmed attendees
- **Admin Dashboard** — Manage guests, RSVPs, settings, and view analytics

---

## Technology Stack

| Layer | Technology |
|-------|------------|
| **Backend** | PHP 8.x (vanilla, no framework) |
| **Database** | PostgreSQL (Neon.tech recommended for Vercel) |
| **Frontend** | Vanilla JS, GSAP animations, Chart.js |
| **Maps** | Leaflet.js |
| **QR Codes** | QRCode.js |
| **Image Hosting** | Cloudinary (Vercel) / Local filesystem (development) |
| **Deployment** | Vercel (serverless) or Laragon (local) |

---

## Directory Structure

```
EPATS/
├── index.php              # Public invitation page (main entry)
├── guest-portal.php       # Personal guest portal (QR code, time capsule)
├── setup.php              # One-time database setup script
├── config.php             # Core configuration, DB connection, helpers
├── database.sql           # PostgreSQL schema + default data
├── vercel.json            # Vercel deployment configuration
├── .env.example           # Environment variables template
│
├── admin/                 # Admin portal
│   ├── index.php          # Dashboard with stats & charts
│   ├── login.php          # Admin authentication
│   ├── logout.php         # Session logout
│   ├── settings.php       # Invitation customization
│   ├── guests.php         # Guest list management
│   ├── rsvp.php           # RSVP responses view
│   ├── wishes.php         # Time capsule wishes
│   ├── gallery.php        # Photo gallery management
│   └── partials/          # Reusable admin components
│       ├── sidebar.php
│       └── topbar.php
│
├── api/                   # AJAX endpoints
│   ├── rsvp.php           # POST endpoint for RSVP submissions
│   └── map-pins.php       # GET endpoint for guest map data
│
├── includes/              # Shared PHP libraries
│   ├── cloudinary.php     # Cloudinary upload helper
│   └── db_session.php     # Database-backed session handler
│
├── assets/                # Static assets
│   ├── css/
│   │   ├── main.css       # Invitation page styles
│   │   ├── admin.css      # Admin portal styles
│   │   └── portal.css     # Guest portal styles
│   ├── js/
│   │   ├── invitation.js  # Main invitation interactions
│   │   ├── portal.js      # Guest portal functionality
│   │   ├── admin.js       # Admin UI interactions
│   │   └── particles-mini.js  # Background animation
│   ├── images/            # Static images
│   └── fonts/             # Custom fonts (if any)
│
└── uploads/               # Local file uploads (development only)
```

---

## Database Schema (PostgreSQL)

### Core Tables

| Table | Purpose |
|-------|---------|
| `settings` | Key-value store for all invitation customization |
| `admin_users` | Admin authentication (single user: admin) |
| `guests` | Pre-registered guest list with invite tokens |
| `rsvp_responses` | All RSVP submissions |
| `time_capsule` | Sealed guest wishes |
| `gallery` | Uploaded photos metadata |
| `map_pins` | Guest location data for the journey map |
| `php_sessions` | Database-backed sessions (required for Vercel) |

### Key Settings (stored in `settings` table)

- `ceremony_type` — Event type (Wedding, Engagement, etc.)
- `couple_name_1`, `couple_name_2` — Couple names
- `event_date`, `event_time`, `event_timezone` — Event timing
- `venue_name`, `venue_address` — Location details
- `cover_photo`, `couple_photo` — Image URLs
- `color_bg`, `color_accent`, `color_text` — Theme colors
- `show_map`, `show_time_capsule`, `show_guest_garden` — Feature toggles

---

## Environment Variables

Copy `.env.example` to `.env` for local development. Set these in Vercel Dashboard for production:

```bash
# Database (PostgreSQL)
DB_HOST=ep-xxx.us-east-2.aws.neon.tech
DB_PORT=5432
DB_USER=your_db_user
DB_PASS=your_db_password
DB_NAME=neondb
DB_SSLMODE=require

# Or use full URL (Vercel Postgres auto-injects this)
POSTGRES_URL_NON_POOLING=postgresql://...

# Image Uploads (Cloudinary - REQUIRED on Vercel)
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret

# Optional custom domain
APP_URL=https://yourdomain.com
```

---

## Setup Instructions

### Local Development (Laragon)

1. **Database Setup**
   - Create PostgreSQL database named `e_invitation`
   - Update `.env` with your DB credentials

2. **Run Setup**
   ```
   Visit: http://localhost/EPATS/setup.php
   ```
   - Creates all tables
   - Sets default admin password: `admin123`
   - Creates `uploads/` directory

3. **Access**
   - Public: http://localhost/EPATS/
   - Admin: http://localhost/EPATS/admin/ (admin / admin123)

### Vercel Deployment

1. **Create Project**
   ```bash
   vercel
   ```

2. **Add Environment Variables**
   - In Vercel Dashboard → Project → Settings → Environment Variables
   - Add all variables from `.env.example`

3. **Database Setup**
   - Create free PostgreSQL at https://neon.tech
   - Connect to Vercel or manually add connection vars

4. **Run Setup**
   ```
   Visit: https://your-project.vercel.app/setup.php
   ```

5. **Security**
   - Delete or rename `setup.php` after successful setup
   - Change admin password immediately

---

## Architecture Details

### Session Management

Vercel is serverless (no shared filesystem), so sessions are stored in PostgreSQL via `DbSessionHandler` class (`includes/db_session.php`). Local development falls back to native file-based sessions if DB unavailable.

### Image Uploads

| Environment | Storage | Method |
|-------------|---------|--------|
| Local (Laragon) | `uploads/` directory | `move_uploaded_file()` |
| Vercel | Cloudinary | Signed API upload |

The `handleUpload()` function in `config.php` automatically detects environment and routes accordingly.

### Database Connection

`getDB()` in `config.php` returns a singleton PDO instance. Supports:
- Full connection URL (`POSTGRES_URL_NON_POOLING`)
- Individual connection parameters
- SSL mode for production

### Routing (Vercel)

`vercel.json` defines build configs and routes:
- Static assets served directly
- PHP files processed by `vercel-php@0.7.2`
- Pretty URLs: `/admin/` → `/admin/index.php`

---

## Code Conventions

### PHP Style

- Use `require_once __DIR__ . '/path'` for includes
- Always use prepared statements for database queries
- Escape output with `e()` helper: `<?= e($variable) ?>`
- Use `getSettings()` for configuration values
- Admin pages must call `requireAdmin()` at top

### Security Practices

```php
// Input validation
$token = preg_replace('/[^a-f0-9]/', '', $_GET['token'] ?? '');

// SQL injection prevention
$stmt = getDB()->prepare('SELECT * FROM table WHERE id = ?');
$stmt->execute([$id]);

// XSS prevention
echo e($userInput);  // htmlspecialchars wrapper

// CSRF not implemented (low risk for this use case)
```

### JavaScript Conventions

- Global `BASE_URL` injected by PHP for AJAX calls
- Use `fetch()` for API calls
- GSAP for animations, ScrollTrigger for scroll effects

---

## Key Files Reference

### Core Configuration
- `config.php` — DB connection, helpers, upload handling
- `database.sql` — Full schema with defaults

### Public Pages
- `index.php` — Main invitation (375 lines, self-contained)
- `guest-portal.php` — Personal portal with QR/time capsule

### Admin Pages
- `admin/index.php` — Dashboard with Chart.js visualizations
- `admin/settings.php` — Tabbed settings form
- `admin/guests.php` — CRUD for guest list
- `admin/rsvp.php` — RSVP management with export

### API Endpoints
- `api/rsvp.php` — POST only, handles form submission
- `api/map-pins.php` — GET, returns JSON for map markers

---

## Testing

No automated test suite. Manual testing checklist:

1. **Setup Flow**
   - Fresh database → run setup.php → verify tables created
   - Check admin login works

2. **Invitation Flow**
   - Customize settings → verify changes on public page
   - Upload photos → verify display

3. **RSVP Flow**
   - Submit RSVP → verify appears in admin
   - Check QR code generates correctly
   - Verify guest portal loads with token

4. **Time Capsule**
   - Submit wish → verify locked until unlock date
   - Change unlock date → verify reveals

---

## Security Considerations

### Immediate Actions Required

1. **Delete setup.php** after initial setup
2. **Change default password** (admin123)
3. **Secure .env file** — never commit to git
4. **Database** — use strong password, restrict IP access

### Known Limitations

- No CSRF tokens (acceptable for RSVP public forms)
- No rate limiting on login (add if under attack)
- File uploads limited by extension check but not content validation

---

## Troubleshooting

### Database Connection Errors

```
Check:
1. .env variables correct
2. Database exists and accessible
3. SSL mode matches provider (Neon requires 'require')
4. Vercel: check Environment Variables in dashboard
```

### Upload Failures on Vercel

```
Cloudinary required for Vercel. Check:
1. CLOUDINARY_* variables set
2. Cloudinary account has available quota
3. File under 10MB, valid image type
```

### Session Issues

```
If logged out unexpectedly:
1. Check php_sessions table exists
2. Verify DB connection stable
3. Clear browser cookies
```

---

## License & Credits

Built for creating memorable digital wedding invitations. Free to use and modify.

Third-party libraries:
- GSAP (Animation)
- Chart.js (Admin charts)
- Leaflet.js (Maps)
- QRCode.js (QR generation)
- Font Awesome (Icons)
