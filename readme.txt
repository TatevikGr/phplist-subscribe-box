=== phpList Subscribe Box ===
Contributors: (your-wordpress-org-username)
Tags: phplist, newsletter, subscribe, email, widget
Requires at least: 5.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add a simple, styled email subscribe box (shortcode or widget) that pushes subscribers straight into your phpList list.

== Description ==

phpList Subscribe Box adds a lightweight, mobile-friendly subscribe form to any page, post, or widget area, and pushes new subscribers directly to your self-hosted [phpList](https://www.phplist.org/) instance via its REST API.

**Features**

* `[subscribe_box]` shortcode with customizable heading, subtitle, placeholder, and button text.
* A widget version of the same form for use in sidebars and widget areas.
* Subscribes go through phpList's `/api/v2` REST API using a session key obtained from your configured phpList username/password.
* Optional environment variables (`SSB_PHPLIST_USER` / `SSB_PHPLIST_PASS`) to keep phpList credentials out of the database.
* Hidden honeypot field and per-IP rate limiting to reduce spam and abuse.
* HTTPS enforcement option for the phpList endpoint.

This plugin only communicates with the phpList instance you configure in Settings → Subscribe Box; no data is sent anywhere else.

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/` directory, or install it directly through the Plugins screen in wp-admin.
2. Activate the plugin through the 'Plugins' screen in wp-admin.
3. Go to Settings → Subscribe Box and set your phpList Base URL, Subscription Path (e.g. `/api/v2/lists/1/subscribers`), and phpList username/password.
4. Add the `[subscribe_box]` shortcode to a page or post, or add the "Subscribe Box (phpList)" widget to a widget area.

== Frequently Asked Questions ==

= Does this plugin work with phplist.com (hosted) or only self-hosted phpList? =

It talks to any phpList instance that exposes the `/api/v2` REST API at a URL you control, including self-hosted installs.

= Where are my phpList credentials stored? =

By default, in the plugin's settings (in the WordPress options table). You can instead set the `SSB_PHPLIST_USER` and `SSB_PHPLIST_PASS` environment variables (e.g. in `wp-config.php` or your server environment); these override any saved values and avoid storing credentials in the database.

= What happens if a bot fills out the form? =

A hidden honeypot field silently short-circuits the submission (a success message is shown, but nothing is sent to phpList). Submissions are also rate-limited per IP address.

= What data does this plugin send to phpList? =

Only the email address submitted through the form, sent to the subscription path you configure.

== Screenshots ==

1. Front-end subscribe box rendered by the `[subscribe_box]` shortcode.
2. Settings screen under Settings → Subscribe Box.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.