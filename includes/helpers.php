<?php
namespace GitHub_Deployer;

/**
 * Check if the GitHub API token is configured
 * 
 * @return bool True if configured, false otherwise
 */
function is_github_deployer_configured() {
    $settings = get_option('github_deployer_settings', array());
    return !empty($settings['token']);
}

/**
 * Get GitHub repository information
 * 
 * @param string $owner Repository owner/organization
 * @param string $repo Repository name
 * @return object|WP_Error Repository data or error
 */
function get_github_repo_info($owner, $repo) {
    $settings = get_option('github_deployer_settings', array());
    $token = isset($settings['token']) ? $settings['token'] : '';
    $api = new GitHub_API($token);
    return $api->get_repo($owner, $repo);
}

/**
 * Get GitHub repository branches
 * 
 * @param string $owner Repository owner/organization
 * @param string $repo Repository name
 * @return array|WP_Error Branches data or error
 */
function get_github_branches($owner, $repo) {
    $settings = get_option('github_deployer_settings', array());
    $token = isset($settings['token']) ? $settings['token'] : '';
    $api = new GitHub_API($token);
    return $api->get_branches($owner, $repo);
}

/**
 * Get GitHub repository tags
 * 
 * @param string $owner Repository owner/organization
 * @param string $repo Repository name
 * @return array|WP_Error Tags data or error
 */
function get_github_tags($owner, $repo) {
    $settings = get_option('github_deployer_settings', array());
    $token = isset($settings['token']) ? $settings['token'] : '';
    $api = new GitHub_API($token);
    return $api->get_tags($owner, $repo);
}

/**
 * Format error message
 * 
 * @param WP_Error $error WordPress error object
 * @return string Formatted error message
 */
function format_error_message(\WP_Error $error) {
    $message = $error->get_error_message();
    $data = $error->get_error_data();
    
    if (!empty($data['status'])) {
        return sprintf(__('Error %d: %s', 'github-deployer'), $data['status'], $message);
    }
    
    return $message;
} 