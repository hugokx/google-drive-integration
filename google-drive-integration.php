<?php
/*
Plugin Name: Google Drive Integration
Description: Integrate Google Drive with WordPress
Version: 1.0
Author: GetUP
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly


use Sgdg\Vendor\Google\Client as GoogleClient;
use Sgdg\Vendor\Google\Service\Drive as GoogleDrive;

class GoogleDriveIntegration {
    private $options;

    public function __construct() {
        add_action('admin_init', array($this, 'check_required_plugin'));
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_google_drive_auth', array($this, 'handle_google_drive_auth')); // For logged-in users
        add_action('wp_ajax_nopriv_google_drive_auth', array($this, 'handle_google_drive_auth')); // For guests
        add_action('wp_ajax_google_drive_callback', array($this, 'handle_google_drive_callback'));
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
        $auth_url = admin_url('admin-ajax.php?action=google_drive_auth');
        echo '<a href="' . esc_url($auth_url) . '" class="button">Establish Authentication</a>';
    }

    public function enqueue_scripts() {
        wp_enqueue_script('google-api', 'https://apis.google.com/js/api.js');
        wp_enqueue_script('google-drive-integration', plugin_dir_url(__FILE__) . 'js/google-drive-integration.js', array('jquery', 'google-api'), '1.0', true);
        $options = get_option('google_drive_integration_options');
        $access_token = get_option('google_drive_access_token');
        wp_localize_script('google-drive-integration', 'googleDriveIntegration', array(
            'clientId' => $options['client_id'],
            'rootFolderId' => $options['root_folder_id'],
            'accessToken' => $access_token,
            'ajaxurl' => admin_url('admin-ajax.php') // Passes admin-ajax URL to JS
        ));
    }

    public function handle_google_drive_auth() {
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
            $client->addScope(Google_Service_Drive::DRIVE_READONLY);
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
    }
    

    public function handle_google_drive_callback() {
        // Log the function trigger
        error_log('Google Drive Callback: Function triggered.');
    
        // Attempt to load the Google API client from the other plugin
        try {
            require_once WP_PLUGIN_DIR . '/skaut-google-drive-gallery/vendor/autoload.php';
            error_log('Google Drive Callback: Autoloader included successfully.');
        } catch (Exception $e) {
            error_log('Google Drive Callback: Failed to include autoloader - ' . $e->getMessage());
            wp_die('Failed to load Google API Client.', 'Google Drive Error', array('response' => 500));
        }
    
        // Instantiate Google Client
        try {
            $client = new Google_Client();
            error_log('Google Drive Callback: Google Client instantiated.');
        } catch (Exception $e) {
            error_log('Google Drive Callback: Failed to instantiate Google Client - ' . $e->getMessage());
            wp_die('Failed to instantiate Google Client.', 'Google Drive Error', array('response' => 500));
        }
    
        // Fetch options from the database
        $options = get_option('google_drive_integration_options');
        if (empty($options['client_id']) || empty($options['client_secret'])) {
            error_log('Google Drive Callback: Client ID or Client Secret is missing.');
            wp_die('Client ID or Client Secret is missing.', 'Google Drive Error', array('response' => 500));
        }
    
        // Set client credentials
        try {
            $client->setClientId($options['client_id']);
            $client->setClientSecret($options['client_secret']);
            $client->setRedirectUri(admin_url('admin-ajax.php?action=google_drive_callback'));
            error_log('Google Drive Callback: Client credentials set successfully.');
        } catch (Exception $e) {
            error_log('Google Drive Callback: Failed to set client credentials - ' . $e->getMessage());
            wp_die('Failed to set Google client credentials.', 'Google Drive Error', array('response' => 500));
        }
    
        // Handle the callback with the authorization code
        if (isset($_GET['code'])) {
            try {
                $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
                error_log('Google Drive Callback: Token fetched successfully.');
                
                // Check for errors in token response
                if (isset($token['error'])) {
                    error_log('Google Drive Callback: Error fetching access token - ' . $token['error']);
                    wp_die('Error fetching access token: ' . $token['error'], 'Google Drive Error', array('response' => 500));
                }
    
                // Save the token in the options
                update_option('google_drive_access_token', $token);
                error_log('Google Drive Callback: Token saved successfully.');
    
                // Redirect to the settings page
                wp_redirect(admin_url('options-general.php?page=google-drive-integration'));
                exit;
    
            } catch (Exception $e) {
                error_log('Google Drive Callback: Error during token exchange - ' . $e->getMessage());
                wp_die('Error during token exchange: ' . $e->getMessage(), 'Google Drive Error', array('response' => 500));
            }
        } else {
            error_log('Google Drive Callback: Authorization code missing.');
            wp_die('Authorization code is missing.', 'Google Drive Error', array('response' => 400));
        }
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