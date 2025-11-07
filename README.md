# Pulse

A personal website uptime monitoring app built with Laravel & Vue.js. Keep track of your websites, get notified when they go down, and monitor performance—all from a clean, simple dashboard.

---

## 🚀 Quick Start

### Production Deployment

For production deployments, use the included `deploy.sh` script:

```bash
# Make it executable (first time only)
chmod +x deploy.sh

# Run deployment (handles both fresh installs and updates)
./deploy.sh
```

**What the script does:**

- **Environment Setup**: Creates `.env` from `.env.example` if missing
- **Interactive Prompts**: Prompts for `APP_URL` only if missing
- **Git Updates**: Automatically pulls latest changes if in a git repository
- **Dependencies**: Installs production dependencies (`composer install --no-dev`, `npm ci`)
- **Build**: Compiles frontend assets (`npm run build`)
- **Database**: Generates app key, creates SQLite database, and runs migrations
- **Optimization**: Clears caches and optimizes the application for production

**Usage:**

- **Fresh Install**: Run `./deploy.sh` on a new installation
- **Update**: Run `./deploy.sh` to pull latest changes and redeploy

The script is idempotent—safe to run multiple times. It only prompts for missing environment variables and skips steps that are already complete.

### Docker Deployment

```bash
git clone https://github.com/alecscodes/pulse.git
cd pulse
cp .env.example .env
```

**Configure required environment variables in `.env`:**

- `APP_URL` (required) - The full URL of your application (e.g., `http://localhost:8000` or `https://pulse.example.com`)
- `APP_PORT` (optional) - Port to expose the application on (default: `8000`)

Then start the containers:

```bash
docker-compose up -d
```

### Local Development

```bash
# Clone & install
git clone https://github.com/alecscodes/pulse.git
cd pulse
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# Build & run
npm run build
composer run dev
```

Visit `http://localhost:8000` 🎉

---

## ✨ Features

- **Multi-site Monitoring** – Track unlimited websites with custom check intervals
- **HTTP/HTTPS Support** – Monitor GET/POST requests with custom headers & params
- **Content Validation** – Verify expected content in responses
- **Telegram Notifications** – Instant alerts when sites go down or recover
- **Dashboard & Analytics** – Track uptime stats and response times
- **Two-Factor Auth** – Secure your account with 2FA
- **Dark Mode** – Beautiful UI with light/dark themes
- **Mobile-First** – Fully responsive design

---

## 🛠 Tech Stack

**Backend**: Laravel 12 · PHP 8.4+  
**Frontend**: Vue 3 · Inertia.js v2 · Tailwind CSS v4  
**Database**: SQLite (MySQL/PostgreSQL supported)  
**Testing**: Pest PHP v4  
**Deploy**: Docker & Docker Compose

---

## ⚙️ Configuration

### 📢 Telegram Notifications

1. Create a bot via [@BotFather](https://t.me/BotFather)
2. Get your bot token and chat ID
3. Go to **Settings → Monitoring** in the app
4. Enter credentials and save

### 👥 Registration Control

- **Fresh install**: Registration is auto-enabled for first user
- **After setup**: Auto-disabled for security
- **Manual control**: Enable/disable in **Settings → Registration**

### 🔒 IP Banning System

The application includes an automatic IP banning system to protect against attackers and malicious requests.

**Automatic Banning:**

- **Failed Login Attempts**: IPs are permanently banned after **2 failed login attempts**
- **Non-existent Routes**: IPs accessing non-existent routes (e.g., `/wordpress`, `/wp-admin`) are permanently banned
- **Multi-IP Detection**: Detects and bans all related IPs including:
  - Client IP
  - Forwarded IPs (X-Forwarded-For)
  - Proxy/VPN IPs (CF-Connecting-IP, X-Real-Ip, etc.)
  - Server IPs

**Unbanning IPs:**

Unban a specific IP:

```bash
php artisan ip:unban 192.168.1.100
```

Unban all IPs:

```bash
php artisan ip:unban --all
```

**Note**: Banned IPs are stored in the database and cached for performance. Unbanning clears both the database and cache entries.

---

## 📖 Usage

### Adding a Monitor

1. Navigate to **Monitors → Create Monitor**
2. Enter URL, name, and check interval
3. Optionally add headers, params, or content validation
4. Hit **Create** ✅

### Viewing Status

- **Dashboard**: See all monitors at a glance
- **Details**: Click any monitor for history, downtime records, and response times

Monitors run automatically every minute via Laravel scheduler.

---

## 🧪 Development

```bash
# Run tests
php artisan test

# Code formatting
vendor/bin/pint

# Static analysis (Larastan)
composer run analyze

# Frontend dev mode
npm run dev
```

---

## 📄 License

MIT License - feel free to use, modify, and distribute.

---

## ⚠️ Disclaimer

This software is provided **"as is"** without warranty. Use at your own risk. Not responsible for missed alerts, downtime detection failures, or any damages. Always maintain backup monitoring systems for critical services.

---

## 💬 Support

Questions or issues? Check the [issue tracker](https://github.com/alecscodes/pulse/issues) or open a new issue.

**Happy monitoring!** 🚀
