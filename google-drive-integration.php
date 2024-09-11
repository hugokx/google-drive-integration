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

    public function enqueue_scripts() {
        wp_enqueue_script('google-api', 'https://apis.google.com/js/api.js');
        wp_enqueue_script('google-drive-integration', plugin_dir_url(__FILE__) . 'js/google-drive-integration.js', array('jquery', 'google-api'), '1.0', true);
        wp_localize_script('google-drive-integration', 'googleDriveIntegration', array(
            'clientId' => $this->options['client_id'],
            'rootFolderId' => $this->options['root_folder_id']
        ));
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