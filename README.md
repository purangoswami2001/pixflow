# PixFlow

**AI-powered social media scheduling and automation for Facebook, Instagram, and Pinterest.**

PixFlow is a Laravel application for teams that need to plan content, manage a shared media library, publish on a schedule, and run end-to-end AI campaigns — from topic ideas through copy, platform-sized images, and draft posts ready for review.

---

## Features

### Multi-platform publishing

- Connect **Facebook Pages**, **Instagram Business/Creator**, and **Pinterest** accounts via OAuth
- Publish to the correct destination: Facebook Page, Instagram feed, or Pinterest board
- **Queue-based publishing** with cache locking, retry logic, and per-target status tracking
- **Mock mode** (`SOCIAL_MOCK_MODE=true`) to test the full flow without live API credentials
- Admin actions: publish now, retry failed targets, cancel scheduled posts

### Post creation & scheduling

- Guided **Create Post** wizard: platform, account, media, captions, hashtags, schedule
- **One-time** and **recurring weekly** schedules
- **Content calendar** view for upcoming and past posts
- Platform-specific fields (link previews, Pinterest board, etc.)
- **AI caption suggestions** on the create-post page (OpenAI or Gemini)

### Media library

- Upload images and videos with tags, alt text, and channel associations
- **Platform presets** (Instagram portrait 4:5, Pinterest pin 2:3, Facebook link 1.91:1, and more)
- Crop and transform assets to preset dimensions
- **Lightbox** preview for full-size inspection
- AI-generated assets stored with optional Canva-style embedded metadata

### Admin scheduling

- Define **time slots per social account** (day-of-week + time)
- Assign queued posts to slots manually or let the system match them
- Per-account schedule overview and manual post assignment
- Team management: admin and creator roles (Spatie Permission)

### AI automation pipeline

Full campaign automation from the **AI Automation** admin area:

| Stage | What it does |
|-------|----------------|
| **Categories & topics** | Niche config + headline library; bulk import via AI-generated JSON |
| **Prompt Studio** | Per-platform prompt templates (idea, copy, image) with `{{image_style}}` placeholders |
| **Campaigns** | Pick platforms, image styles, presets, and topics; run on demand or on schedule |
| **Pipeline** | Idea → copy → image prompt → AI image → media asset → draft post |
| **Live monitoring** | Run detail and AI logs pages auto-refresh; retry failed items |

**Image styles** (per platform): infographic, lifestyle, minimal, quote card, quick tip card.

**AI providers:**

- **Cursor Cloud Agents** — primary automation text + agent-based image generation
- **OpenAI** — captions, copy, DALL·E images
- **Gemini** — text and image generation (strong for infographics)

Platform-native **aspect ratios** are enforced in prompts and validated after generation (Instagram 1080×1350, Pinterest 1000×1500, Facebook 1200×630, etc.).

### Operations & logging

- Application log viewer and automation log viewer (live feeds)
- Scheduled commands: publish due posts, run due automation, refresh tokens, cleanup old logs
- System health via standard Laravel logging (`storage/logs`)

---

## Tech stack

| Layer | Technology |
|-------|------------|
| Framework | Laravel 13, PHP 8.3+ |
| Frontend | Blade, Tailwind CSS, Vite, Alpine-friendly UI helpers |
| Auth | Laravel Breeze (session-based) |
| Permissions | Spatie Laravel Permission |
| Images | Intervention Image (GD) |
| Queue / DB | Database-backed queues, sessions, and cache |
| Testing | Pest |

---

## Requirements

- PHP 8.3+ with extensions: `pdo`, `mbstring`, `openssl`, `gd`, `fileinfo`
- Composer 2.x
- Node.js 18+ and npm (for Vite assets)
- MySQL/MariaDB, PostgreSQL, or SQLite
- A running **queue worker** for publishing and automation jobs

---

## Installation

### 1. Clone and install dependencies

