# GitHub Deployer Plugin for WordPress

## Project Overview
GitHub Deployer is a WordPress plugin that enables direct deployment of plugins and themes from GitHub repositories to a WordPress site. The plugin allows users to specify a GitHub repository, branch/tag, and deployment type (plugin or theme) to install or update WordPress components directly from GitHub.

## File Structure
```
github-deployer/
├── .gitignore                  # Git ignore file
├── github-deployer.php         # Main plugin file with initialization
├── readme.txt                  # WordPress plugin readme
├── assets/
│   ├── css/
│   │   └── admin.css           # Admin interface styling
│   └── js/
│       └── admin.js            # Admin interface JavaScript
├── includes/
│   ├── class-ajax.php          # AJAX handler for GitHub API requests
│   ├── class-deployer.php      # Core deployment functionality
│   ├── class-github-api.php    # GitHub API interaction
│   ├── class-installer.php     # Plugin installation/activation handlers
│   ├── class-settings.php      # Plugin settings page
│   └── helpers.php             # Helper functions
├── languages/                  # Translation files directory
└── templates/
    └── admin/
        └── settings.php        # Admin settings page template
```

## Key Classes and Components

### Main Plugin File (`github-deployer.php`)
- Defines plugin metadata
- Includes necessary files
- Initializes components on WordPress load
- Registers activation/deactivation hooks

### GitHub API (`class-github-api.php`)
- Handles communication with GitHub's REST API
- Retrieves repository info, branches, and tags
- Generates download URLs for repositories
- Manages GitHub API authentication via personal access token

### Deployment Logic (`class-deployer.php`)
- Downloads repositories from GitHub
- Extracts ZIP archives to the WordPress plugins or themes directory
- Handles the GitHub-specific directory structure in ZIP files
- Processes deployment form submissions

### Settings Management (`class-settings.php`)
- Creates the admin menu entry
- Renders the settings page
- Manages GitHub API token storage
- Enqueues admin CSS and JavaScript files

### Admin Interface (`templates/admin/settings.php`)
- Provides a user interface for plugin settings
- Includes a deployment form for installing plugins/themes
- Shows notifications for successful/failed deployments

### Helper Functions (`helpers.php`)
- Utility functions used throughout the plugin
- API token status checking
- Simplified GitHub API interaction
- Error message formatting

## JavaScript Functionality (`admin.js`)
- Fetches repository information when user enters repo details
- Displays branches and tags for selection
- Handles user interactions with the deployment interface
- Shows loading states and error messages

## CSS Styling (`admin.css`)
- Styles the admin interface
- Formats repository information display
- Provides loading animations
- Makes the UI responsive

## Bug Fixes and Solutions
- Fixed PHP class loading by switching from autoloader to direct file inclusion
- Fixed GitHub API authentication by properly handling the token
- Resolved class name mismatch between `GitHub_API` references

## Installation
1. Upload the `github-deployer` folder to the WordPress plugins directory
2. Activate the plugin through the WordPress admin
3. Go to GitHub Deployer in the admin menu
4. Enter a GitHub personal access token with the "repo" scope
5. Use the deployment form to install plugins or themes

## Usage
1. Enter a GitHub repository owner and name
2. Select a branch, tag, or commit reference
3. Choose whether to deploy as a plugin or theme
4. Click "Deploy" to install the GitHub repository

## Security Considerations
- WordPress nonces are used for form security
- User capabilities are checked before deployment
- Input data is sanitized
- GitHub API token is stored securely in WordPress options 