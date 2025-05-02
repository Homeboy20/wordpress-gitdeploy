<?php
/**
 * Auto Updater Class
 *
 * @package GitHub_Deployer
 */

namespace GitHub_Deployer;

/**
 * Auto Updater class
 * Responsible for checking repositories for updates and deploying them automatically
 */
class Auto_Updater {

    /**
     * Plugin instance
     *
     * @var Plugin
     */
    private $plugin;

    /**
     * Constructor
     *
     * @param Plugin $plugin Plugin instance.
     */
    public function __construct($plugin) {
        $this->plugin = $plugin;
        
        // Register the auto update check hook
        add_action('github_deployer_check_updates', array($this, 'check_updates'));
    }

    /**
     * Check for updates for all repositories set to auto-update
     */
    public function check_updates() {
        $repositories = $this->get_tracked_repos();
        
        if (empty($repositories)) {
            return;
        }
        
        foreach ($repositories as $repository) {
            $this->check_repository_update($repository);
        }
    }

    /**
     * Get all repositories that are set for auto updates
     * 
     * @return array Array of repository objects
     */
    public function get_tracked_repos() {
        $repos = new Repositories();
        $auto_update_repos = $repos->get_auto_update_repositories();
        
        // If we have no auto-updating repositories, check if we should set up Homeboy20/wordpress-gitdeploy
        if (empty($auto_update_repos)) {
            $repo_manager = $this->plugin->get_repository_manager();
            $all_repos = $repo_manager->get_deployed_repositories();
            
            // Look for the Homeboy20/wordpress-gitdeploy repository
            foreach ($all_repos as $repo) {
                if ($repo['owner'] === 'Homeboy20' && $repo['repo'] === 'wordpress-gitdeploy') {
                    // Found it, but it's not set for auto-update yet
                    if (!isset($repo['is_tracked']) || !$repo['is_tracked']) {
                        // Enable auto-update for it
                        $db_repos = $repos->get_repositories();
                        foreach ($db_repos as $db_repo) {
                            if ($db_repo->owner === 'Homeboy20' && $db_repo->name === 'wordpress-gitdeploy') {
                                $repos->update_repository($db_repo->id, array(
                                    'auto_update' => 1
                                ));
                                
                                // Get the updated list
                                return $repos->get_auto_update_repositories();
                            }
                        }
                    }
                }
            }
        }
        
        return $auto_update_repos;
    }

    /**
     * Check if a specific repository has updates
     * 
     * @param object $repository Repository object
     * @return bool True if updates were found and applied, false otherwise
     */
    public function check_repository_update($repository) {
        $github_api = $this->plugin->get_github_api();
        $deployer = $this->plugin->get_deployer();
        
        // Make sure we have all required data
        if (!isset($repository->owner) || !isset($repository->name) || !isset($repository->branch)) {
            error_log('GitHub Deployer: Invalid repository data for update check');
            return false;
        }
        
        // Verify repository is still valid and accessible
        $repo_info = $github_api->get_repo($repository->owner, $repository->name);
        if (is_wp_error($repo_info)) {
            error_log(sprintf(
                'GitHub Deployer: Repository %s/%s is not accessible: %s',
                $repository->owner,
                $repository->name,
                $repo_info->get_error_message()
            ));
            return false;
        }
        
        // Get the latest commit from the remote repository
        $latest_commit = $github_api->get_latest_commit($repository->owner, $repository->name, $repository->branch);
        
        if (is_wp_error($latest_commit)) {
            // Log the error
            error_log(sprintf(
                'GitHub Deployer: Failed to check for updates for %s/%s: %s',
                $repository->owner,
                $repository->name,
                $latest_commit->get_error_message()
            ));
            return false;
        }
        
        // Get the latest deployed commit SHA from the database
        $last_deployed_sha = get_option('github_deployer_last_commit_' . $repository->id, '');
        
        // If the latest commit is different from the last deployed one, update
        if (!empty($latest_commit['sha']) && $latest_commit['sha'] !== $last_deployed_sha) {
            // Log update intent
            error_log(sprintf(
                'GitHub Deployer: Found new commit for %s/%s, attempting to update from %s to %s',
                $repository->owner,
                $repository->name,
                substr($last_deployed_sha, 0, 8),
                substr($latest_commit['sha'], 0, 8)
            ));
            
            // Deploy the updated repository
            $result = $deployer->deploy($repository->id);
            
            if (is_wp_error($result)) {
                // Log the error
                error_log(sprintf(
                    'GitHub Deployer: Failed to auto-update %s/%s: %s',
                    $repository->owner,
                    $repository->name,
                    $result->get_error_message()
                ));
                return false;
            }
            
            // Update the last deployed commit SHA
            update_option('github_deployer_last_commit_' . $repository->id, $latest_commit['sha']);
            
            // Log the successful update
            error_log(sprintf(
                'GitHub Deployer: Successfully auto-updated %s/%s to commit %s',
                $repository->owner,
                $repository->name,
                $latest_commit['sha']
            ));
            
            return true;
        }
        
        return false;
    }

    /**
     * Enable auto-update for a repository
     * 
     * @param int $repo_id Repository ID
     * @return bool True on success, false on failure
     */
    public function enable_auto_update($repo_id) {
        $repositories = new Repositories();
        
        // Update the repository to enable auto-update
        $result = $repositories->update_repository($repo_id, array(
            'auto_update' => 1
        ));
        
        if ($result) {
            // Get the repository data
            $repository = $repositories->get_repository($repo_id);
            
            if ($repository) {
                // Log the auto-update enablement
                error_log(sprintf(
                    'GitHub Deployer: Auto-update enabled for %s/%s',
                    $repository->owner,
                    $repository->name
                ));
            }
        }
        
        return $result;
    }

    /**
     * Disable auto-update for a repository
     * 
     * @param int $repo_id Repository ID
     * @return bool True on success, false on failure
     */
    public function disable_auto_update($repo_id) {
        $repositories = new Repositories();
        
        // Update the repository to disable auto-update
        $result = $repositories->update_repository($repo_id, array(
            'auto_update' => 0
        ));
        
        if ($result) {
            // Get the repository data
            $repository = $repositories->get_repository($repo_id);
            
            if ($repository) {
                // Log the auto-update disablement
                error_log(sprintf(
                    'GitHub Deployer: Auto-update disabled for %s/%s',
                    $repository->owner,
                    $repository->name
                ));
            }
        }
        
        return $result;
    }
} 