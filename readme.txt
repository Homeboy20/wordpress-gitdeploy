=== GitHub Deployer for WordPress ===
Contributors: ndosa
Tags: github, deployment, git, plugin, theme, install
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Directly deploy plugins and themes from GitHub repositories.

== Description ==

GitHub Deployer allows you to install or update WordPress plugins and themes directly from GitHub repositories. This is especially useful for:

* Testing your own plugins/themes from GitHub
* Installing premium plugins/themes stored in private repositories
* Deploying custom plugins/themes for clients
* Using development versions of plugins/themes
* Keeping your GitHub-sourced plugins/themes automatically up-to-date

**Key Features:**

* Deploy plugins and themes directly from any GitHub repository
* Works with both public and private repositories (requires GitHub token)
* Deploy specific branches, tags, or commits
* Simple, user-friendly interface
* Secure deployment process
* Update existing plugins/themes with new versions from GitHub
* Automatic backup creation before updates
* Auto-update system to keep plugins/themes in sync with GitHub

== Installation ==

1. Upload the `github-deployer` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to the 'GitHub Deployer' menu item
4. Enter your GitHub personal access token (required for private repositories)
5. Start deploying plugins and themes from GitHub

== Frequently Asked Questions ==

= Do I need a GitHub account? =

Yes, you need a GitHub account to generate a personal access token. This is required for accessing private repositories and for avoiding GitHub API rate limits.

= How do I create a GitHub personal access token? =

1. Log in to your GitHub account
2. Go to Settings > Developer settings > Personal access tokens
3. Click "Generate new token"
4. Give your token a descriptive name
5. Select the "repo" scope to give access to your repositories
6. Click "Generate token"
7. Copy the token and paste it into the plugin settings

= Can I deploy from private repositories? =

Yes, as long as you've provided a GitHub personal access token with the appropriate permissions.

= How do auto-updates work? =

When you enable auto-updates for a repository:
1. The plugin sets up a WordPress cron job that runs hourly
2. It checks the GitHub API for changes to the specified branch/tag/commit
3. If changes are detected, it automatically updates the plugin/theme
4. A backup is created before any update takes place

= What repository formats are supported? =

The plugin supports standard WordPress plugin and theme formats. The repository should be structured like a typical WordPress plugin or theme.

== Screenshots ==

1. Main interface for deploying repositories
2. Settings page
3. Successful deployment example
4. Auto-update tracking interface

== Changelog ==

= 1.2.0 =
* Added auto-update functionality to automatically update plugins/themes when changes are detected in GitHub
* Added hourly checks for repository changes
* Added tracking interface to manage auto-updated repositories
* Improved backup system for safer updates
* Added settings to enable/disable auto-updates per repository

= 1.1.0 =
* Added ability to update existing plugins and themes
* Added automatic backup functionality before updates
* Improved user interface with update notifications
* Better error handling for deployment process

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.2.0 =
Added automatic update functionality to keep plugins and themes in sync with GitHub repositories.

= 1.1.0 =
Added ability to update existing plugins and themes with automatic backup functionality.

= 1.0.0 =
Initial release of GitHub Deployer for WordPress. 