<?php
namespace GitHub_Deployer;

class Settings {
    public function __construct() {
        // Make sure settings are registered early
        add_action('admin_init', array($this, 'settings_init'), 5);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('GitHub Deployer', 'github-deployer'),
            __('GitHub Deployer', 'github-deployer'),
            'manage_options',
            'github-deployer',
            array($this, 'render_settings_page'),
            'dashicons-download'
        );
    }
    
    public function sanitize_settings($input) {
        $output = array();
        if (isset($input['token'])) {
            $output['token'] = sanitize_text_field($input['token']);
        }
        if (isset($input['webhook_secret'])) {
            $output['webhook_secret'] = sanitize_text_field($input['webhook_secret']);
        }
        return $output;
    }

    public function settings_init() {
        register_setting('github_deployer', 'github_deployer_settings', array(
            'sanitize_callback' => array($this, 'sanitize_settings'),
        ));
        
        add_settings_section(
            'github_deployer_settings_section',
            __('GitHub API Settings', 'github-deployer'),
            array($this, 'settings_section_callback'),
            'github_deployer'
        );
        
        add_settings_field(
            'github_deployer_token',
            __('GitHub Personal Access Token', 'github-deployer'),
            array($this, 'token_field_callback'),
            'github_deployer',
            'github_deployer_settings_section'
        );
        
        add_settings_field(
            'github_deployer_webhook_secret',
            __('Webhook Secret', 'github-deployer'),
            array($this, 'webhook_secret_field_callback'),
            'github_deployer',
            'github_deployer_settings_section'
        );
    }
    
    public function settings_section_callback() {
        echo '<p>' . __('Enter your GitHub Personal Access Token with the "repo" scope to enable repository access.', 'github-deployer') . '</p>';
        echo '<p>' . __('For webhook integrations, you can set a secret token for enhanced security.', 'github-deployer') . '</p>';
        echo '<p>' . __('<strong>Note:</strong> For private repositories, your token must have the full "repo" scope to access private repository content.', 'github-deployer') . '</p>';
    }
    
    public function token_field_callback() {
        $options = get_option('github_deployer_settings', array());
        $token = isset($options['token']) ? $options['token'] : '';
        
        printf(
            '<input type="password" id="github_deployer_token" name="github_deployer_settings[token]" value="%s" class="regular-text" />',
            esc_attr($token)
        );
        echo '<p class="description">' . __('Create a token at https://github.com/settings/tokens', 'github-deployer') . '</p>';
        echo '<p class="description">' . __('Required scopes: <strong>repo</strong> (for private repositories access)', 'github-deployer') . '</p>';
    }
    
    public function webhook_secret_field_callback() {
        $options = get_option('github_deployer_settings', array());
        $webhook_secret = isset($options['webhook_secret']) ? $options['webhook_secret'] : '';
        
        printf(
            '<input type="password" id="github_deployer_webhook_secret" name="github_deployer_settings[webhook_secret]" value="%s" class="regular-text" />',
            esc_attr($webhook_secret)
        );
        echo '<p class="description">' . __('Optional: Set a secret token for GitHub webhook security', 'github-deployer') . '</p>';
        echo '<p class="description">' . __('Webhook URL: ', 'github-deployer') . '<code>' . get_rest_url(null, 'github-deployer/v1/webhook') . '</code></p>';
    }
    
    public function get($key, $default = '') {
        $options = get_option('github_deployer_settings', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }
    
    public function render_settings_page() {
        // Check for permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get current tab
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'deploy';
        
        // Display settings form
        $tpl_dir = GITHUB_DEPLOYER_PLUGIN_DIR . 'templates/admin/';

        if ( file_exists( $tpl_dir . 'header.php' ) ) {
            require_once $tpl_dir . 'header.php';
        }

        // Display active tab content
        $tab_templates = array(
            'repositories' => 'repositories.php',
            'settings'     => 'settings-tab.php',
            'connect'      => 'connect.php',
            'deploy'       => 'deploy.php',
        );
        $tab_file = isset( $tab_templates[ $active_tab ] ) ? $tab_templates[ $active_tab ] : 'deploy.php';
        if ( file_exists( $tpl_dir . $tab_file ) ) {
            require_once $tpl_dir . $tab_file;
        }
        
        $footer_template = GITHUB_DEPLOYER_PLUGIN_DIR . 'templates/admin/footer.php';
        if ( file_exists( $footer_template ) ) {
            require_once $footer_template;
        }
    }
    
    public function enqueue_assets($hook) {
        if (strpos($hook, 'github-deployer') === false) {
            return;
        }
        
        wp_enqueue_style(
            'github-deployer-admin',
            GITHUB_DEPLOYER_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            GITHUB_DEPLOYER_VERSION
        );
        
        wp_enqueue_script(
            'github-deployer-admin',
            GITHUB_DEPLOYER_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            GITHUB_DEPLOYER_VERSION,
            true
        );
        
        wp_localize_script('github-deployer-admin', 'github_deployer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('github_deployer_nonce'),
            'strings' => array(
                'loading' => __('Loading repository information...', 'github-deployer'),
                'error' => __('Error loading repository data. Please check the repository owner and name.', 'github-deployer'),
                'branches' => __('Branches', 'github-deployer'),
                'tags' => __('Tags', 'github-deployer'),
                'select_branch' => __('Select a branch', 'github-deployer'),
                'select_tag' => __('Select a tag', 'github-deployer'),
                'plugin_exists' => __('This plugin is already installed. Checking "Update Existing" will update it with the selected version.', 'github-deployer'),
                'theme_exists' => __('This theme is already installed. Checking "Update Existing" will update it with the selected version.', 'github-deployer'),
                'deploy_button' => __('Deploy', 'github-deployer'),
                'update_button' => __('Update', 'github-deployer'),
                'deploy_and_track_button' => __('Deploy and Track', 'github-deployer'),
                'no_license' => __('No license specified', 'github-deployer'),
                'repo_already_tracked' => __('This repository is already being tracked for auto-updates.', 'github-deployer'),
                'auto_update_enabled' => __('Auto-updates enabled. This repository will be checked for updates hourly.', 'github-deployer'),
                'auto_update_disabled' => __('Auto-updates disabled for this repository.', 'github-deployer')
            )
        ));
    }
} 