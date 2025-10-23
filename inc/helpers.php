<?php
/**
 * Helper functions for GenieWP
 *
 * @package GenieWP
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('geniewp_admin_notice')) {
    /**
     * Display an admin notice
     *
     * @param string $message Notice message
     * @param string $type Notice type (error, warning, success, info)
     * @return void
     */
    function geniewp_admin_notice($message, $type = 'error') {
        add_action('admin_notices', function () use ($message, $type) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        });
    }
}

if (!function_exists('geniewp_is_ready')) {
    /**
     * Check if all requirements are met
     *
     * @return bool
     */
    function geniewp_is_ready() {
        // Check required extensions
        $extensions = ['curl', 'json', 'mbstring'];
        foreach ($extensions as $ext) {
            if (!extension_loaded($ext)) {
                return false;
            }
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            return false;
        }
        
        // Check WordPress version
        if (version_compare($GLOBALS['wp_version'], '6.5', '<')) {
            return false;
        }
        
        return true;
    }
}