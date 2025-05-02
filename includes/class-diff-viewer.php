<?php
namespace GitHub_Deployer;

/**
 * Diff Viewer class
 * 
 * Shows differences between repository versions before deployment
 */
class Diff_Viewer {
    private $github_api;
    
    public function __construct($github_api = null) {
        if ($github_api) {
            $this->github_api = $github_api;
        } else {
            $settings = get_option('github_deployer_settings', array());
            $token = isset($settings['token']) ? $settings['token'] : '';
            $this->github_api = new GitHub_API($token);
        }
        
        // Register AJAX handlers
        add_action('wp_ajax_github_deployer_get_diff', array($this, 'ajax_get_diff'));
    }
    
    /**
     * Get comparison between two references in a repository
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $base Base reference
     * @param string $head Head reference
     * @return array|WP_Error Comparison data or error
     */
    public function get_comparison($owner, $repo, $base, $head) {
        $endpoint = "repos/{$owner}/{$repo}/compare/{$base}...{$head}";
        return $this->github_api->request($endpoint);
    }
    
    /**
     * Get the currently deployed version of a repository
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return string|null Current version or null if not found
     */
    public function get_current_version($owner, $repo) {
        $deployed_repos = get_option('github_deployer_deployed_repos', array());
        
        foreach ($deployed_repos as $deployed_repo) {
            if ($deployed_repo['owner'] === $owner && $deployed_repo['repo'] === $repo) {
                return $deployed_repo['ref'];
            }
        }
        
        return null;
    }
    
