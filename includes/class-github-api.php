<?php
namespace GitHub_Deployer;

class GitHub_API {
    private $token;
    private $oauth_token;
    private $auth_type = 'token'; // 'token' or 'oauth'
    private $api_url = 'https://api.github.com';
    private $token_expiration = null;
    
    public function __construct($token = '', $auth_type = 'token') {
        $this->token = $token;
        $this->auth_type = $auth_type;
        
        // Check token expiration if available
        if (!empty($token)) {
            $this->check_token_expiration();
        }
    }
    
    public function set_token($token, $auth_type = 'token') {
        $this->token = $token;
        $this->auth_type = $auth_type;
        
        // Check token expiration when setting a new token
        if (!empty($token)) {
            $this->check_token_expiration();
        }
    }
    
    public function set_oauth_token($token) {
        $this->oauth_token = $token;
        $this->auth_type = 'oauth';
    }
    
    public function get_auth_type() {
        return $this->auth_type;
    }
    
    public function is_token_expiring() {
        if (empty($this->token_expiration)) {
            return false;
        }
        
        // Return true if token expires in less than 7 days
        return ($this->token_expiration - time()) < (7 * 24 * 60 * 60);
    }
    
    private function check_token_expiration() {
        if ($this->auth_type === 'token') {
            // Check token metadata through GitHub API
            $response = $this->request('user');
            
            // If we have headers, check for token expiration
            if (!\is_wp_error($response) && isset($response->headers)) {
                // Store expiration time if available
                if (isset($response->headers['github-authentication-token-expiration'])) {
                    $this->token_expiration = strtotime($response->headers['github-authentication-token-expiration']);
                }
            }
        }
    }
    
    public function get_repo($owner, $repo) {
        $endpoint = "repos/{$owner}/{$repo}";
        return $this->request($endpoint);
    }
    
    public function get_branches($owner, $repo) {
        return $this->request("repos/{$owner}/{$repo}/branches");
    }
    
    public function get_tags($owner, $repo) {
        return $this->request("repos/{$owner}/{$repo}/tags");
    }
    
    /**
     * Get releases for a repository
     *
     * @param string $owner Owner of the repository.
     * @param string $repo  Repository name.
     * @return array|\WP_Error List of releases or error.
     */
    public function get_releases($owner, $repo) {
        return $this->request("repos/{$owner}/{$repo}/releases");
    }
    
    /**
     * Get the latest commit from a branch
     *
     * @param string $owner Owner of the repository.
     * @param string $repo  Repository name.
     * @param string $branch Branch name.
     * @return array|\WP_Error Latest commit data or error.
     */
    public function get_latest_commit($owner, $repo, $branch = 'main') {
        $url = "repos/{$owner}/{$repo}/commits/{$branch}";
        
        $response = $this->request($url);
        
        if (\is_wp_error($response)) {
            return $response;
        }
        
        // Convert stdClass to array
        $commit_data = json_decode(json_encode($response), true);
        
        return $commit_data;
    }
    
    /**
     * Create a new branch in a repository
     *
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $branch_name Name for the new branch
     * @param string $sha SHA of the commit to branch from
     * @return array|\WP_Error Response data or error
     */
    public function create_branch($owner, $repo, $branch_name, $sha) {
        $endpoint = "repos/{$owner}/{$repo}/git/refs";
        $data = array(
            'ref' => "refs/heads/{$branch_name}",
            'sha' => $sha
        );
        
        return $this->post_request($endpoint, $data);
    }
    
    /**
     * Create or update a file in a repository
     *
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $path Path to the file in the repository
     * @param string $content File content (will be base64 encoded)
     * @param string $message Commit message
     * @param string $branch Branch to commit to
     * @param string $sha SHA of the file to update (required for updates, omit for new files)
     * @return array|WP_Error Response data or error
     */
    public function update_file($owner, $repo, $path, $content, $message, $branch, $sha = null) {
        $endpoint = "repos/{$owner}/{$repo}/contents/{$path}";
        
        $data = array(
            'message' => $message,
            'content' => base64_encode($content),
            'branch' => $branch
        );
        
        // If SHA is provided, we're updating an existing file
        if (!empty($sha)) {
            $data['sha'] = $sha;
        }
        
        return $this->put_request($endpoint, $data);
    }
    
