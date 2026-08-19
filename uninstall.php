<?php
/**
 * Uninstall routine for phpList Subscribe Box.
 *
 * Removes the plugin's option and any rate-limit / session-key transients.
 * Runs only when the plugin is deleted from wp-admin, not on deactivation.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

function ssb_uninstall_cleanup_site() : void {
    global $wpdb;

    delete_option('ssb_options');

    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_ssb_') . '%',
            $wpdb->esc_like('_transient_timeout_ssb_') . '%'
        )
    );
}

if (is_multisite()) {
    $site_ids = get_sites(['fields' => 'ids']);
    foreach ($site_ids as $site_id) {
        switch_to_blog($site_id);
        ssb_uninstall_cleanup_site();
        restore_current_blog();
    }
} else {
    ssb_uninstall_cleanup_site();
}