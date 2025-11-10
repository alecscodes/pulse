# 🫀 Pulse

> **Website uptime monitoring app** built with Laravel & Vue.js  
> Track websites, get notified when they go down, and monitor performance metrics.

---

## 📋 Table of Contents

- [Quick Start](#-quick-start)
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Configuration](#️-configuration)
- [Usage](#-usage)
- [Development](#-development)
- [Artisan Commands](#-artisan-commands)
- [License](#-license)
- [Support](#-support)

---

## 🚀 Quick Start

### 🐳 Docker (Recommended)

The easiest way to get started with Pulse:

```bash
git clone https://github.com/alecscodes/pulse.git
cd pulse
cp .env.example .env
# Set APP_URL in .env
docker-compose up -d
```

### 🏭 Production Deployment

For production environments:

```bash
git clone https://github.com/alecscodes/pulse.git
cd pulse
./deploy.sh
```

The `deploy.sh` script automatically sets up cron jobs for:

- Laravel scheduler (runs every minute)
- Queue worker auto-start (checks every minute, starts if not running)

**Note:** The queue worker must be running for down monitors to be checked every 3 seconds.

### 💻 Local Development

For local development without Docker:

```bash
git clone https://github.com/alecscodes/pulse.git
cd pulse
composer install && npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite && php artisan migrate
npm run build && composer run dev
```

Visit `http://localhost:8000` to access the application.

---

## ✨ Features

- 🔄 **Multi-site monitoring** with custom check intervals
- 🌐 **HTTP/HTTPS support** with custom headers & query parameters
- ✅ **Content validation** to ensure your site returns expected content
- 📱 **Telegram notifications** for instant alerts when sites go down
- 📊 **Dashboard & analytics** to track uptime and performance
- 🔐 **Two-factor authentication** for enhanced security
- 🌙 **Dark mode** for comfortable monitoring
- 📱 **Mobile-first responsive design** - monitor from anywhere

---

## 🛠 Tech Stack

| Category | Technology |
|----------|-----------|
| **Backend** | Laravel 12 · PHP 8.4+ |
| **Frontend** | Vue 3 · Inertia.js v2 · Tailwind CSS v4 |
| **Database** | SQLite (MySQL/PostgreSQL supported) |
| **Testing** | Pest PHP v4 |

---

## ⚙️ Configuration

### 📱 Telegram Notifications

Set up Telegram notifications to receive instant alerts:

1. Create a bot via [@BotFather](https://t.me/BotFather) on Telegram
2. Get your bot token and chat ID
3. Navigate to **Settings → Monitoring** in the dashboard
4. Enter your bot credentials

### 👥 Registration Control

- Registration is **automatically enabled** for the first user
- Registration is **automatically disabled** after the first user is created
- Manual control available via **Settings → Registration**

### 🚫 IP Banning

Pulse automatically bans IPs for suspicious activity:

**Automatic bans triggered by:**

- 2 failed login attempts
- Accessing non-existent routes (e.g., `/wp-admin`)
- Detects and bans related IPs (client, forwarded, proxy, server)

**Unban commands:**

```bash
# Unban a specific IP
php artisan ip:unban 192.168.1.100

# Unban all IPs
php artisan ip:unban --all
```

### 🔄 Updates

Update Pulse directly from the dashboard notification or via command line:

```bash
php artisan git:update
```

---

## 📖 Usage

Getting started with monitoring is simple:

1. Navigate to **Monitors → Create Monitor**
2. Enter your website URL, name, and desired check interval
3. Optionally configure:
   - Custom headers
   - Query parameters
   - Content validation rules
4. Monitors run automatically every minute via the Laravel scheduler

---

## 🧪 Development

### Running Tests

```bash
php artisan test          # Run all tests
```

### Code Quality

```bash
vendor/bin/pint           # Format code with Laravel Pint
composer run analyze      # Run static analysis (PHPStan)
```

### Frontend Development

```bash
npm run dev              # Start Vite dev server with hot reload
npm run build            # Build for production
```

---

## 🔧 Artisan Commands

Pulse includes several helpful Artisan commands:

| Command | Description |
|---------|-------------|
| `php artisan git:update` | Update the application from Git repository |
| `php artisan ip:unban <ip>` | Unban a specific IP address |
| `php artisan ip:unban --all` | Unban all banned IP addresses |
| `php artisan monitors:check` | Manually trigger monitor checks |

---

## 📄 License

This project is open-sourced software licensed under the [MIT License](LICENSE).

---

## ⚠️ Disclaimer

Pulse is provided **"as is"** without warranty of any kind. For critical services, always maintain backup monitoring systems to ensure continuous uptime tracking.

---

## 💬 Support

Need help? Found a bug? Have a feature request?

- 🐛 [Report an issue](https://github.com/alecscodes/pulse/issues)
- 💡 [Request a feature](https://github.com/alecscodes/pulse/issues/new)

---

<div align="center">

**Made with ❤️ for reliable uptime monitoring**

</div>