    /**
     * Get file contents from a repository
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $path File path
     * @param string $ref Branch, tag or commit SHA (default: main branch)
     * @return stdClass|WP_Error File contents object or error
     */
    public function get_file_contents($owner, $repo, $path, $ref = '') {
        $endpoint = "repos/{$owner}/{$repo}/contents/{$path}";
        
        if (!empty($ref)) {
            $endpoint .= "?ref={$ref}";
        }
        
        $response = $this->request($endpoint);
        
        if (\is_wp_error($response)) {
            return $response;
        }
        
        // If it's a binary file, the content will be base64 encoded
        if (isset($response->content) && isset($response->encoding) && $response->encoding === 'base64') {
            $response->decoded_content = base64_decode($response->content);
        }
        
        return $response;
    }
    
    /**
     * Create a new commit by updating multiple files
     *
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param array $files Array of files to update (path => content)
     * @param string $message Commit message
     * @param string $branch Branch to commit to
     * @param string $base_tree SHA of the base tree to update
     * @return array|WP_Error Response data or error
     */
    public function create_commit($owner, $repo, $files, $message, $branch, $base_tree) {
        // First, create a tree with the new files
        $tree_items = array();
        
        foreach ($files as $path => $content) {
            $tree_items[] = array(
                'path' => $path,
                'mode' => '100644', // File mode (100644 for file)
                'type' => 'blob',
                'content' => $content
            );
        }
        
        $tree_data = array(
            'base_tree' => $base_tree,
            'tree' => $tree_items
        );
        
        $tree_response = $this->post_request("repos/{$owner}/{$repo}/git/trees", $tree_data);
        
        if (is_wp_error($tree_response)) {
            return $tree_response;
        }
        
        // Get the current commit that the branch points to
        $ref_response = $this->request("repos/{$owner}/{$repo}/git/refs/heads/{$branch}");
        
        if (is_wp_error($ref_response)) {
            return $ref_response;
        }
        
        $parent_sha = $ref_response->object->sha;
        
        // Create the commit
        $commit_data = array(
            'message' => $message,
            'tree' => $tree_response->sha,
            'parents' => array($parent_sha)
        );
        
        $commit_response = $this->post_request("repos/{$owner}/{$repo}/git/commits", $commit_data);
        
        if (is_wp_error($commit_response)) {
            return $commit_response;
        }
        
        // Update the branch reference to point to the new commit
        $ref_update_data = array(
            'sha' => $commit_response->sha
        );
        
        return $this->patch_request("repos/{$owner}/{$repo}/git/refs/heads/{$branch}", $ref_update_data);
    }
    
    /**
     * Commit and push changes to a repository
     *
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param array $file_updates Array of file updates with format [path => [content, message]]
     * @param string $branch Branch to commit to
     * @param string $commit_message Main commit message
     * @return array|WP_Error Response data or error
     */
    public function commit_and_push($owner, $repo, $file_updates, $branch = 'main', $commit_message = '') {
        if (empty($file_updates)) {
            return new \WP_Error('no_files', __('No files to update', 'github-deployer'));
        }
        
        // If no commit message provided, use a default one
        if (empty($commit_message)) {
            $commit_message = sprintf(
                __('Update %d file(s) via GitHub Deployer', 'github-deployer'),
                count($file_updates)
            );
        }
        
        // For single file updates, use the simpler contents API
        if (count($file_updates) === 1) {
            $file_path = array_key_first($file_updates);
            $file_data = reset($file_updates);
            
            $content = is_array($file_data) ? $file_data['content'] : $file_data;
            $message = is_array($file_data) && isset($file_data['message']) ? 
                       $file_data['message'] : $commit_message;
            
            // Get the current file to get its SHA (if it exists)
            $current_file = $this->get_file_contents($owner, $repo, $file_path);
            $sha = is_wp_error($current_file) ? null : $current_file->sha;
            
            return $this->update_file($owner, $repo, $file_path, $content, $message, $branch, $sha);
        }
        
        // For multiple files, use the Git Data API
        // First, get the latest commit SHA for the branch
        $latest_commit = $this->get_latest_commit($owner, $repo, $branch);
        
        if (is_wp_error($latest_commit)) {
            return $latest_commit;
        }
        
        $base_tree = $latest_commit['tree']['sha'];
        
        // Prepare files for the commit
        $files = [];
        foreach ($file_updates as $path => $data) {
            $content = is_array($data) ? $data['content'] : $data;
            $files[$path] = $content;
        }
        
        // Create the commit and update the branch
        return $this->create_commit($owner, $repo, $files, $commit_message, $branch, $base_tree);
    }
    
