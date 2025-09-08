<?php
/**
 * Plugin Name: phpList Subscribe Box
 * Description: Adds a subscribe box via shortcode and widget, sending subscriptions to phpList (session key via login/password)
 * Version: 1.3.1
 * Author: Tatevik Grigoryan
 * License: AGPL-3.0-or-later
 * Requires at least: 5.2
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) { exit; }

/** Guard against re-declare if file is included twice */
if (!class_exists('PhpList_Subscribe_Box_Plugin')):

    class PhpList_Subscribe_Box_Plugin {
        const OPTION_GROUP = 'ssb_options_group';
        const OPTION_NAME  = 'ssb_options';
        const NONCE_ACTION = 'ssb_subscribe_action';
        const NONCE_NAME   = 'ssb_nonce';

        public function __construct() {
            // Settings
            add_action('admin_menu', [$this, 'add_settings_page']);
            add_action('admin_init', [$this, 'register_settings']);

            // Shortcode
            add_shortcode('subscribe_box', [$this, 'render_subscribe_box']);

            // AJAX handlers
            add_action('wp_ajax_ssb_subscribe', [$this, 'handle_ajax_subscribe']);
            add_action('wp_ajax_nopriv_ssb_subscribe', [$this, 'handle_ajax_subscribe']);

            // Assets
            add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

            // Widget (register after the widget class is defined, see add_action below)
            add_action('widgets_init', [__CLASS__, 'register_widget']);
        }

        /** Register widget after class is defined */
        public static function register_widget() : void {
            if (class_exists('PhpList_SSB_Subscribe_Widget')) {
                register_widget('PhpList_SSB_Subscribe_Widget');
            }
        }

        /** Defaults */
        public static function default_options() : array {
            return [
                'api_endpoint'      => '',
                'subscription_path' => '/api/v2/lists/1/subscribers',
                'api_user'          => '',
                'api_pass'          => '',
                'email_field'       => 'email',
                'success_message'   => 'Thanks! Please check your inbox.',
                'error_message'     => 'Sorry, something went wrong. Please try again later.',
                'enforce_https'     => '1',
            ];
        }

        /** Assets */
        public function enqueue_assets() : void {
            wp_register_style('ssb_styles', plugins_url('assets/subscribe-box.css', __FILE__), [], '1.0.0');
            wp_enqueue_style('ssb_styles');

            wp_register_script('ssb_script', plugins_url('assets/subscribe-box.js', __FILE__), ['jquery'], '1.0.0', true);
            wp_localize_script('ssb_script', 'SSB', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce(self::NONCE_ACTION),
            ]);
            wp_enqueue_script('ssb_script');
        }

        /** Admin UI */
        public function add_settings_page() : void {
            add_options_page('Simple Subscribe Box', 'Subscribe Box', 'manage_options', 'ssb-settings', [$this, 'render_settings_page']);
        }

        public function register_settings() : void {
            register_setting(self::OPTION_GROUP, self::OPTION_NAME, [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_options'],
                'default' => self::default_options(),
            ]);

            add_settings_section('ssb_main_section', 'phpList API Settings', function() {
                echo '<p>Configure phpList endpoint and credentials. If you set environment variables <code>SSB_PHPLIST_USER</code> and <code>SSB_PHPLIST_PASS</code>, they will override the saved username/password here.</p>';
            }, 'ssb-settings');

            add_settings_field('api_endpoint', 'Base URL (phpList)', [$this, 'field_api_endpoint'], 'ssb-settings', 'ssb_main_section');
            add_settings_field('subscription_path', 'Subscription Path', [$this, 'field_subscription_path'], 'ssb-settings', 'ssb_main_section');
            add_settings_field('api_user', 'phpList Username', [$this, 'field_api_user'], 'ssb-settings', 'ssb_main_section');
            add_settings_field('api_pass', 'phpList Password', [$this, 'field_api_pass'], 'ssb-settings', 'ssb_main_section');
            add_settings_field('email_field', 'Email Field Name', [$this, 'field_email_field'], 'ssb-settings', 'ssb_main_section');
            add_settings_field('success_message', 'Success Message', [$this, 'field_success_message'], 'ssb-settings', 'ssb_main_section');
            add_settings_field('error_message', 'Error Message', [$this, 'field_error_message'], 'ssb-settings', 'ssb_main_section');
            add_settings_field('enforce_https', 'Enforce HTTPS', [$this, 'field_enforce_https'], 'ssb-settings', 'ssb_main_section');
        }

        public function sanitize_options($opts) : array {
            $defaults = self::default_options();
            $opts = is_array($opts) ? $opts : [];
            $prev  = get_option(self::OPTION_NAME, $defaults);

            $out = [
                'api_endpoint'      => isset($opts['api_endpoint']) ? esc_url_raw(trim($opts['api_endpoint'])) : $defaults['api_endpoint'],
                'subscription_path' => isset($opts['subscription_path']) ? '/'.ltrim(sanitize_text_field($opts['subscription_path']), '/') : $defaults['subscription_path'],
                'api_user'          => isset($opts['api_user']) ? sanitize_text_field($opts['api_user']) : $defaults['api_user'],
                // keep previous unless a new non-empty value is provided
                'api_pass'          => (!empty($opts['api_pass']) && $opts['api_pass'] !== '********') ? wp_unslash($opts['api_pass']) : ( $prev['api_pass'] ?? '' ),
                'email_field'       => isset($opts['email_field']) ? sanitize_key($opts['email_field']) : $defaults['email_field'],
                'success_message'   => isset($opts['success_message']) ? sanitize_text_field($opts['success_message']) : $defaults['success_message'],
                'error_message'     => isset($opts['error_message']) ? sanitize_text_field($opts['error_message']) : $defaults['error_message'],
                'enforce_https'     => !empty($opts['enforce_https']) ? '1' : '0',
            ];

            if ($out['enforce_https'] === '1' && $out['api_endpoint']) {
                if (parse_url($out['api_endpoint'], PHP_URL_SCHEME) !== 'https') {
                    add_settings_error(self::OPTION_NAME, 'ssb_https_required', 'Endpoint must be HTTPS when enforcement is enabled.');
                }
            }

            return $out;
        }

        private function get_options(): array {
            return wp_parse_args(get_option(self::OPTION_NAME, []), self::default_options());
        }

        /** Fields */
        public function field_api_endpoint()      { $o = $this->get_options(); echo '<input type="url" name="'.self::OPTION_NAME.'[api_endpoint]" value="'.esc_attr($o['api_endpoint']).'" class="regular-text" placeholder="https://phplist.example.com" />'; }
        public function field_subscription_path() { $o = $this->get_options(); echo '<input type="text" name="'.self::OPTION_NAME.'[subscription_path]" value="'.esc_attr($o['subscription_path']).'" class="regular-text" placeholder="/api/v2/lists/1/subscribers" />'; }
        public function field_api_user()          { $o = $this->get_options(); echo '<input type="text" name="'.self::OPTION_NAME.'[api_user]" value="'.esc_attr($o['api_user']).'" class="regular-text" placeholder="apiuser" />'; }
        public function field_api_pass()          { $o = $this->get_options(); $mask = $o['api_pass'] ? '********' : ''; echo '<input type="password" name="'.self::OPTION_NAME.'[api_pass]" value="'.esc_attr($mask).'" class="regular-text" autocomplete="new-password" placeholder="••••••••" />'; echo '<p class="description">Tip: set <code>SSB_PHPLIST_USER</code> / <code>SSB_PHPLIST_PASS</code> in wp-config/env to avoid storing secrets in DB.</p>'; }
        public function field_email_field()       { $o = $this->get_options(); echo '<input type="text" name="'.self::OPTION_NAME.'[email_field]" value="'.esc_attr($o['email_field']).'" class="regular-text" />'; }
        public function field_success_message()   { $o = $this->get_options(); echo '<input type="text" name="'.self::OPTION_NAME.'[success_message]" value="'.esc_attr($o['success_message']).'" class="regular-text" />'; }
        public function field_error_message()     { $o = $this->get_options(); echo '<input type="text" name="'.self::OPTION_NAME.'[error_message]" value="'.esc_attr($o['error_message']).'" class="regular-text" />'; }
        public function field_enforce_https()     { $o = $this->get_options(); echo '<label><input type="checkbox" name="'.self::OPTION_NAME.'[enforce_https]" value="1" '.checked('1',$o['enforce_https'],false).' /> Require HTTPS endpoint</label>'; }

        public function render_settings_page() : void {
            if (!current_user_can('manage_options')) { return; }
            echo '<div class="wrap"><h1>Simple Subscribe Box</h1><form method="post" action="options.php">';
            settings_fields(self::OPTION_GROUP);
            do_settings_sections('ssb-settings');
            submit_button();
            echo '</form><p>Use the shortcode <code>[subscribe_box]</code> to display the form.</p></div>';
        }

        /** Shortcode */
        public function render_subscribe_box($atts = []) : string {
            $atts = shortcode_atts([
                'placeholder_email' => 'Your email',
                'placeholder_name'  => 'Your name (optional)',
                'button_text'       => 'Subscribe',
                'show_name'         => 'true',
                'compact'           => 'false',
            ], $atts, 'subscribe_box');

            $show_name = filter_var($atts['show_name'], FILTER_VALIDATE_BOOLEAN);
            $compact   = filter_var($atts['compact'], FILTER_VALIDATE_BOOLEAN);

            $nonce = wp_create_nonce(self::NONCE_ACTION);
            $html  = '<form class="ssb-form'.($compact?' ssb-compact':'').'" method="post">';
            $html .= '<input type="hidden" name="action" value="ssb_subscribe" />';
            $html .= '<input type="hidden" name="'.esc_attr(self::NONCE_NAME).'" value="'.esc_attr($nonce).'" />';
            if ($show_name) {
                $html .= '<input type="text" name="name" class="ssb-input ssb-name" placeholder="'.esc_attr($atts['placeholder_name']).'" />';
            }
            $html .= '<input type="email" name="email" class="ssb-input ssb-email" placeholder="'.esc_attr($atts['placeholder_email']).'" required />';
            $html .= '<button type="submit" class="ssb-button">'.esc_html($atts['button_text']).'</button>';
            $html .= '<div class="ssb-message" aria-live="polite"></div>';
            $html .= '</form>';
            return $html;
        }

        /** AJAX submit — direct push (no queue) */
        public function handle_ajax_subscribe() : void {
            $nonce = isset($_POST[self::NONCE_NAME]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])) : '';
            if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
                wp_send_json_error(['message' => 'Invalid request.'], 400);
            }

            $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
            $name  = isset($_POST['name'])  ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
            if (empty($email) || !is_email($email)) {
                wp_send_json_error(['message' => 'Please enter a valid email.'], 400);
            }

            $opts = $this->get_options();
            $payload = $this->build_payload($email, $name, $opts);

            $result = $this->push_to_phplist($payload);
            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()], 502);
            }
            wp_send_json_success(['message' => $opts['success_message']]);
        }

        private function build_payload(string $email, string $name, array $opts) : array {
            return ['emails' => [$email]];
        }

        /** === phpList client ==================================================== */

        private function push_to_phplist(array $payload) {
            $opts = $this->get_options();

            if (empty($opts['api_endpoint'])) {
                return new WP_Error('ssb_no_endpoint', 'phpList endpoint is not configured.');
            }
            if ($opts['enforce_https'] === '1' && parse_url($opts['api_endpoint'], PHP_URL_SCHEME) !== 'https') {
                return new WP_Error('ssb_https_required', 'phpList endpoint must be HTTPS.');
            }

            $base = rtrim($opts['api_endpoint'], '/');
            $path = $opts['subscription_path'] ?: '/api/v2/lists/1/subscribers';
            $key = $this->get_cached_session_key($base);

            if (!$key) {
                $key = $this->phplist_login($base);
                if (is_wp_error($key)) return $key;
            }

            $resp = $this->phplist_request($base . $path, $payload, $key);
            if ($this->is_auth_failure($resp)) {
                $this->delete_cached_session_key($base);
                $key = $this->phplist_login($base);
                if (is_wp_error($key)) return $key;
                $resp = $this->phplist_request($base . $path, $payload, $key);
            }

            $code = wp_remote_retrieve_response_code($resp);
            $body = wp_remote_retrieve_body($resp);

            $json = json_decode($body, true);
            $has_error = is_array($json) && (isset($json['error']) || isset($json['errors']));
            if (($code < 200 || $code >= 300) || $has_error) {
                $msg = $this->extract_error_message($body) ?: 'phpList API error.';
                return new WP_Error('ssb_api_error', $msg, ['status' => $code]);
            }
            return ['ok' => true];
        }

        private function phplist_login(string $base) {
            $user = $this->get_phplist_user();
            $pass = $this->get_phplist_pass();
            if (!$user || !$pass) return new WP_Error('ssb_missing_creds', 'phpList credentials not configured.');

            $resp = wp_remote_post($base.'/api/v2/sessions', [
                'timeout' => 10,
                'headers' => ['Content-Type' => 'application/json'],
                'body'    => wp_json_encode(['login_name' => $user, 'password' => $pass]),
            ]);
            if (is_wp_error($resp)) return $resp;

            $code = wp_remote_retrieve_response_code($resp);
            $body = wp_remote_retrieve_body($resp);
            $json = json_decode($body, true);

            if ($code < 200 || $code >= 300 || !is_array($json) || empty($json['key'])) {
                $msg = $this->extract_error_message($body) ?: 'phpList login failed (invalid credentials or response).';
                return new WP_Error('ssb_login_failed', $msg, ['status' => $code]);
            }

            $key = $json['key'];
            $ttl = !empty($json['expiry_date']) ? max(60, strtotime($json['expiry_date']) - time()) : 1800;
            set_transient($this->session_key_key($base, $user), $key, $ttl);
            return $key;
        }

        private function phplist_request(string $url, array $payload, string $session_key) {
            return wp_remote_request($url, [
                'method'  => 'POST',
                'timeout' => 10,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'php-auth-pw'  => $session_key,
                ],
                'body'    => wp_json_encode($payload),
            ]);
        }

        private function is_auth_failure($resp) : bool {
            if (is_wp_error($resp)) return true;
            $code = wp_remote_retrieve_response_code($resp);
            $body = wp_remote_retrieve_body($resp);
            if ($code === 401 || $code === 403) return true;
            if ($code === 200 && stripos($body, '<html') !== false && stripos($body, 'login') !== false) return true;
            return false;
        }

        private function extract_error_message(string $body) : string {
            $json = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                if (!empty($json['message']) && is_string($json['message'])) return sanitize_text_field($json['message']);
                if (!empty($json['error'])) {
                    return is_array($json['error']) ? sanitize_text_field($json['error']['message'] ?? 'Error') : sanitize_text_field($json['error']);
                }
                if (!empty($json['errors']) && is_array($json['errors'])) {
                    return sanitize_text_field(reset($json['errors']));
                }
            }
            $stripped = trim(wp_strip_all_tags($body));
            return $stripped ? mb_substr($stripped, 0, 200) : '';
        }

        private function get_phplist_user() : string {
            $env = getenv('SSB_PHPLIST_USER');
            if ($env) return $env;
            $o = $this->get_options();
            return $o['api_user'] ?? '';
        }
        private function get_phplist_pass() : string {
            $env = getenv('SSB_PHPLIST_PASS');
            if ($env) return $env;
            $o = $this->get_options();
            return $o['api_pass'] ?? '';
        }

        private function session_key_key(string $base, string $user) : string {
            return 'ssb_phplist_session_'.md5($base.'|'.$user);
        }
        private function get_cached_session_key(string $base) {
            return get_transient($this->session_key_key($base, $this->get_phplist_user()));
        }
        private function delete_cached_session_key(string $base) : void {
            delete_transient($this->session_key_key($base, $this->get_phplist_user()));
        }
    }

