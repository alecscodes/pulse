# Pulse

A personal website uptime monitoring app built with Laravel & Vue.js. Keep track of your websites, get notified when they go down, and monitor performance—all from a clean, simple dashboard.

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

## 🚀 Quick Start

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

### Docker Deployment

```bash
git clone https://github.com/alecscodes/pulse.git
cd pulse
cp .env.example .env
docker-compose up -d
```

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
