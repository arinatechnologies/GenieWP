<?php
/**
 * Plugin Name: GenieWP - AI Theme Generator
 * Description: Generate full WordPress block themes from AI prompts (CloudMySite).
 * Version: 1.0.0
 * Author: CloudMySite
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Text Domain: geniewp
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('GENIEWP_VERSION', '1.0.0');
define('QUICKWP_APP_BASEFILE', __FILE__);
define('QUICKWP_APP_URL', plugin_dir_url(__FILE__));
define('QUICKWP_APP_PATH', __DIR__);
define('GENIEWP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('GENIEWP_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check requirements
function geniewp_check_requirements() {
    $errors = [];

    // Check PHP version
    if (version_compare(PHP_VERSION, '8.1', '<')) {
        $errors[] = sprintf(
            __('GenieWP requires PHP %s or higher.', 'geniewp'),
            '8.1'
        );
    }

    // Check WordPress version
    if (version_compare($GLOBALS['wp_version'], '6.5', '<')) {
        $errors[] = sprintf(
            __('GenieWP requires WordPress %s or higher.', 'geniewp'),
            '6.5'
        );
    }

    // Check required extensions
    $required_extensions = ['curl', 'json', 'mbstring'];
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = sprintf(
                __('GenieWP requires the %s PHP extension.', 'geniewp'),
                $ext
            );
        }
    }

    return $errors;
}

// Display admin notices for requirements issues
function geniewp_show_requirements_notices($errors) {
    foreach ($errors as $error) {
        add_action('admin_notices', function () use ($error) {
            printf(
                '<div class="notice notice-error"><p>%s</p></div>',
                esc_html($error)
            );
        });
    }
}

// Safe loading function
function geniewp_load_plugin() {
    // Check requirements
    $errors = geniewp_check_requirements();
    if (!empty($errors)) {
        geniewp_show_requirements_notices($errors);
        return;
    }

    // Load composer autoloader if exists
    $vendor_file = GENIEWP_PLUGIN_DIR . '/vendor/autoload.php';
    if (file_exists($vendor_file) && is_readable($vendor_file)) {
        require_once $vendor_file;
    }

    // Load core classes with file existence checks
    $core_files = [
        'inc/class-main.php',
        'inc/class-api.php',
        'inc/helpers.php'
    ];

    foreach ($core_files as $file) {
        $file_path = GENIEWP_PLUGIN_DIR . $file;
        if (file_exists($file_path) && is_readable($file_path)) {
            require_once $file_path;
        }
    }

    // Initialize plugin
    add_action('plugins_loaded', function() {
        // Check if the class exists (in case file loading failed)
        if (class_exists('\\ThemeIsle\\QuickWP\\Main')) {
            new \ThemeIsle\QuickWP\Main();
        }
    });
}

// Load the plugin
geniewp_load_plugin();

// Activation hook
register_activation_hook(__FILE__, function () {
    try {
        // Check requirements again during activation
        $errors = geniewp_check_requirements();
        if (!empty($errors)) {
            // Deactivate plugin and show error
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(implode('<br>', $errors));
        }

        // Add default option for API key
        add_option('open_ai_api_key', '');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    } catch (Exception $e) {
        error_log('GenieWP activation error: ' . $e->getMessage());
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . 
                 esc_html__('GenieWP encountered an error during activation. Please check the error log.', 'geniewp') . 
                 '</p></div>';
        });
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});