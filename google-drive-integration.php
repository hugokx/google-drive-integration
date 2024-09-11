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
        add_action('admin_init', array($this, 'handle_admin_actions'));
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

function get_drive_folder_contents($folder_id = null) {
    $options = get_option('google_drive_integration_options');
    $folder_id = $folder_id ?: $options['root_folder_id'];
    // Implement logic to fetch folder contents using the Google Drive API
    return array('This is a placeholder for folder contents');
}