endif; // end PhpList_Subscribe_Box_Plugin guard

// Widget class with a unique, prefixed name to avoid collisions
if (!class_exists('PhpList_SSB_Subscribe_Widget')):
    class PhpList_SSB_Subscribe_Widget extends WP_Widget {
        public function __construct() {
            parent::__construct('phplist_ssb_subscribe_widget', 'Subscribe Box (phpList)', [
                'description' => 'A simple subscribe form that posts to phpList.'
            ]);
        }

        public function widget($args, $instance) {
            echo $args['before_widget'];
            if (!empty($instance['title'])) {
                echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
            }
            echo do_shortcode('[subscribe_box]');
            echo $args['after_widget'];
        }

        public function form($instance) {
            $title = isset($instance['title']) ? esc_attr($instance['title']) : 'Subscribe';
            echo '<p><label for="'.$this->get_field_id('title').'">Title:</label>';
            echo '<input class="widefat" id="'.$this->get_field_id('title').'" name="'.$this->get_field_name('title').'" type="text" value="'.$title.'"></p>';
        }

        public function update($new_instance, $old_instance) {
            $instance = [];
            $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
            return $instance;
        }
    }
endif;

// Bootstrap the plugin
if (class_exists('PhpList_Subscribe_Box_Plugin') && !isset($GLOBALS['phplist_subscribe_box_plugin'])) {
    $GLOBALS['phplist_subscribe_box_plugin'] = new PhpList_Subscribe_Box_Plugin();
}
