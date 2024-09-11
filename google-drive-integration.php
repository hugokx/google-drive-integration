<?php
/*
Plugin Name: Google Drive Integration
Description: Integrate Google Drive with WordPress
Version: 1.0
Author: GetUP
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly


use Sgdg\Vendor\Google\Client as Google_Client;
use Sgdg\Vendor\Google\Service\Drive as Google_Drive;

class GoogleDriveIntegration {
    private $options;

    public function __construct() {
        add_action('admin_init', array($this, 'check_required_plugin'));
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_action_oauth_grant', array($this, 'handle_oauth_grant'));
        add_action('admin_init', array($this, 'handle_admin_actions'));
        /*
        add_action('wp_ajax_google_drive_auth', array($this, 'handle_google_drive_auth')); // For logged-in users
        add_action('wp_ajax_nopriv_google_drive_auth', array($this, 'handle_google_drive_auth')); // For guests
        add_action('wp_ajax_google_drive_callback', array($this, 'handle_oauth_callback'));
        add_action('wp_ajax_nopriv_google_drive_callback', array($this, 'handle_oauth_callback'));
        add_action('wp_ajax_google_drive_callback', array($this, 'handle_google_drive_callback'));
        */
    }

    public function handle_admin_actions() {
        if (isset($_GET['page']) && $_GET['page'] === 'google-drive-integration') {
            if (isset($_GET['action']) && $_GET['action'] === 'oauth_grant') {
                $this->handle_oauth_grant();
            } elseif (isset($_GET['action']) && $_GET['action'] === 'oauth_redirect') {
                $this->handle_oauth_redirect();
            }
        }
    }

    // Check if Skaut Google Drive Gallery is active
    public function check_required_plugin() {
    if (!is_plugin_active('skaut-google-drive-gallery/skaut-google-drive-gallery.php')) {
        // Deactivate this plugin if Skaut Google Drive Gallery is not active
        deactivate_plugins(plugin_basename(__FILE__));
            add_action('admin_notices', array($this, 'required_plugin_notice'));
        } else {
        // Load Skaut Google Drive Gallery's vendor folder if the plugin is active
            if (!class_exists('Google_Client')) {
                require_once WP_PLUGIN_DIR . '/skaut-google-drive-gallery/vendor/autoload.php';
            }
        }
    }
    
    // Display a notice if Skaut Google Drive Gallery is not active
    public function required_plugin_notice() {
        echo '<div class="error"><p>Google Drive Integration requires the Skaut Google Drive Gallery plugin to be installed and active.</p></div>';
    }

    public function add_plugin_page() {
        add_options_page(
            'Google Drive Integration', 
            'Google Drive Integration', 
            'manage_options', 
            'google-drive-integration', 
            array($this, 'create_admin_page')
        );
    }

    public function create_admin_page() {
        $this->options = get_option('google_drive_integration_options');
        ?>
        <div class="wrap">
            <h1>Google Drive Integration Settings</h1>
            <form method="post" action="options.php">
            <?php
                settings_fields('google_drive_integration_option_group');
                do_settings_sections('google-drive-integration-admin');
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    public function page_init() {
        register_setting(
            'google_drive_integration_option_group',
            'google_drive_integration_options',
            array($this, 'sanitize')
        );

        add_settings_section(
            'google_drive_integration_setting_section',
            'Google Drive Settings',
            array($this, 'section_info'),
            'google-drive-integration-admin'
        );

        add_settings_field(
            'client_id',
            'Client ID',
            array($this, 'client_id_callback'),
            'google-drive-integration-admin',
            'google_drive_integration_setting_section'
        );

        add_settings_field(
            'client_secret',
            'Client Secret',
            array($this, 'client_secret_callback'),
            'google-drive-integration-admin',
            'google_drive_integration_setting_section'
        );

        add_settings_field(
            'root_folder_id',
            'Root Folder ID',
            array($this, 'root_folder_id_callback'),
            'google-drive-integration-admin',
            'google_drive_integration_setting_section'
        );

        add_settings_field(
            'google_drive_auth',
            'Authenticate',
            array($this, 'render_auth_button'),
            'google-drive-integration-admin',
            'google_drive_integration_setting_section'
        );
    }

    public function sanitize($input) {
        $sanitary_values = array();
        if (isset($input['client_id'])) {
            $sanitary_values['client_id'] = sanitize_text_field($input['client_id']);
        }
        if (isset($input['client_secret'])) {
            $sanitary_values['client_secret'] = sanitize_text_field($input['client_secret']);
        }
        if (isset($input['root_folder_id'])) {
            $sanitary_values['root_folder_id'] = sanitize_text_field($input['root_folder_id']);
        }
        return $sanitary_values;
    }

    public function section_info() {
        echo 'Enter your Google Drive API settings below:';
    }

    public function client_id_callback() {
        printf(
            '<input type="text" id="client_id" name="google_drive_integration_options[client_id]" value="%s" />',
            isset($this->options['client_id']) ? esc_attr($this->options['client_id']) : ''
        );
    }

    public function client_secret_callback() {
        printf(
            '<input type="text" id="client_secret" name="google_drive_integration_options[client_secret]" value="%s" />',
            isset($this->options['client_secret']) ? esc_attr($this->options['client_secret']) : ''
        );
    }

    public function root_folder_id_callback() {
        printf(
            '<input type="text" id="root_folder_id" name="google_drive_integration_options[root_folder_id]" value="%s" />',
            isset($this->options['root_folder_id']) ? esc_attr($this->options['root_folder_id']) : ''
        );
    }

    public function render_auth_button() {
        echo '<a class="button button-primary" href="' .
            esc_url_raw(wp_nonce_url(admin_url('admin.php?page=google-drive-integration&action=oauth_grant'), 'oauth_grant')) .
            '">' .
            esc_html__('Grant Permission', 'google-drive-integration') .
            '</a>';
    }

    public function enqueue_scripts() {
        wp_enqueue_script('google-api', 'https://apis.google.com/js/api.js');
        wp_enqueue_script('google-drive-integration', plugin_dir_url(__FILE__) . 'js/google-drive-integration.js', array('jquery', 'google-api'), '1.0', true);
        $options = get_option('google_drive_integration_options');
        $access_token = get_option('google_drive_access_token');
        wp_localize_script('google-drive-integration', 'googleDriveIntegration', array(
            'clientId' => $options['client_id'],
            'rootFolderId' => $options['root_folder_id'],
            'accessToken' => $access_token
        ));
    }

    /*public function handle_google_drive_auth() {
        // Log the function trigger
        error_log('Google Drive Auth: Function triggered.');
    
        // Attempt to load the Google API client from the other plugin
        try {
            require_once WP_PLUGIN_DIR . '/skaut-google-drive-gallery/vendor/autoload.php';
            error_log('Google Drive Auth: Autoloader included successfully.');
        } catch (Exception $e) {
            error_log('Google Drive Auth: Failed to include autoloader - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Failed to load Google API Client.'));
            wp_die();
        }
    
        // Instantiate Google Client
        try {
            $client = new Google_Client();
            error_log('Google Drive Auth: Google Client instantiated.');
        } catch (Exception $e) {
            error_log('Google Drive Auth: Failed to instantiate Google Client - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Failed to instantiate Google Client.'));
            wp_die();
        }
    
        // Fetch options from the database
        $options = get_option('google_drive_integration_options');
        if (empty($options['client_id']) || empty($options['client_secret'])) {
            error_log('Google Drive Auth: Client ID or Client Secret is missing.');
            wp_send_json_error(array('message' => 'Client ID or Client Secret is missing.'));
            wp_die();
        }
    
        // Set client credentials
        try {
            $client->setClientId($options['client_id']);
            $client->setClientSecret($options['client_secret']);
            $client->setRedirectUri(admin_url('admin-ajax.php?action=google_drive_callback'));
            $client->addScope(Google_Drive::DRIVE_READONLY);
            error_log('Google Drive Auth: Client credentials set successfully.');
        } catch (Exception $e) {
            error_log('Google Drive Auth: Failed to set client credentials - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Failed to set Google client credentials.'));
            wp_die();
        }
    
        // Generate authentication URL
        try {
            $auth_url = $client->createAuthUrl();
            error_log('Google Drive Auth: Authentication URL generated - ' . $auth_url);
            wp_send_json_success(array('auth_url' => $auth_url));
        } catch (Exception $e) {
            error_log('Google Drive Auth: Failed to generate Auth URL - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Failed to generate Google Auth URL.'));
        }
    
        wp_die(); // Always die at the end of an AJAX request
    }*/
    

    public function handle_google_drive_callback() {
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'google_drive_callback')) {
            wp_die('Invalid nonce');
        }
    
        if (isset($_GET['code'])) {
            $client = new Google_Client();
            $options = get_option('google_drive_integration_options');
            $client->setClientId($options['client_id']);
            $client->setClientSecret($options['client_secret']);
            $client->setRedirectUri(add_query_arg('action', 'google_drive_callback', admin_url('admin-ajax.php')));
    
            try {
                $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
                update_option('google_drive_access_token', $token);
                echo '<script>
                    window.opener.postMessage("google-auth-success", "*");
                    window.close();
                </script>';
            } catch (Exception $e) {
                echo 'An error occurred: ' . $e->getMessage();
            }
        } else {
            echo 'Authorization code not received.';
        }
        exit;
    }

    public function handle_oauth_grant() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
    
        if (!wp_verify_nonce($_GET['_wpnonce'], 'oauth_grant')) {
            wp_die('Invalid nonce');
        }
    
        $client = new Google_Client();
        $options = get_option('google_drive_integration_options');
        $client->setClientId($options['client_id']);
        $client->setClientSecret($options['client_secret']);
        $client->setRedirectUri(admin_url('admin.php?page=google-drive-integration&action=oauth_redirect'));
        $client->addScope(Google_Drive::DRIVE_READONLY);
        $auth_url = $client->createAuthUrl();
        
        wp_redirect($auth_url);
        exit;
    }

    public function handle_oauth_redirect() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
    
        if (isset($_GET['code'])) {
            $client = new Google_Client();
            $options = get_option('google_drive_integration_options');
            $client->setClientId($options['client_id']);
            $client->setClientSecret($options['client_secret']);
            $client->setRedirectUri(admin_url('admin.php?page=google-drive-integration&action=oauth_redirect'));
    
            try {
                $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
                update_option('google_drive_access_token', $token);
                add_settings_error(
                    'google_drive_messages',
                    'oauth_updated',
                    __('Permission granted.', 'google-drive-integration'),
                    'updated'
                );
            } catch (Exception $e) {
                add_settings_error(
                    'google_drive_messages',
                    'oauth_failed',
                    __('An error occurred: ', 'google-drive-integration') . $e->getMessage(),
                    'error'
                );
            }
        } else {
            add_settings_error(
                'google_drive_messages',
                'oauth_failed',
                __('No authorization code received from Google.', 'google-drive-integration'),
                'error'
            );
        }
    
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_safe_redirect(admin_url('admin.php?page=google-drive-integration&settings-updated=true'));
        exit;
    }
}

$google_drive_integration = new GoogleDriveIntegration();

// Function to get Google Drive folder contents
function get_drive_folder_contents($folder_id = null) {
    $options = get_option('google_drive_integration_options');
    $folder_id = $folder_id ?: $options['root_folder_id'];

    // Here you would implement the logic to fetch folder contents using the Google Drive API
    // This is a placeholder and should be replaced with actual API calls
    return array('This is a placeholder for folder contents');
}