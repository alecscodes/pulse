# 🫀 Pulse

> **Website uptime monitoring** built with Laravel & Vue  
> Track websites and get instant notifications when they go down.
---

## 🎥 Deploy Demo

<p align="center">
  <a href="https://streamable.com/aigbjy" target="_blank">
    <img
      src="https://cdn-cf-east.streamable.com/image/aigbjy.jpg?Expires=1763553176117&Key-Pair-Id=APKAIEYUVEN4EVB2OKEQ&Signature=XhqhbWeIdJJrMLrpKOYXePrjqR4aeruj5HePbtqHi-UZeE1QZDOA8woljhzLZb-WZoL7eVfhg3xEHtNT4QaVbDaxDybIf6u4dPiFHu3J6eb32C~Ug2lW1nK9wYKtbRAcZ6jjW2rQP3fmDgO0RfCYbL1beOJlJjmvqdhDdYqa1yuC1wU9khDv~iRFZgwpwUJo04SweQaKYgEalEsety0TAwjxLTGKIk8lKlKR9RY2Vc0ng0FzvMfYmqSmMMbyd6Uj2LM5oThTVyG6l8ywXkVmV0U6AoEyWLphloEuxx-Cnp7ToMP1a1Y4OMOLRRiE0ig98vJTLdvE1tiaHA5oYenhZQ__"
      alt="Pulse Deploy Demo"
      style="width:100%;max-width:900px;border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,0.15);cursor:pointer;"
    >
  </a>
</p>

<p align="center">
  <em>Watch how to clone, configure, and deploy <b>Pulse</b> in under 2 minutes.</em><br>
  <a href="https://streamable.com/aigbjy" target="_blank">▶️ Watch on Streamable</a>
</p>

---

## 📋 Table of Contents

