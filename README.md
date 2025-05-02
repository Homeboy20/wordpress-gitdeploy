# GitHub Deployer for WordPress

Deploy and update WordPress plugins and themes directly from GitHub repositories.

## Features

- Deploy plugins and themes from any GitHub repository
- Automatic updates via GitHub webhooks
- Support for private repositories
- Auto-update tracking for connected repositories
- One-click deployment and updates
- Minimal server requirements
- No need for manual file uploads
- Secure webhook integration

## Installation

There are two ways to install the GitHub Deployer plugin:

### Option 1: Install via WordPress Dashboard

1. Download the latest release as a ZIP file from [GitHub Releases](https://github.com/Homeboy20/wordpress-gitdeploy/releases)
2. Go to WordPress Dashboard > Plugins > Add New
3. Click "Upload Plugin" and select the ZIP file
4. Click "Install Now"
5. Activate the plugin

### Option 2: Self-Deployment (Recommended)

If you already have the plugin installed, you can use it to update itself:

1. Go to GitHub Deployer > Connect Repository
2. Enter the URL: https://github.com/Homeboy20/wordpress-gitdeploy
3. Check "Deploy immediately after connecting" and "Enable auto-updates"
4. Click "Connect Repository"

This will connect the plugin to its official repository and enable auto-updates.

## Creating a GitHub Token

1. Log in to your GitHub account
2. Go to Settings > Developer settings > Personal access tokens
3. Click "Generate new token"
4. Give your token a descriptive name
5. Select the "repo" scope to give access to your repositories
6. Click "Generate token"
7. Copy the token and paste it into the plugin settings

## Private Repository Support

GitHub Deployer fully supports deploying plugins and themes from private repositories. To use this feature:

1. Make sure your GitHub token has the full "repo" scope (required for private repository access)
2. Enter your repository details as you would for a public repository
3. The plugin will automatically authenticate your request using your GitHub token
4. Private repositories will be downloaded securely using GitHub's API

## Usage

### Deploying a Plugin or Theme

1. Go to GitHub Deployer > Deploy
2. Enter the repository owner (username) and repository name
3. Choose a branch or tag (defaults to main)
4. Select whether this is a plugin or theme
5. Click "Deploy"

### Auto-Updates

1. Go to GitHub Deployer > Repositories
2. Find your repository in the list
3. Click "Enable Auto-Update"

The plugin will automatically check for updates on a regular basis and apply them when available.

## Webhooks

For instant updates, you can set up a GitHub webhook:

1. Go to your repository on GitHub
2. Click Settings > Webhooks > Add webhook
3. Set the Payload URL to your webhook URL (shown in the GitHub Deployer settings)
4. Set Content type to `application/json`
5. Enter your secret token (if configured in the plugin settings)
6. Choose which events trigger the webhook (recommend "Just the push event")
7. Click "Add webhook"

## Support

If you encounter any issues or need assistance, please [submit an issue](https://github.com/Homeboy20/wordpress-gitdeploy/issues) on GitHub.

## License

This project is licensed under the GPL-2.0+ License - see the LICENSE file for details.

## Credits

Created by Ndosa 