    /**
     * Check if a repository is deployed
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return bool True if deployed, false otherwise
     */
    public function is_deployed($owner, $repo) {
        $deployed_repos = get_option('github_deployer_deployed_repos', array());
        
        foreach ($deployed_repos as $deployed_repo) {
            if ($deployed_repo['owner'] === $owner && $deployed_repo['repo'] === $repo) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Format the comparison data for display
     * 
     * @param object $comparison Comparison data from GitHub API
     * @return array Formatted comparison data
     */
    public function format_comparison($comparison) {
        $result = array(
            'ahead_by' => $comparison->ahead_by,
            'behind_by' => $comparison->behind_by,
            'total_commits' => count($comparison->commits),
            'status' => $comparison->status,
            'changes' => array(
                'total' => 0,
                'additions' => 0,
                'deletions' => 0
            ),
            'files' => array(),
            'commits' => array()
        );
        
        // Process files
        if (!empty($comparison->files)) {
            $result['changes']['total'] = count($comparison->files);
            
            foreach ($comparison->files as $file) {
                $result['changes']['additions'] += $file->additions;
                $result['changes']['deletions'] += $file->deletions;
                
                $result['files'][] = array(
                    'filename' => $file->filename,
                    'status' => $file->status,
                    'additions' => $file->additions,
                    'deletions' => $file->deletions,
                    'changes' => $file->changes,
                    'patch' => isset($file->patch) ? $file->patch : ''
                );
            }
        }
        
        // Process commits
        if (!empty($comparison->commits)) {
            foreach ($comparison->commits as $commit) {
                $result['commits'][] = array(
                    'sha' => $commit->sha,
                    'message' => $commit->commit->message,
                    'author' => $commit->commit->author->name,
                    'date' => $commit->commit->author->date,
                    'url' => $commit->html_url
                );
            }
        }
        
        return $result;
    }
    
    /**
     * Render HTML for displaying file differences
     * 
     * @param array $file File data
     * @return string HTML for the diff
     */
    public function render_file_diff($file) {
        $html = '<div class="github-deployer-file-diff">';
        $html .= '<div class="file-header">';
        $html .= '<span class="filename">' . esc_html($file['filename']) . '</span>';
        
        // Status badge
        $status_class = 'status-' . $file['status'];
        $status_label = $this->get_status_label($file['status']);
        $html .= '<span class="status ' . esc_attr($status_class) . '">' . esc_html($status_label) . '</span>';
        
        // Changes stats
        $html .= '<span class="changes-stat">';
        if ($file['additions'] > 0) {
            $html .= '<span class="additions">+' . $file['additions'] . '</span>';
        }
        if ($file['deletions'] > 0) {
            $html .= '<span class="deletions">-' . $file['deletions'] . '</span>';
        }
        $html .= '</span>';
        
        $html .= '</div>'; // End file-header
        
        // Render the diff if we have a patch
        if (!empty($file['patch'])) {
            $html .= '<div class="file-diff-content">';
            $html .= '<pre class="diff">' . $this->colorize_diff($file['patch']) . '</pre>';
            $html .= '</div>';
        }
        
        $html .= '</div>'; // End github-deployer-file-diff
        
        return $html;
    }
    
    /**
     * Get human-readable label for file status
     * 
     * @param string $status Status code from GitHub API
     * @return string Human-readable status
     */
    private function get_status_label($status) {
        $labels = array(
            'added' => __('Added', 'github-deployer'),
            'removed' => __('Removed', 'github-deployer'),
            'modified' => __('Modified', 'github-deployer'),
            'renamed' => __('Renamed', 'github-deployer'),
            'copied' => __('Copied', 'github-deployer'),
            'changed' => __('Changed', 'github-deployer'),
            'unchanged' => __('Unchanged', 'github-deployer')
        );
        
        return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
    }
    
    /**
     * Add color highlighting to diff content
     * 
     * @param string $diff Diff content
     * @return string HTML with highlighting
     */
    private function colorize_diff($diff) {
        $lines = explode("\n", $diff);
        $result = '';
        
        foreach ($lines as $line) {
            if (empty($line)) {
                $result .= "\n";
                continue;
            }
            
            $class = '';
            $first_char = substr($line, 0, 1);
            
            switch ($first_char) {
                case '+':
                    $class = 'diff-add';
                    break;
                case '-':
                    $class = 'diff-remove';
                    break;
                case '@':
                    $class = 'diff-info';
                    break;
                default:
                    $class = 'diff-context';
                    break;
            }
            
            $result .= '<span class="' . $class . '">' . esc_html($line) . '</span>' . "\n";
        }
        
        return $result;
    }
    
    /**
     * AJAX handler for fetching diff data
     */
    public function ajax_get_diff() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'github_deployer_diff')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'github-deployer')));
        }
        
        // Verify permissions
        if (!current_user_can('install_plugins') && !current_user_can('install_themes')) {
            wp_send_json_error(array('message' => __('You do not have sufficient permissions.', 'github-deployer')));
        }
        
        // Get parameters
        $owner = isset($_POST['owner']) ? sanitize_text_field($_POST['owner']) : '';
        $repo = isset($_POST['repo']) ? sanitize_text_field($_POST['repo']) : '';
        $target_ref = isset($_POST['target_ref']) ? sanitize_text_field($_POST['target_ref']) : '';
        
        if (empty($owner) || empty($repo) || empty($target_ref)) {
            wp_send_json_error(array('message' => __('Missing required parameters.', 'github-deployer')));
        }
        
        // Get current version
        $current_ref = $this->get_current_version($owner, $repo);
        
        if (!$current_ref) {
            wp_send_json_error(array('message' => __('Repository is not currently deployed.', 'github-deployer')));
        }
        
        // Get comparison data
        $comparison = $this->get_comparison($owner, $repo, $current_ref, $target_ref);
        
        if (is_wp_error($comparison)) {
            wp_send_json_error(array(
                'message' => $comparison->get_error_message()
            ));
        }
        
        // Format comparison data
        $formatted = $this->format_comparison($comparison);
        
        // Generate HTML for each file
        $html = '';
        foreach ($formatted['files'] as $file) {
            $html .= $this->render_file_diff($file);
        }
        
        // Return the result
        wp_send_json_success(array(
            'diff_html' => $html,
            'comparison' => $formatted,
            'current_ref' => $current_ref,
            'target_ref' => $target_ref
        ));
    }
} 