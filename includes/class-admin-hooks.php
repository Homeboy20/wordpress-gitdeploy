<?php
namespace GitHub_Deployer;

/**
 * Admin hooks class
 */
class Admin_Hooks {
    /**
     * Initialize hooks
     */
    public function init() {
        // Register admin pages
        add_action('admin_menu', array($this, 'register_admin_pages'));
        
        // Register admin actions
        add_action('admin_post_github_deployer_update_repo', array($this, 'handle_update_repo'));
        
        // Register admin notices
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    /**
     * Register admin pages
     */
    public function register_admin_pages() {
        // Main menu
        add_menu_page(
            __('GitHub Deployer', 'github-deployer'),
            __('GitHub Deployer', 'github-deployer'),
            'manage_options',
            'github-deployer',
            array($this, 'render_main_page'),
            'dashicons-update',
            30
        );
        
        // Submenu - Manage Repositories
        add_submenu_page(
            'github-deployer',
            __('Manage Repositories', 'github-deployer'),
            __('Manage Repositories', 'github-deployer'),
            'manage_options',
            'github-deployer',
            array($this, 'render_main_page')
        );
        
        // Submenu - Update Repository
        add_submenu_page(
            'github-deployer',
            __('Update Repository', 'github-deployer'),
            __('Update Repository', 'github-deployer'),
            'manage_options',
            'github-deployer-update',
            array($this, 'render_update_page')
        );
        
        // Submenu - Settings
        add_submenu_page(
            'github-deployer',
            __('Settings', 'github-deployer'),
            __('Settings', 'github-deployer'),
            'manage_options',
            'github-deployer-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Render the main admin page
     */
    public function render_main_page() {
        $admin_pages = new Admin_Pages();
        $admin_pages->render_main_page();
    }
    
    /**
     * Render the update repository page
     */
    public function render_update_page() {
        $admin_pages = new Admin_Pages();
        
        $repo_id = isset($_GET['repo_id']) ? absint($_GET['repo_id']) : 0;
        
        if (empty($repo_id)) {
            // Show repository selection form
            echo '<div class="wrap">';
            echo '<h1>' . __('Select Repository to Update', 'github-deployer') . '</h1>';
            
            $repositories = new Repositories();
            $repos = $repositories->get_repositories();
            
            if (empty($repos)) {
                echo '<div class="notice notice-info"><p>' . 
                     __('No repositories found. Please add a repository first.', 'github-deployer') . 
                     '</p></div>';
                
                echo '<p><a href="' . admin_url('admin.php?page=github-deployer') . '" class="button">' . 
                     __('Add Repository', 'github-deployer') . '</a></p>';
            } else {
                echo '<form method="get" action="">';
                echo '<input type="hidden" name="page" value="github-deployer-update">';
                
                echo '<table class="form-table">';
                echo '<tr>';
                echo '<th scope="row"><label for="repo_id">' . __('Repository', 'github-deployer') . '</label></th>';
                echo '<td>';
                echo '<select name="repo_id" id="repo_id">';
                
                foreach ($repos as $repo) {
                    echo '<option value="' . esc_attr($repo->id) . '">' . 
                         esc_html($repo->name) . ' (' . esc_html($repo->owner) . ')' . 
                         '</option>';
                }
                
                echo '</select>';
                echo '</td>';
                echo '</tr>';
                echo '</table>';
                
                echo '<p class="submit">';
                echo '<input type="submit" name="submit" id="submit" class="button button-primary" value="' . 
                     __('Select Repository', 'github-deployer') . '">';
                echo '</p>';
                
                echo '</form>';
            }
            
            echo '</div>';
        } else {
            // Show update form for the selected repository
            $admin_pages->render_update_form($repo_id);
        }
    }
    
    /**
     * Render the settings page
     */
    public function render_settings_page() {
        $admin_pages = new Admin_Pages();
        $admin_pages->render_settings_page();
    }
    
    /**
     * Handle update repository form submission
     */
    public function handle_update_repo() {
        $admin_pages = new Admin_Pages();
        $admin_pages->handle_repo_update();
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Display error messages
        if (isset($_GET['error']) && isset($_GET['page']) && $_GET['page'] === 'github-deployer') {
            $error_type = sanitize_text_field($_GET['error']);
            $error_message = isset($_GET['message']) ? sanitize_text_field(urldecode($_GET['message'])) : '';
            
            $classes = 'notice notice-error';
            $message = '';
            
            switch ($error_type) {
                case 'incompatible_archive':
                    $classes = 'notice notice-error is-dismissible';
                    
                    // If there's a custom message, use it, otherwise use default
                    if (!empty($error_message)) {
                        $message = $error_message;
                    } else {
                        $message = __('The repository does not have the required WordPress plugin or theme structure.', 'github-deployer');
                    }
                    
                    // Add helpful tips
                    $message .= '<br><br><strong>' . __('Common causes for incompatible archives:', 'github-deployer') . '</strong>';
                    $message .= '<ul style="list-style-type: disc; margin-left: 20px;">';
                    $message .= '<li>' . __('Missing plugin header in PHP files (for plugins)', 'github-deployer') . '</li>';
                    $message .= '<li>' . __('Missing style.css file with theme header (for themes)', 'github-deployer') . '</li>';
                    $message .= '<li>' . __('Repository contains only documentation or assets, not actual plugin/theme code', 'github-deployer') . '</li>';
                    $message .= '<li>' . __('Repository structure has code in a subdirectory instead of the root', 'github-deployer') . '</li>';
                    $message .= '</ul>';
                    
                    // Add recommendation
                    $message .= '<p><strong>' . __('Recommendation:', 'github-deployer') . '</strong> ';
                    $message .= __('Compare your repository structure with official WordPress guidelines or other successful plugins/themes.', 'github-deployer') . '</p>';
                    break;
                case 'deployment_failed':
                    $classes = 'notice notice-error is-dismissible';
                    $message = !empty($error_message) 
                        ? sprintf(__('Deployment failed: %s', 'github-deployer'), $error_message)
                        : __('Deployment failed. Please check your GitHub API token and repository permissions.', 'github-deployer');
                    break;
                case 'update_failed':
                    $classes = 'notice notice-error is-dismissible';
                    $message = !empty($error_message) 
                        ? sprintf(__('Update failed: %s', 'github-deployer'), $error_message)
                        : __('Repository update failed.', 'github-deployer');
                    break;
                case 'invalid_repository':
                    $classes = 'notice notice-error is-dismissible';
                    $message = __('Invalid repository ID.', 'github-deployer');
                    break;
                case 'missing_fields':
                    $classes = 'notice notice-error is-dismissible';
                    $message = __('Please fill in all required fields.', 'github-deployer');
                    break;
                default:
                    $classes = 'notice notice-error is-dismissible';
                    $message = !empty($error_message)
                        ? $error_message
                        : __('An error occurred.', 'github-deployer');
                    break;
            }
            
            printf(
                '<div class="%1$s"><p>%2$s</p></div>',
                esc_attr($classes),
                wp_kses_post($message)
            );
        }
        
        // Display success messages
        if (isset($_GET['success']) && isset($_GET['page']) && $_GET['page'] === 'github-deployer') {
            $success_type = sanitize_text_field($_GET['success']);
            $success_message = '';
            
            switch ($success_type) {
                case 'deployment':
                    $repo_name = isset($_GET['repo']) ? sanitize_text_field($_GET['repo']) : '';
                    $success_message = sprintf(
                        __('Successfully deployed %s.', 'github-deployer'),
                        '<strong>' . esc_html($repo_name) . '</strong>'
                    );
                    break;
                case 'update':
                    $repo_name = isset($_GET['repo']) ? sanitize_text_field($_GET['repo']) : '';
                    $success_message = sprintf(
                        __('Successfully updated %s.', 'github-deployer'),
                        '<strong>' . esc_html($repo_name) . '</strong>'
                    );
                    break;
                case 'repo_added':
                    $success_message = __('Repository added successfully.', 'github-deployer');
                    break;
                case 'repo_deleted':
                    $success_message = __('Repository deleted successfully.', 'github-deployer');
                    break;
                case 'settings_updated':
                    $success_message = __('Settings updated successfully.', 'github-deployer');
                    break;
                default:
                    $success_message = __('Action completed successfully.', 'github-deployer');
                    break;
            }
            
            printf(
                '<div class="notice notice-success is-dismissible"><p>%s</p></div>',
                wp_kses_post($success_message)
            );
        }
    }
} 