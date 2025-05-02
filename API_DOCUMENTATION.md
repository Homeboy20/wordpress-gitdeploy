# GitHub Deployer API Documentation

This document provides detailed information about the GitHub Deployer plugin's classes, methods, and hooks for developers who want to extend or integrate with the plugin.

## Classes and Methods

### GitHub_Deployer\GitHub_API

This class handles all communication with the GitHub API.

#### Methods

##### `__construct($token = '')`
- **Description**: Initializes the GitHub API class with an optional token.
- **Parameters**:
  - `$token` (string): GitHub personal access token

##### `set_token($token)`
- **Description**: Sets the GitHub API token after instantiation.
- **Parameters**:
  - `$token` (string): GitHub personal access token

##### `get_repo($owner, $repo)`
- **Description**: Retrieves information about a GitHub repository.
- **Parameters**:
  - `$owner` (string): Repository owner/organization
  - `$repo` (string): Repository name
- **Returns**: Object|WP_Error - Repository data or error

##### `get_branches($owner, $repo)`
- **Description**: Retrieves branches from a GitHub repository.
- **Parameters**:
  - `$owner` (string): Repository owner/organization
  - `$repo` (string): Repository name
- **Returns**: Array|WP_Error - Branches data or error

##### `get_tags($owner, $repo)`
- **Description**: Retrieves tags from a GitHub repository.
- **Parameters**:
  - `$owner` (string): Repository owner/organization
  - `$repo` (string): Repository name
- **Returns**: Array|WP_Error - Tags data or error

##### `get_zip_url($owner, $repo, $ref)`
- **Description**: Generates a ZIP download URL for a repository.
- **Parameters**:
  - `$owner` (string): Repository owner/organization
  - `$repo` (string): Repository name
  - `$ref` (string): Branch name, tag name, or commit hash
- **Returns**: String - The ZIP download URL

### GitHub_Deployer\Deployer

This class handles the deployment of repositories to the WordPress installation.

#### Methods

##### `__construct($github_api = null)`
- **Description**: Initializes the deployer with an optional GitHub API instance.
- **Parameters**:
  - `$github_api` (GitHub_API|null): An instance of GitHub_API

##### `deploy($owner, $repo, $ref, $type = 'plugin')`
- **Description**: Deploys a GitHub repository as a plugin or theme.
- **Parameters**:
  - `$owner` (string): Repository owner/organization
  - `$repo` (string): Repository name
  - `$ref` (string): Branch name, tag name, or commit hash
  - `$type` (string): Either 'plugin' or 'theme'
- **Returns**: Boolean|WP_Error - True on success or error object

##### `handle_deployment()`
- **Description**: Handles the admin post action for deployments.
- **Returns**: Void - Redirects to the admin page

### GitHub_Deployer\Settings

This class handles the plugin settings and admin interface.

#### Methods

##### `__construct()`
- **Description**: Initializes the settings class.

##### `add_admin_menu()`
- **Description**: Adds the plugin menu item to the WordPress admin.

##### `settings_init()`
- **Description**: Initializes the settings page fields and sections.

##### `render_settings_page()`
- **Description**: Renders the admin settings page.

##### `enqueue_assets($hook)`
- **Description**: Enqueues CSS and JavaScript for the admin page.
- **Parameters**:
  - `$hook` (string): Current admin page hook

### GitHub_Deployer\Installer

This class handles plugin activation and deactivation.

#### Methods

##### `activate()`
- **Description**: Runs on plugin activation.

##### `deactivate()`
- **Description**: Runs on plugin deactivation.

##### `uninstall()`
- **Description**: Cleans up plugin data on uninstallation.

## Helper Functions

### `GitHub_Deployer\is_github_deployer_configured()`
- **Description**: Checks if the GitHub API token is configured.
- **Returns**: Boolean - True if configured, false otherwise

### `GitHub_Deployer\get_github_repo_info($owner, $repo)`
- **Description**: Gets repository information.
- **Parameters**:
  - `$owner` (string): Repository owner/organization
  - `$repo` (string): Repository name
- **Returns**: Object|WP_Error - Repository data or error

