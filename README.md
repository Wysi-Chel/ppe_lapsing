# PPE Lapsing System

`AI-Integrated PPE Lapsing Schedule Management System` built with `PHP`, `MySQL`, and `Bootstrap`.

## Included

- Login with `Admin`, `Accounting Staff`, and `Auditor` roles
- PPE asset register with add, edit, view, delete, and filtering
- Automatic straight-line depreciation schedule generation
- Dashboard, reports, and audit-style record checks
- AI analysis page with OpenAI Responses API support and a local fallback when no API key is configured

## Local setup

1. Make sure Apache and MySQL are running in XAMPP.
2. Import [db.sql](/c:/xampp/htdocs/ppe_lapsing/db.sql) into MySQL or phpMyAdmin.
3. Open `http://localhost/ppe_lapsing/`.
4. Sign in with one of the seeded accounts:

- `admin@ppe.local` / `admin123`
- `staff@ppe.local` / `staff123`
- `auditor@ppe.local` / `auditor123`

## Optional OpenAI setup

You can place your OpenAI settings in a local `.env` file in the project root:

```env
OPENAI_API_KEY=your_openai_api_key_here
OPENAI_MODEL=gpt-5.5
```

This project now auto-loads `.env` on each request. If the key is missing, the app still saves a local rule-based analysis.

You can also override defaults with these environment variables:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `OPENAI_MODEL`

## Main entry points

- [index.php](/c:/xampp/htdocs/ppe_lapsing/index.php)
- [auth/login.php](/c:/xampp/htdocs/ppe_lapsing/auth/login.php)
- [modules/dashboard.php](/c:/xampp/htdocs/ppe_lapsing/modules/dashboard.php)
- [modules/assets.php](/c:/xampp/htdocs/ppe_lapsing/modules/assets.php)
- [modules/ai_analysis.php](/c:/xampp/htdocs/ppe_lapsing/modules/ai_analysis.php)