    /**
     * Get repositories for the authenticated user
     * 
     * @param int $page Page number to fetch
     * @param int $per_page Number of repositories per page
     * @return array|\WP_Error List of repositories or error
     */
    public function get_user_repos($page = 1, $per_page = 30) {
        $response = $this->request("user/repos?sort=updated&page={$page}&per_page={$per_page}");
        
        // Check if we got a proper response with items
        if (!\is_wp_error($response) && isset($response->items)) {
            return $response->items;
        }
        
        return $response;
    }
    
    /**
     * Get repositories for a specific user or organization
     * 
     * @param string $username GitHub username or organization name
     * @param int $page Page number to fetch
     * @param int $per_page Number of repositories per page
     * @return array|\WP_Error List of repositories or error
     */
    public function get_user_public_repos($username, $page = 1, $per_page = 30) {
        $response = $this->request("users/{$username}/repos?sort=updated&page={$page}&per_page={$per_page}");
        
        // Check if we got a proper response with items
        if (!\is_wp_error($response) && isset($response->items)) {
            return $response->items;
        }
        
        return $response;
    }
    
    /**
     * Get repositories for an organization
     * 
     * @param string $org Organization name
     * @param int $page Page number to fetch
     * @param int $per_page Number of repositories per page
     * @return array|\WP_Error List of repositories or error
     */
    public function get_org_repos($org, $page = 1, $per_page = 30) {
        $response = $this->request("orgs/{$org}/repos?sort=updated&page={$page}&per_page={$per_page}");
        
        // Check if we got a proper response with items
        if (!\is_wp_error($response) && isset($response->items)) {
            return $response->items;
        }
        
        return $response;
    }
    
    /**
     * Search for repositories by query
     * 
     * @param string $query Search query
     * @param int $page Page number to fetch
     * @param int $per_page Number of repositories per page
     * @return array|\WP_Error Search results or error
     */
    public function search_repos($query, $page = 1, $per_page = 30) {
        $query = urlencode($query);
        return $this->request("search/repositories?q={$query}&sort=updated&page={$page}&per_page={$per_page}");
    }
    
    /**
     * Check connection status to a repository
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return bool|\WP_Error True if connected, false if disconnected, WP_Error on error
     */
    public function check_connection_status($owner, $repo) {
        $result = $this->get_repo($owner, $repo);
        
        if (\is_wp_error($result)) {
            return $result;
        }
        
        // Check if we can reach the repository
        return true;
    }
    
    /**
     * Get the ZIP download URL for a repository
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $ref Branch, tag, or commit reference
     * @return string URL to download the repository ZIP
     */
    public function get_zip_url($owner, $repo, $ref) {
        // For private repositories, we need to use the authenticated API
        if (!empty($this->token) || (!empty($this->oauth_token) && $this->auth_type === 'oauth')) {
            // Return a special format that tells the deployer to use authenticated download
            return "github-api://{$owner}/{$repo}/{$ref}";
        }
        
        // For public repositories, use the standard GitHub URL
        return "https://github.com/{$owner}/{$repo}/archive/{$ref}.zip";
    }
    
    /**
     * Get archive download URL from the GitHub API (for private repos)
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $ref Branch, tag, or commit reference
     * @return string|\WP_Error URL for downloading the archive
     */
    public function get_archive_download_url($owner, $repo, $ref) {
        $endpoint = "repos/{$owner}/{$repo}/zipball/{$ref}";
        // Make the request but don't follow the redirect (redirection=0 set in request method)
        $response = $this->request($endpoint, true); 
        
        // Check if the request resulted in an error
        if (is_wp_error($response)) {
            // Check if the error object contains headers (it might if process_response added them)
            $headers = isset($response->error_data['headers']) ? $response->error_data['headers'] : null;
            // Even on error, check if maybe a redirect URL was somehow captured
            if ($headers && isset($headers['location'])) {
                 // Log this unusual case but attempt to return the URL
                 error_log("GitHub Deployer: Got redirect URL despite WP_Error in get_archive_download_url for {$owner}/{$repo}/{$ref}");
                 return $headers['location'];
            }
            // Otherwise, return the original error
            return $response; 
        }

        // If no error, check the response object (processed by process_response)
        // for the location header
        if (isset($response->headers) && isset($response->headers['location'])) {
            return $response->headers['location']; // Return the URL string directly
        }

        // If no error and no location header, it's an unexpected situation
        error_log("GitHub Deployer: Failed to get redirect URL from get_archive_download_url for {$owner}/{$repo}/{$ref}. Response: " . print_r($response, true));
        return new \WP_Error(
            'github_api_no_redirect_url',
             __('Could not retrieve the download URL for the private repository archive. The API did not provide a redirect location.', 'github-deployer')
        );
    }
    
