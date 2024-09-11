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
        //admin actions
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'check_required_plugin'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'page_init'));
        add_action('admin_init', array($this, 'handle_admin_actions'));

        // Front-end actions
        //add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        // Add AJAX hook to expose this function to JavaScript via an AJAX request
        add_action('wp_ajax_get_banner_data', array($this, 'ajax_get_banner_data'));
        add_action('wp_ajax_nopriv_get_banner_data', array($this, 'ajax_get_banner_data')); // For non-logged-in users
    }

    public function handle_admin_actions() {
        if (isset($_POST['revoke_token'])) {
            $this->revoke_token(); // Revoke the token when the form is submitted
        }
        if (isset($_GET['page']) && $_GET['page'] === 'google-drive-integration') {
            if (isset($_GET['action']) && $_GET['action'] === 'oauth_grant') {
                $this->handle_oauth_grant();
            } elseif (isset($_GET['action']) && $_GET['action'] === 'oauth_redirect') {
                $this->handle_oauth_redirect();
            }
        }
    }

    public function check_required_plugin() {
        if (!is_plugin_active('skaut-google-drive-gallery/skaut-google-drive-gallery.php')) {
            deactivate_plugins(plugin_basename(__FILE__));
            add_action('admin_notices', array($this, 'required_plugin_notice'));
        } else {
            if (!class_exists('Google_Client')) {
                require_once WP_PLUGIN_DIR . '/skaut-google-drive-gallery/vendor/autoload.php';
            }
        }
    }
    
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
        $access_token = get_option('google_drive_access_token');

        // Check the existence and validity of the token
        $token_valid = false;
        if (!empty($access_token) && isset($access_token['access_token'])) {
            error_log('Checking if access token exists and is valid or expired...');
            if (!$this->is_token_expired($access_token)) {
                $token_valid = $this->validate_access_token($access_token['access_token']);
                error_log('Token is not expired.');
            }else{
                error_log('Token is expired.');
            }
        } else {
            error_log('No access token found in WordPress options.');
        }

        ?>
        <div class="wrap">
        <h1>Google Drive Integration Settings</h1>

        <!-- Show a success message if the access token is valid -->
        <?php if ($token_valid): ?>
            <div style="background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 15px;">
                <strong>Authentication successful!</strong> You are connected to Google Drive.
            </div>
            <form method="post">
                <input type="submit" name="revoke_token" class="button button-secondary" value="Revoke Access to Google Drive">
            </form>
        <?php else: ?>
            <div style="background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 15px;">
                <strong>Authentication required!</strong> Please authorize access to Google Drive.
            </div>
        <?php endif; ?>
            <form method="post" action="options.php">
        <?php
            settings_fields('google_drive_integration_option_group');
            do_settings_sections('google-drive-integration-admin');
            submit_button();
        ?>
            </form>
        </div>
        <!-- Simply enqueue the external scripts without "onload" -->
        <script async defer src="https://apis.google.com/js/api.js"></script>
        <script async defer src="https://accounts.google.com/gsi/client"></script>
        <?php
    }

    // Function to validate the token via Google's tokeninfo endpoint
    public function validate_access_token($token) {
        $url = 'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=' . $token;
    
        error_log('Validating access token with Google API...');
    
        // Make a GET request to check the token validity
        $response = wp_remote_get($url);
    
        if (is_wp_error($response)) {
            error_log("Token validation request failed: " . $response->get_error_message());
            return false;
        }
    
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
    
        // Log the response from the validation
        error_log('Token validation response: ' . print_r($data, true));
    
        if (isset($data['expires_in']) && $data['expires_in'] > 0) {
            error_log("Token is valid, expires in: " . $data['expires_in'] . " seconds.");
            return true; // Token is valid
        } else {
            error_log("Token is invalid or expired.");
            return false; // Token is expired
        }
    }
    

    public function is_token_expired($token_data) {
        if (empty($token_data['access_token'])) {
            error_log('No access token found in the token data.');
            return true; // Token does not exist
        }
    
        // Extract created and expires_in from the token data
        $created_time = isset($token_data['created']) ? (int) $token_data['created'] : 0;
        $expires_in = isset($token_data['expires_in']) ? (int) $token_data['expires_in'] : 0;
    
        // Log values for debugging
        error_log('Token created time: ' . $created_time);
        error_log('Token expires in: ' . $expires_in . ' seconds.');
    
        // Calculate the current time
        $current_time = time();
        error_log('Current time: ' . $current_time);
    
        // Calculate when the token expires
        $expiration_time = $created_time + $expires_in;
        error_log('Token expiration time: ' . $expiration_time);

        // Debugging logs
        error_log("Token created time (UTC): " . gmdate('Y-m-d H:i:s', $created_time));
        error_log("Current time (UTC): " . gmdate('Y-m-d H:i:s', $current_time));
        error_log("Expires in (seconds): {$expires_in}");
        error_log("Token expiry time (UTC): " . gmdate('Y-m-d H:i:s', $created_time + $expires_in));

    
        // If the current time is greater than the expiration time, the token has expired
        if ($current_time >= $expiration_time) {
            error_log('Token has expired.');
            return true;
        } else {
            error_log('Token is still valid.');
            return false;
        }
    }
    

    // Function to revoke the token from Google and remove it from WordPress
    public function revoke_token() {
        $access_token = get_option('google_drive_access_token');

        if (!empty($access_token) && isset($access_token['access_token'])) {
            $token = $access_token['access_token'];

            // Make a request to Google's revocation endpoint
            $url = 'https://accounts.google.com/o/oauth2/revoke?token=' . $token;
            error_log('Sending revocation request to Google...');
            $response = wp_remote_get($url);

            // If the request is successful, remove the token from WordPress
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                error_log('Token successfully revoked from Google.');
                // Check if the token is invalid now
                $token_valid = $this->validate_access_token($token);

                if (!$token_valid) {
                    // Token is invalid, delete it from WordPress
                    delete_option('google_drive_access_token');
                    error_log('Token successfully revoked and removed from WordPress.');

                    // Display success message
                    add_settings_error(
                        'google_drive_messages',
                        'token_revoked',
                        __('Token well revoked!', 'google-drive-integration'),
                        'updated'
                    );
                } else {
                    // If token is still valid, there was an issue revoking it
                    error_log('Token revocation failed.');
                    add_settings_error(
                        'google_drive_messages',
                        'revoke_failed',
                        __('Failed to revoke the token.', 'google-drive-integration'),
                        'error'
                    );
                }
            } else {
                error_log('Failed to revoke the token from Google.');
                add_settings_error(
                    'google_drive_messages',
                    'revoke_failed',
                    __('Failed to revoke access to Google Drive.', 'google-drive-integration'),
                    'error'
                );
            }
        } else {
            error_log('No access token found to revoke.');
            add_settings_error(
                'google_drive_messages',
                'no_token',
                __('No token found to revoke.', 'google-drive-integration'),
                'error'
            );
        }

        // Refresh the page to display the updated status
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_safe_redirect(admin_url('admin.php?page=google-drive-integration&settings-updated=true'));
        exit;
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
            'client_id', 'Client ID', array($this, 'client_id_callback'),
            'google-drive-integration-admin', 'google_drive_integration_setting_section'
        );
        add_settings_field(
            'client_secret', 'Client Secret', array($this, 'client_secret_callback'),
            'google-drive-integration-admin', 'google_drive_integration_setting_section'
        );
        add_settings_field(
            'root_folder_id', 'Root Folder ID', array($this, 'root_folder_id_callback'),
            'google-drive-integration-admin', 'google_drive_integration_setting_section'
        );
        add_settings_field(
            'google_drive_auth', 'Authenticate', array($this, 'render_auth_button'),
            'google-drive-integration-admin', 'google_drive_integration_setting_section'
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
        $auth_url = $this->generate_auth_url(); // Method to generate the Google OAuth URL
        echo '<a href="' . esc_url($auth_url) . '" class="button button-primary" id="authorize_button">Authorize Google Drive Access</a>';
    }

    public function generate_auth_url() {
        $options = get_option('google_drive_integration_options');
        $client_id = $options['client_id'];
        $redirect_uri = admin_url('admin.php?page=google-drive-integration&action=oauth_redirect');
        $scope = 'https://www.googleapis.com/auth/drive.readonly';
    
        return 'https://accounts.google.com/o/oauth2/v2/auth?client_id=' . $client_id .
               '&redirect_uri=' . urlencode($redirect_uri) .
               '&response_type=code&scope=' . urlencode($scope) . '&access_type=offline';
    }
    
    // Back-end: Enqueue scripts
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_google-drive-integration') {
            return;
        }

        wp_enqueue_script('gapi', 'https://apis.google.com/js/api.js', array(), null, true);
        wp_enqueue_script('gis', 'https://accounts.google.com/gsi/client', array(), null, true);
        wp_enqueue_script('google-drive-integration-admin', plugin_dir_url(__FILE__) . 'js/google-drive-integration-admin.js', array('jquery', 'gapi', 'gis'), '1.0', true);

        $options = get_option('google_drive_integration_options');
        $access_token = get_option('google_drive_access_token');

        wp_localize_script('google-drive-integration-admin', 'googleDriveIntegrationAdmin', array(
            'clientId' => $options['client_id'],
            'rootFolderId' => $options['root_folder_id'],
            'accessToken' => $access_token,
            'ajaxurl' => admin_url('admin-ajax.php')
        ));
    }

    // Front-end: Enqueue scripts
    /*public function enqueue_frontend_scripts() {
       
        wp_enqueue_script('google-drive-integration-frontend', plugin_dir_url(__FILE__) . 'js/google-drive-integration-frontend.js', array('jquery'), '1.0', true);
        $options = get_option('google_drive_integration_options');
        wp_localize_script('google-drive-integration-frontend', 'googleDriveIntegrationFrontEnd', array(
            'clientId' => $options['client_id'],
            'ajaxurl' => admin_url('admin-ajax.php'),
            'rootFolderId' => $options['root_folder_id'],
        ));
    }*/

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

        // Ensure you are explicitly requesting a refresh token
        $client->setAccessType('offline');  // Request a refresh token
        $client->setPrompt('consent');  // Force Google to ask the user for consent again

        $client->addScope(Google_Drive::DRIVE_READONLY);
        $auth_url = $client->createAuthUrl();
        
        wp_redirect($auth_url);
        exit;
    }

    public function handle_oauth_redirect() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
    
        // Check if the authorization code is present
        if (isset($_GET['code'])) {
            $client = new Google_Client();
            $options = get_option('google_drive_integration_options');
            $client->setClientId($options['client_id']);
            $client->setClientSecret($options['client_secret']);
            $client->setRedirectUri(admin_url('admin.php?page=google-drive-integration&action=oauth_redirect'));
    
            try {
                // Fetch the access token using the auth code
                $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    
                // Check if there was an error in fetching the token
                if (isset($token['error'])) {
                    error_log('Error fetching access token: ' . $token['error']);
                    add_settings_error(
                        'google_drive_messages',
                        'oauth_failed',
                        __('Failed to fetch access token: ' . $token['error'], 'google-drive-integration'),
                        'error'
                    );
                } else {
                    // Store the token in WordPress
                    update_option('google_drive_access_token', $token);
    
                    add_settings_error(
                        'google_drive_messages',
                        'oauth_updated',
                        __('Permission granted and token saved.', 'google-drive-integration'),
                        'updated'
                    );
    
                    // Log the success
                    error_log('Token successfully fetched and saved.');
                }
            } catch (Exception $e) {
                // Catch any errors during the OAuth process
                error_log('Exception during OAuth process: ' . $e->getMessage());
                add_settings_error(
                    'google_drive_messages',
                    'oauth_failed',
                    __('An error occurred during the OAuth process: ' . $e->getMessage(), 'google-drive-integration'),
                    'error'
                );
            }
        } else {
            // If no authorization code is found, log the error
            error_log('No authorization code received from Google.');
            add_settings_error(
                'google_drive_messages',
                'oauth_failed',
                __('No authorization code received from Google.', 'google-drive-integration'),
                'error'
            );
        }
    
        // Set a transient for errors/success and redirect back to the settings page
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_safe_redirect(admin_url('admin.php?page=google-drive-integration&settings-updated=true'));
        exit;
    }
    

    // Function to retrieve banner images from a specified subfolder
    public function get_banner_data($subfolderPath) {
        $rootFolderId = get_option('google_drive_integration_options')['root_folder_id'];

        // Find the specified subfolder by its path (e.g., 'Banners/EnCours')
        $subfolderId = $this->find_subfolder($subfolderPath, $rootFolderId);
        
        if (!$subfolderId) {
            return []; // Return an empty array if the folder is not found
        }

        // List files inside the specified subfolder
        $files = $this->list_folder_contents($subfolderId);

        // Filter and process image files
        $banner_data = [];
        foreach ($files as $file) {
            if (strpos($file['mimeType'], 'image/') === 0) {
                $category = pathinfo($file['name'], PATHINFO_FILENAME); // Get the category from file name (without extension)
                $banner_data[] = [
                    'url' => $file['webViewLink'], // Use Google Drive's web view link for the image
                    'category' => $category
                ];
            }
        }

        return $banner_data; // Return the array of banners
    }

    // Helper function to find a subfolder based on a path (e.g., 'Banners/EnCours')
    private function find_subfolder($subfolderPath, $parentFolderId) {
        $pathParts = explode('/', $subfolderPath); // Split the path by "/"
        $currentFolderId = $parentFolderId;

        foreach ($pathParts as $folderName) {
            $subfolders = $this->list_folder_contents($currentFolderId);

            $folder = array_filter($subfolders, function($file) use ($folderName) {
                return $file['name'] === $folderName && $file['mimeType'] === 'application/vnd.google-apps.folder';
            });

            if (!$folder) {
                return false; // If folder is not found, return false
            }

            $currentFolderId = array_values($folder)[0]['id']; // Get the ID of the folder
        }

        return $currentFolderId; // Return the final subfolder ID
    }

    // Helper function to list the contents of a Google Drive folder
    private function list_folder_contents($folderId) {
        // You can reuse this logic from your previous Google Drive API setup
        $client = new Google_Client();
        $client->setAccessToken(get_option('google_drive_access_token'));
        $driveService = new Google_Service_Drive($client);

        $response = $driveService->files->listFiles([
            'q' => "'" . $folderId . "' in parents",
            'fields' => 'files(id, name, mimeType, webViewLink)',
        ]);

        return $response->getFiles();
    }

    // Function to handle the AJAX request
    public function ajax_get_banner_data() {
        if (!isset($_POST['subfolderPath'])) {
            wp_send_json_error('No subfolder path provided');
            return;
        }

        $subfolderPath = sanitize_text_field($_POST['subfolderPath']); // Get the subfolder path from the AJAX request
        $banner_data = $this->get_banner_data($subfolderPath); // Pass the subfolder path to the function
        wp_send_json_success($banner_data);
    }
}

$google_drive_integration = new GoogleDriveIntegration();