### `GitHub_Deployer\get_github_branches($owner, $repo)`
- **Description**: Gets repository branches.
- **Parameters**:
  - `$owner` (string): Repository owner/organization
  - `$repo` (string): Repository name
- **Returns**: Array|WP_Error - Branches data or error

### `GitHub_Deployer\get_github_tags($owner, $repo)`
- **Description**: Gets repository tags.
- **Parameters**:
  - `$owner` (string): Repository owner/organization
  - `$repo` (string): Repository name
- **Returns**: Array|WP_Error - Tags data or error

### `GitHub_Deployer\format_error_message(WP_Error $error)`
- **Description**: Formats an error message for display.
- **Parameters**:
  - `$error` (WP_Error): WordPress error object
- **Returns**: String - Formatted error message

## Actions and Filters

### Actions

#### `github_deployer_before_deploy`
- **Description**: Fires before deploying a repository.
- **Parameters**:
  - `$owner` (string): Repository owner/organization
  - `$repo` (string): Repository name
  - `$ref` (string): Branch, tag, or commit
  - `$type` (string): Deployment type (plugin or theme)

#### `github_deployer_after_deploy`
- **Description**: Fires after successfully deploying a repository.
- **Parameters**:
  - `$owner` (string): Repository owner/organization
  - `$repo` (string): Repository name
  - `$ref` (string): Branch, tag, or commit
  - `$type` (string): Deployment type (plugin or theme)
  - `$destination` (string): Full path to the deployed directory

#### `github_deployer_deploy_failed`
- **Description**: Fires when a deployment fails.
- **Parameters**:
  - `$owner` (string): Repository owner/organization
  - `$repo` (string): Repository name
  - `$ref` (string): Branch, tag, or commit
  - `$type` (string): Deployment type (plugin or theme)
  - `$error` (WP_Error): The error that occurred

### Filters

#### `github_deployer_api_url`
- **Description**: Filters the GitHub API URL.
- **Parameters**:
  - `$url` (string): The API URL
- **Returns**: String - Modified API URL

#### `github_deployer_zip_url`
- **Description**: Filters the ZIP download URL.
- **Parameters**:
  - `$url` (string): The ZIP URL
  - `$owner` (string): Repository owner/organization
  - `$repo` (string): Repository name
  - `$ref` (string): Branch, tag, or commit
- **Returns**: String - Modified ZIP URL

#### `github_deployer_extract_destination`
- **Description**: Filters the extraction destination.
- **Parameters**:
  - `$destination` (string): Extraction destination directory
  - `$type` (string): Deployment type (plugin or theme)
- **Returns**: String - Modified destination directory

#### `github_deployer_final_directory_name`
- **Description**: Filters the final directory name for the deployed code.
- **Parameters**:
  - `$dir_name` (string): Directory name (default: repository name)
  - `$repo` (string): Repository name
  - `$type` (string): Deployment type (plugin or theme)
- **Returns**: String - Modified directory name

## Integration Examples

### Programmatically Deploy a Repository

```php
// Get a reference to the deployer
$deployer = new GitHub_Deployer\Deployer();

// Deploy a repository
$result = $deployer->deploy('owner', 'repo-name', 'main', 'plugin');

if (is_wp_error($result)) {
    echo 'Deployment failed: ' . $result->get_error_message();
} else {
    echo 'Deployment successful!';
}
```

### Add Custom Repository Information Display

```php
// Add custom information to the repository display
add_filter('github_deployer_repo_info', function($repo_info, $owner, $repo) {
    // Add custom data
    $repo_info->custom_data = 'Custom repository information';
    return $repo_info;
}, 10, 3);
```

### Change Extraction Destination

```php
// Change where plugins are extracted
add_filter('github_deployer_extract_destination', function($destination, $type) {
    if ($type === 'plugin') {
        return WP_CONTENT_DIR . '/my-custom-plugins-folder/';
    }
    return $destination;
}, 10, 2);
```

### Log Deployments

```php
// Log when deployments occur
add_action('github_deployer_after_deploy', function($owner, $repo, $ref, $type, $destination) {
    error_log("Deployed {$type} from {$owner}/{$repo} ({$ref}) to {$destination}");
}, 10, 5);
``` 