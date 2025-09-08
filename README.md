# Simple Subscribe Box (WordPress Plugin)

A minimal WordPress plugin that adds a subscribe box via a shortcode and widget, posting subscriptions to your API endpoint.

## Installation
1. Copy the `subscribe-box` folder into your WordPress installation under `wp-content/plugins/`.
2. In wp-admin, go to Plugins and activate "Simple Subscribe Box".

## Configure
1. Go to Settings -> Subscribe Box.
2. Set your API Endpoint URL.
3. Optionally set an API Key (adds an `Authorization: Bearer <key>` header).
4. Adjust field names, success and error messages, and optional extra payload (JSON merged into request body).

## Usage
- Shortcode: add `[subscribe_box]` to any page/post or widget area (using a Shortcode block).
- Widget: Appearance -> Widgets -> add "Subscribe Box".

### Shortcode attributes
- `placeholder_email` (default "Your email")
- `placeholder_name` (default "Your name (optional)")
- `button_text` (default "Subscribe")
- `show_name` ("true"/"false", default "true")
- `compact` ("true"/"false", default "false")

Example:
```
[subscribe_box placeholder_email="Email address" button_text="Join"]
```

## How it works
- The form submits via AJAX to `admin-ajax.php` with action `ssb_subscribe`.
- The plugin sends a JSON POST to your configured API endpoint using WordPress HTTP API.
- On 2xx responses, a success message is shown. On non-2xx, an error message is shown (tries to read `message` or `error` from JSON response).

## Security
- Nonce verification on AJAX requests.
- Input sanitized and validated (email checked with `is_email`).

## Requirements
- WordPress 5.2+
- PHP 7.4+

## Uninstall
- Deactivating the plugin leaves settings intact.
- To remove settings, delete the `ssb_options` option via the database or a custom uninstall routine (not included).