```bash
git clone <your-repo-url> pixflow
cd pixflow

composer install
npm install
npm run build
```

### 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` — at minimum set `APP_URL`, database credentials, and any AI/social API keys you need.

**WAMP / subdirectory deploy** (e.g. `http://localhost/pixflow/public`):

```env
APP_URL=http://localhost/pixflow/public
APP_BASE_PATH=/pixflow/public
ASSET_URL="${APP_URL}"
```

Ensure `public/.htaccess` has `RewriteBase /pixflow/public` (or your folder name).

For **ngrok**, use your tunnel URL with the same path and set `SESSION_SECURE_COOKIE=true`.

### 3. Database

```bash
# MySQL: create database `pixflow` first, then:
php artisan migrate

# Optional demo users, mock accounts, sample posts:
php artisan social:create-demo-data
```

Demo logins (after `social:create-demo-data`):

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@pixflow.local` | `password` |
| User | `jane@pixflow.local` | `password` |

Seeder admin (from `db:seed`): `admin@pixflow.com` / `admin123`

### 4. Storage

```bash
php artisan storage:link
```

### 5. Run the app

**Development (all services):**

```bash
composer dev
```

**Or manually:**

```bash
php artisan serve          # http://127.0.0.1:8000 — set APP_BASE_PATH empty
php artisan queue:work     # required for publish + automation
```

---

## Background jobs & scheduler

Publishing and automation depend on the queue. Always run:

```bash
php artisan queue:work
```

Register the scheduler in cron (production):

```cron
* * * * * php /path/to/pixflow/artisan schedule:run >> /dev/null 2>&1
```

Scheduled tasks:

| Command | Interval |
|---------|----------|
| `social:publish-due` | Every minute |
| `automation:run-due` | Every 5 minutes |
| `social:refresh-tokens` | Daily |
| `social:cleanup-old-logs` | Daily |

Local manual publish check:

```bash
php artisan social:publish-due
php artisan automation:run-due
```

---

## Social API setup

Set `SOCIAL_MOCK_MODE=false` and configure OAuth apps.

### Meta (Facebook + Instagram)

1. Create an app at [Meta for Developers](https://developers.facebook.com/) with a **Page management** use case.
2. Add Facebook Login and register the callback:  
   `{APP_URL}/auth/facebook/callback`
3. Set in `.env`: `FACEBOOK_CLIENT_ID`, `FACEBOOK_CLIENT_SECRET`, `FACEBOOK_REDIRECT_URI`
4. After App Review, enable `pages_manage_posts` and set `FACEBOOK_REQUEST_PUBLISH_SCOPES=true`

### Pinterest

1. Register at [Pinterest Developers](https://developers.pinterest.com/).
2. Callback: `{APP_URL}/auth/pinterest/callback`
3. Scopes: `boards:read`, `pins:read`, `pins:write`
4. Set `PINTEREST_CLIENT_ID`, `PINTEREST_CLIENT_SECRET`, `PINTEREST_REDIRECT_URI`

---

## AI automation setup

Minimum for automation campaigns:

```env
AUTOMATION_ENABLED=true
AUTOMATION_AI_PROVIDER=cursor
CURSOR_API_KEY=your_cursor_api_key
CURSOR_IMAGE_MODE=agent
```

Optional fallbacks / enhancements:

```env
OPENAI_API_KEY=...
GEMINI_API_KEY=...
AUTOMATION_IMAGE_PROVIDER=gemini   # optional override
CURSOR_IMAGE_AGENT_POLL_TIMEOUT=480
CURSOR_IMAGE_DIMENSION_RETRIES=2
```

Workflow:

1. **Admin → AI Automation → Categories** — define your niche and topics
2. **Prompt Studio** — tune idea/copy/image prompts (or import bulk JSON)
3. **Campaigns** — select platforms, styles, and run
4. Review generated posts under **Posts**, then schedule or publish

---

## Testing

```bash
php artisan test
```

---

## License

MIT
