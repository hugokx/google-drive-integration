<?php
/*
Plugin Name: Google Drive Integration
Description: Integrate Google Drive with WordPress
Version: 1.0
Author: GetUP
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class GoogleDriveIntegration {
    private $options;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_google_drive_auth', array($this, 'handle_google_drive_auth')); // For logged-in users
        add_action('wp_ajax_nopriv_google_drive_auth', array($this, 'handle_google_drive_auth')); // For guests
        add_action('wp_ajax_google_drive_callback', array($this, 'handle_google_drive_callback'));
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
        try {
            // Debug log: Verify that the function is triggered
            error_log('Google Drive Auth: Function triggered.');
            
            // Ensure the Google API Client is loaded
            require_once plugin_dir_path(__FILE__) . 'lib/vendor/autoload.php';
    
            $client = new Google_Client();
            $options = get_option('google_drive_integration_options');
    
            // Debug log: Check if client ID and secret are present
            if (empty($options['client_id']) || empty($options['client_secret'])) {
                error_log('Google Drive Auth: Missing Client ID or Client Secret.');
                wp_send_json_error(array('message' => 'Client ID or Client Secret is missing.'));
                wp_die(); // Stop the script execution
            }
    
            $client->setClientId($options['client_id']);
            $client->setClientSecret($options['client_secret']);
            $client->setRedirectUri(admin_url('admin-ajax.php?action=google_drive_callback'));
            $client->addScope(Google_Service_Drive::DRIVE_READONLY);
    
            // Try generating the auth URL
            $auth_url = $client->createAuthUrl();
            error_log('Google Drive Auth: Auth URL generated - ' . $auth_url);
            wp_send_json_success(array('auth_url' => $auth_url));
        } catch (Exception $e) {
            // Log the exception message
            error_log('Google Drive Auth: Exception - ' . $e->getMessage());
            wp_send_json_error(array('message' => 'Error generating Auth URL: ' . $e->getMessage()));
        }
    
        wp_die(); // Ensure the script ends properly
    }
    
    public function handle_google_drive_callback() {
        try {
            // Log the function trigger
            error_log('Google Drive Callback: Function triggered.');
    
            // Ensure the Google API Client is loaded
            require_once plugin_dir_path(__FILE__) . 'lib/vendor/autoload.php';
    
            // Initialize the Google client
            $client = new Google_Client();
            $options = get_option('google_drive_integration_options');
    
            if (empty($options['client_id']) || empty($options['client_secret'])) {
                error_log('Google Drive Callback: Client ID or Client Secret is missing.');
                wp_die('Google Drive Client ID or Client Secret is not configured.', 'Google Drive Error', array('response' => 500));
            }
    
            // Set up the Google Client with credentials and redirect URI
            $client->setClientId($options['client_id']);
            $client->setClientSecret($options['client_secret']);
            $client->setRedirectUri(admin_url('admin-ajax.php?action=google_drive_callback'));
    
            // Check if the authorization code is present
            if (isset($_GET['code'])) {
                error_log('Google Drive Callback: Authorization code received.');
    
                // Try to exchange the authorization code for an access token
                $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
                // Check if an error occurred while fetching the access token
                if (isset($token['error'])) {
                    error_log('Google Drive Callback: Error fetching access token - ' . $token['error']);
                    wp_die('Error fetching Google Drive access token: ' . $token['error'], 'Google Drive Error', array('response' => 500));
                }
    
                // Log the access token and any relevant data
                error_log('Google Drive Callback: Access token fetched successfully - ' . print_r($token, true));
    
                // Save the access token in WordPress options for future use
                update_option('google_drive_access_token', $token);
    
                // Redirect the user back to the plugin's settings page
                error_log('Google Drive Callback: Redirecting back to plugin settings.');
                wp_redirect(admin_url('options-general.php?page=google-drive-integration'));
                exit;
            } else {
                // Log if the 'code' parameter is missing in the URL
                error_log('Google Drive Callback: Authorization code not found.');
                wp_die('Authorization code is missing from the callback URL.', 'Google Drive Error', array('response' => 400));
            }
        } catch (Exception $e) {
            // Catch any exceptions and log the error message
            error_log('Google Drive Callback: Exception occurred - ' . $e->getMessage());
            wp_die('An error occurred during Google Drive authentication: ' . $e->getMessage(), 'Google Drive Error', array('response' => 500));
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