- [Quick Start](#-quick-start)
  - [Deploy](#-deploy)
- [Features](#-features)
- [Usage](#-usage)
  - [Content Validation Rules](#content-validation-rules)
  - [SSL Certificate Monitoring](#-ssl-certificate-monitoring)
- [Configuration](#️-configuration)
  - [Telegram Notifications](#-telegram-notifications)
  - [Registration Control](#-registration-control)
  - [IP Banning](#-ip-banning)
  - [Automatic Updates](#-automatic-updates)
- [Artisan Commands](#-artisan-commands)
- [Tech Stack](#-tech-stack)
- [Development](#-development)
  - [Running Tests](#running-tests)
  - [Code Quality](#code-quality)
  - [Frontend Development](#frontend-development)
- [License](#-license)
- [Support](#-support)

---

## 🚀 Quick Start

### 📦 Deploy

```bash
git clone https://github.com/alecscodes/pulse.git
cd pulse
./deploy.sh
```

The `deploy.sh` script will:

- Create `.env` from `.env.example` if missing
- Use **Docker** when available, otherwise run on the host
- Run `php artisan app:deploy` (git sync, dependencies, migrations, optimization)

**Docker:** auto-detected when Docker Compose is available.

**Standard (cPanel/VPS):** runs on the host and adds scheduler + queue cron entries.

To update an existing installation, run `./deploy.sh` or `php artisan app:deploy`.

## ✨ Features

- 🔄 **Multi-site monitoring** with custom check intervals
- 🌐 **HTTP/HTTPS support** with custom headers & query parameters
- 🔒 **SSL certificate monitoring** - automatic daily checks with expiration alerts
- 🌍 **Domain expiration monitoring** - automatic daily checks with expiration alerts via WHOIS
- ✅ **Content validation** to ensure your site returns expected content
- 📱 **Telegram notifications** for instant alerts when sites go down
- 📊 **Dashboard & analytics** to track uptime and response time
- 🔐 **Two-factor authentication** for enhanced security
- 🌙 **Dark mode** for comfortable monitoring
- 📱 **Mobile-first responsive design** - monitor from anywhere
- 🔄 **Automatic updates** - checks every five minutes via scheduler; run `./deploy.sh` for manual updates

---

## 📖 Usage

Getting started with monitoring:

1. Navigate to **Monitors → Create Monitor**
2. Enter your website URL, name, and desired check interval
3. Optionally configure:
   - Custom headers
   - Query parameters
   - Content validation rules
4. Monitors run automatically every minute via the Laravel scheduler

### Content Validation Rules

Configure validation to ensure your site returns expected content:

- **Title validation**: Must match exactly (e.g., setting "Welcome to My Site" will only pass if the page title is exactly "Welcome to My Site")
- **Content validation**: Must include the phrase (e.g., setting "Welcome to our website" will pass if the page content contains this phrase anywhere, like "Welcome to our website for all visitors" or "You are welcome to our website anytime")

You can set either validation type independently, or both together. When both are set, both conditions must pass.

### 🔒 SSL Certificate Monitoring

Pulse automatically monitors SSL certificates for all HTTPS monitors daily:

- Checks certificate validity and expiration dates
- Sends Telegram notifications when certificates expire within 30 days or are already expired
- Stores certificate details (issuer, validity dates) with each monitor check

**Manual check:**

```bash
php artisan ssl:check
```

### 🌍 Domain Expiration Monitoring

Pulse automatically monitors domain expiration for all active monitors daily:

- Queries WHOIS servers to check expiration dates
- Sends Telegram notifications when domains expire within 30 days or are already expired
- Stores expiration details with each monitor

**Manual check:**

```bash
php artisan domain:check
```

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
- Automatically detects and bans related IPs (client, forwarded, proxy, server)

**Unban commands:**

```bash
# Unban a specific IP
php artisan ip:unban 192.168.1.100

# Unban all IPs
php artisan ip:unban --all
```

### 🔄 Automatic Updates

Pulse checks for and applies updates every five minutes via the Laravel scheduler:

- **Lightweight checks**: uses `git ls-remote` (no `git fetch` on every check)
- **Smart skipping**: updates are skipped when the local commit matches remote
- **Auto-updates**: `app:deploy --if-outdated` runs every five minutes via the scheduler
- **Manual update**: run `./deploy.sh` or `php artisan app:deploy`

### 📋 Log Retention

Pulse automatically cleans up old logs daily. Critical logs are kept for 365 days, while debug logs are kept for 7 days. Retention periods are configurable per log level via settings.

```bash
php artisan logs:cleanup              # Clean up old logs
php artisan logs:cleanup --dry-run    # Preview what would be deleted
```

---

## 🔧 Artisan Commands

Pulse includes several helpful Artisan commands:

| Command | Description |
|---------|-------------|
| `php artisan app:deploy` | Deploy the application (git sync, dependencies, migrations, optimization) |
| `php artisan app:deploy --if-outdated` | Deploy only when remote has new commits (runs automatically every five minutes) |
| `php artisan ip:unban <ip>` | Unban a specific IP address |
| `php artisan ip:unban --all` | Unban all banned IP addresses |
| `php artisan monitors:check` | Manually trigger monitor checks |
| `php artisan ssl:check` | Manually check SSL certificates for all active HTTPS monitors (runs automatically daily) |
| `php artisan domain:check` | Manually check domain expiration for all active monitors (runs automatically daily) |
| `php artisan logs:cleanup` | Clean up old logs based on configurable retention periods (runs automatically daily) |
| `php artisan logs:cleanup --dry-run` | Preview what logs would be deleted without actually deleting them |

---

## 🛠 Tech Stack

| Category | Technology |
|----------|-----------|
| **Backend** | Laravel 12 · PHP 8.4+ |
| **Frontend** | Vue 3 · Inertia v2 · Tailwind CSS v4 |
| **Database** | SQLite (MySQL/PostgreSQL supported) |
| **Deployment** | Docker · Standard Hosting |
| **Testing** | Pest PHP v4 |
| **Code Quality** | Larastan (PHPStan) · Laravel Pint · ESLint · Prettier |

---

## 🧪 Development

For local development:

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

### Running Tests

```bash
php artisan test          # Run all tests
```

### Code Quality

```bash
vendor/bin/pint           # Format code with Laravel Pint
composer run analyze      # Run static analysis (PHPStan)
npm run lint              # Lint and fix JavaScript/TypeScript/Vue code (ESLint)
npm run format            # Format frontend code (Prettier)
npm run format:check      # Check frontend code formatting (Prettier)
```

### Frontend Development

```bash
npm run dev              # Start Vite dev server with hot reload
npm run build            # Build for production
```

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
