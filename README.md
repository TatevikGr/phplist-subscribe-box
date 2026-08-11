# phpList Subscribe Box (WordPress Plugin)

A minimal WordPress plugin that adds a subscribe box via a shortcode and widget, posting subscriptions to your phpList instance.

This file is developer-facing documentation (repo/build notes, local Docker setup). The plugin's user-facing listing content — the copy shown on the WordPress.org plugin page — lives in `readme.txt`; keep both in sync when behavior changes.

## Installation
1. Copy this folder into your WordPress installation under `wp-content/plugins/phplist-subscribe-box/` (the main file is `phplist-subscribe-box.php`).
2. In wp-admin, go to Plugins and activate "phpList Subscribe Box".

## Configure
1. Go to Settings -> Subscribe Box.
2. Set your phpList Base URL and Subscription Path (e.g. `/api/v2/lists/1/subscribers`).
3. Set your phpList username/password (used to obtain a session key via `/api/v2/sessions`). You can instead set the `SSB_PHPLIST_USER` / `SSB_PHPLIST_PASS` environment variables to avoid storing credentials in the database — these override the saved values.
4. Adjust the email field name, success/error messages, and whether HTTPS is enforced for the endpoint.

## Usage
- Shortcode: add `[subscribe_box]` to any page/post or widget area (using a Shortcode block).
- Widget: Appearance -> Widgets -> add "Subscribe Box".

### Shortcode attributes
- `heading` (default "Stay in the loop")
- `subtitle` (default "Subscribe to our newsletter and get the latest updates delivered straight to your inbox.")
- `placeholder_email` (default "Enter your email address")
- `button_text` (default "Subscribe")
- `show_badges` ("true"/"false", default "true") — shows the "No spam, ever / Your data is safe / Unsubscribe anytime" row
- `show_branding` ("true"/"false", default "true") — shows the "Powered by phpList" footer
- `compact` ("true"/"false", default "false") — renders just the email pill (no heading, subtitle, badges, or branding); used automatically by the widget

Example:
```
[subscribe_box heading="Join our list" placeholder_email="Email address" button_text="Join"]
```

## How it works
- The form submits via AJAX to `admin-ajax.php` with action `ssb_subscribe`.
- The plugin sends a JSON POST to your configured API endpoint using WordPress HTTP API.
- On 2xx responses, a success message is shown. On non-2xx, an error message is shown (tries to read `message` or `error` from JSON response).

## Security
- Nonce verification on AJAX requests.
- Input sanitized and validated (email checked with `is_email`).
- Hidden honeypot field rejects (silently, without hitting phpList) submissions where it's filled in.
- Per-IP rate limiting (5 submissions per 10 minutes) on the subscribe endpoint.

## Requirements
- WordPress 5.2+
- PHP 7.4+

## Uninstall
- Deactivating the plugin leaves settings intact.
- Deleting the plugin from wp-admin runs `uninstall.php`, which removes the `ssb_options` option and any rate-limit/session-key transients (including per-site on multisite).

## License
GPLv2 or later. See `LICENSE`.

## Packaging for distribution
Files listed in `.distignore` (`.git`, `.idea`, `docker-compose.yml`, `.env`/`.env.example`, this `README.md`, etc.) are development-only and should be excluded from the zip uploaded to WordPress.org or distributed to users — `readme.txt`, `uninstall.php`, `LICENSE`, `phplist-subscribe-box.php`, and `assets/` are what ship.