    /**
     * Make a POST request to the GitHub API
     *
     * @param string $endpoint API endpoint
     * @param array $data Data to send in the request
     * @return mixed|\WP_Error Response data or error
     */
    private function post_request($endpoint, $data) {
        $url = trailingslashit($this->api_url) . $endpoint;
        
        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'WordPress/' . \get_bloginfo('version') . '; ' . \get_bloginfo('url'),
                'Content-Type' => 'application/json'
            ),
            'body' => \wp_json_encode($data)
        );
        
        // Add authentication based on type
        if ($this->auth_type === 'oauth' && !empty($this->oauth_token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->oauth_token;
        } elseif (!empty($this->token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->token;
        }
        
        return $this->process_response(\wp_remote_post($url, $args));
    }
    
    /**
     * Make a PUT request to the GitHub API
     *
     * @param string $endpoint API endpoint
     * @param array $data Data to send in the request
     * @return mixed|\WP_Error Response data or error
     */
    private function put_request($endpoint, $data) {
        $url = trailingslashit($this->api_url) . $endpoint;
        
        $args = array(
            'method' => 'PUT',
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'WordPress/' . \get_bloginfo('version') . '; ' . \get_bloginfo('url'),
                'Content-Type' => 'application/json'
            ),
            'body' => \wp_json_encode($data)
        );
        
        // Add authentication based on type
        if ($this->auth_type === 'oauth' && !empty($this->oauth_token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->oauth_token;
        } elseif (!empty($this->token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->token;
        }
        
        return $this->process_response(\wp_remote_request($url, $args));
    }
    
    /**
     * Make a PATCH request to the GitHub API
     *
     * @param string $endpoint API endpoint
     * @param array $data Data to send in the request
     * @return mixed|\WP_Error Response data or error
     */
    private function patch_request($endpoint, $data) {
        $url = trailingslashit($this->api_url) . $endpoint;
        
        $args = array(
            'method' => 'PATCH',
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'WordPress/' . \get_bloginfo('version') . '; ' . \get_bloginfo('url'),
                'Content-Type' => 'application/json'
            ),
            'body' => \wp_json_encode($data)
        );
        
        // Add authentication based on type
        if ($this->auth_type === 'oauth' && !empty($this->oauth_token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->oauth_token;
        } elseif (!empty($this->token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->token;
        }
        
        return $this->process_response(\wp_remote_request($url, $args));
    }
    
    /**
     * Process an API response
     *
     * @param array|\WP_Error $response WordPress HTTP API response
     * @return array|\WP_Error Processed response data or error
     */
    private function process_response($response) {
        // Fix for handling both array and object responses from GitHub API
        if (\is_wp_error($response)) {
            return $response;
        }
        
        $response_code = \wp_remote_retrieve_response_code($response);
        $headers = \wp_remote_retrieve_headers($response);
        $body = \wp_remote_retrieve_body($response);

        // Store essential headers regardless of response code initially
        $response_headers = array(
            'x-ratelimit-remaining' => isset($headers['x-ratelimit-remaining']) ? $headers['x-ratelimit-remaining'] : null,
            'github-authentication-token-expiration' => isset($headers['github-authentication-token-expiration']) ? $headers['github-authentication-token-expiration'] : null,
            'x-oauth-scopes' => isset($headers['x-oauth-scopes']) ? $headers['x-oauth-scopes'] : null,
            'location' => isset($headers['location']) ? $headers['location'] : null
        );
        
        // Handle Redirects (like zipball URL) - return just headers including Location
        if ($response_code >= 300 && $response_code < 400 && isset($response_headers['location'])) {
            // For redirects, we primarily care about the location and rate limits
            // Return a simple object containing only the necessary headers
            $redirect_data = new \stdClass();
            $redirect_data->headers = $response_headers;
            // Apply filter even for redirects, maybe someone wants to modify headers
            return \apply_filters('github_deployer_api_response', $redirect_data); 
        }

        // Check for API rate limits
        if (isset($response_headers['x-ratelimit-remaining']) && intval($response_headers['x-ratelimit-remaining']) < 10) {
            error_log('GitHub Deployer: API rate limit getting low. ' . $response_headers['x-ratelimit-remaining'] . ' requests remaining.');
        }
        
        // Try to decode JSON body only for non-redirect responses
        $data = json_decode($body);
        
        // Handle >= 400 Errors
        if ($response_code >= 400) {
            $error_message = is_object($data) && isset($data->message) ? $data->message : 'Unknown error (' . $response_code . ') from body: ' . $body;
            $error_data = is_object($data) ? $data : null; // Pass decoded data if available
            $wp_error = new \WP_Error(
                'github_api_error',
                sprintf(\__('GitHub API Error (%d): %s', 'github-deployer'), $response_code, $error_message),
                $error_data
            );
            // Attach headers to the error object for context
            $wp_error->add_data(['headers' => $response_headers], 'github_api_error');
            return $wp_error;
        }
        
        // Process Successful (2xx) responses
        // If data is an object, add headers as a property
        if (is_object($data)) {
            $data->headers = $response_headers;
        } 
        // If data is an array, convert to object with headers
        elseif (is_array($data)) {
            // Save the array
            $array_data = $data;
            
            // Create an object to hold both the array and headers
            $data = new \stdClass();
            $data->items = $array_data;
            $data->headers = $response_headers;
        } 
        // Handle cases where body is not JSON but response is 2xx (rare for GitHub API)
        elseif ($data === null && json_last_error() !== JSON_ERROR_NONE) {
             $data = new \stdClass(); // Create an object anyway
             $data->raw_body = $body; // Store the raw body
             $data->headers = $response_headers;
        } else {
            // If json_decode resulted in something else (e.g. null, false, number) - wrap it
             $decoded_value = $data; // Keep original decoded value
             $data = new \stdClass();
             $data->value = $decoded_value;
             $data->headers = $response_headers;
        }
        
        // Apply our API response filter for additional processing
        $data = \apply_filters('github_deployer_api_response', $data);
        
        return $data;
    }
    
    /**
     * Send request to GitHub API
     *
     * @param string $endpoint API endpoint to request
     * @param bool $include_redirect_headers Whether to include redirect headers in response
     * @return mixed Response data or WP_Error
     */
    private function request($endpoint, $include_redirect_headers = false) {
        $url = trailingslashit($this->api_url) . $endpoint;
        $url = \apply_filters('github_deployer_api_url', $url, $endpoint);
        
        $args = array(
            'timeout' => 30,
            'headers' => array(
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'WordPress/' . \get_bloginfo('version') . '; ' . \get_bloginfo('url'),
            )
        );
        
        // Check if token is empty
        if (empty($this->token) && empty($this->oauth_token)) {
            // For endpoints that might contain private repository data, return error
            if (strpos($endpoint, 'repos/') === 0) {
                // Extract owner and repo from endpoint
                $parts = explode('/', trim($endpoint, '/'));
                if (count($parts) >= 3) {
                    $owner = $parts[1];
                    $repo = $parts[2];
                    return new \WP_Error(
                        'github_api_auth_error',
                        sprintf(
                            \__('No GitHub authentication token provided. For private repositories like %s/%s, you need to configure a valid token with "repo" scope.', 'github-deployer'),
                            $owner,
                            $repo
                        )
                    );
                }
            }
            
            return new \WP_Error(
                'github_api_auth_error',
                \__('No GitHub authentication token provided. For private repositories, you need to configure a valid token with "repo" scope.', 'github-deployer')
            );
        }
        
        // Add authentication based on type
        if ($this->auth_type === 'oauth' && !empty($this->oauth_token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->oauth_token;
        } elseif (!empty($this->token)) {
            $args['headers']['Authorization'] = 'Bearer ' . $this->token;
        }
        
        // For archive downloads, we need to include redirect headers
        if ($include_redirect_headers) {
            $args['redirection'] = 0; // Don't follow redirects
        }
        
        $response = \wp_remote_get($url, $args);
        return $this->process_response($response);
    }
} 