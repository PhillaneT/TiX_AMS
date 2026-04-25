# AjanaNova Grader

## Overview

AjanaNova Grader (`local_ajananova`) is a **Moodle local plugin** that adds AI-assisted assessment marking, credit-based billing, and PDF annotation to a Moodle 4.1+ site. It is **not** a standalone web application — it is designed to be installed inside an existing Moodle instance at `<moodle>/local/ajananova/`.

- Language: PHP 8.2
- Package manager: Composer (`composer.json` / `composer.lock`)
- Plugin type: Moodle local plugin
- License: GPL-3.0-or-later

## Replit setup

Because the plugin's PHP files all bootstrap Moodle (`require_once(__DIR__ . '/../../config.php');` and `defined('MOODLE_INTERNAL') || die();`), they cannot render outside a Moodle install. To make the project meaningfully browsable in the Replit preview, a developer overview page (`index.php`) is served by PHP's built-in web server.

- Workflow: `Server` runs `php -S 0.0.0.0:5000 -t .` on port 5000 (webview output).
- `index.php` parses `version.php`, `composer.json`, `settings.php`, and `db/install.xml` to render plugin metadata, configurable settings, and the database schema.
- `index.php` is gated on `!defined('MOODLE_INTERNAL')` so it is inert inside a real Moodle install.

## Project layout

- `version.php` — Moodle plugin metadata (component, version, requires, maturity).
- `lib.php` — Moodle navigation/settings hook callbacks.
- `settings.php` — Admin settings (mock mode, Anthropic API key, licence key, credit costs).
- `memo.php` / `mark.php` — Assessor-facing pages reached from the assignment gear menu.
- `db/install.xml` — Database schema for three tables: `ajananova_ai_usage`, `ajananova_marking_results`, `ajananova_client_credits`.
- `db/hooks.php` — Registers the `before_footer_html_generation` hook listener (Moodle 4.3+).
- `classes/` — Auto-loaded namespaced classes:
  - `ai/` — Anthropic client, mock client, prompt builder, marking engine.
  - `billing/` — Credit manager and usage logger.
  - `grading/` — Marking criteria reader.
  - `output/` — Marking review renderer.
  - `pdf/` — PDF text extractor and annotator (uses `smalot/pdfparser`, `setasign/fpdi`, `tecnickcom/tcpdf`).
  - `hook_listener.php` — Injects the floating "Mark with AI" button.
- `lang/en/local_ajananova.php` — English language strings.
- `templates/` — Mustache templates for the marking review, memo upload, and credits-exhausted screens.
- `index.php` — Replit-only landing page (NOT shipped with the plugin into Moodle).

## Composer dependencies

- `smalot/pdfparser` ^2.0 — extract text from learner submission PDFs.
- `setasign/fpdi` ^2.3 — import existing PDFs as templates.
- `tecnickcom/tcpdf` ^6.6 — write annotated PDFs.

Install with `composer install`.

## Installing into Moodle

1. Copy the directory to `<moodle>/local/ajananova/`.
2. Run `composer install` inside the plugin directory.
3. Visit `Site administration → Notifications` to install the database tables.
4. Configure under `Site administration → Plugins → Local plugins → AjanaNova Grader`. Mock mode is on by default.
5. Open any assignment as an assessor; the gear menu now shows *AjanaNova: Upload marking guide* and *AjanaNova: Mark with AI*.

## User preferences

None recorded yet.
