<?php
/**
 * Settings tab template
 */

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Display the current settings
$options = get_option('github_deployer_settings', array());
?>

<div class="github-deployer-container">
    <div class="github-deployer-settings">
        <h2><?php esc_html_e('GitHub API Settings', 'github-deployer'); ?></h2>
        
        <form action="options.php" method="post">
            <?php
            // Output settings fields
            settings_fields('github_deployer');
            
            // Manually render the settings section and field if needed
            ?>
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="github_deployer_token"><?php esc_html_e('GitHub Personal Access Token', 'github-deployer'); ?></label>
                        </th>
                        <td>
                            <input type="password" name="github_deployer_settings[token]" id="github_deployer_token" value="<?php echo esc_attr(isset($options['token']) ? $options['token'] : ''); ?>" class="regular-text">
                            <p class="description">
                                <?php esc_html_e('Create a personal access token in your GitHub account settings with the "repo" scope.', 'github-deployer'); ?>
                                <a href="https://github.com/settings/tokens/new" target="_blank"><?php esc_html_e('Create token', 'github-deployer'); ?></a>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <?php
            // Submit button
            submit_button();
            ?>
        </form>
        
        <div class="settings-info">
            <h3><?php esc_html_e('About GitHub Personal Access Tokens', 'github-deployer'); ?></h3>
            <p><?php esc_html_e('A personal access token is required to access private repositories and to avoid GitHub API rate limits.', 'github-deployer'); ?></p>
            <p><?php esc_html_e('To create a token, go to your GitHub account settings, then Developer settings > Personal access tokens, and click "Generate new token".', 'github-deployer'); ?></p>
            <p><?php esc_html_e('Your token should have the "repo" scope to access private repositories, or "public_repo" for public repositories only.', 'github-deployer'); ?></p>
            <p><a href="https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/creating-a-personal-access-token" target="_blank" class="button"><?php esc_html_e('GitHub Documentation on Access Tokens', 'github-deployer'); ?></a></p>
        </div>
    </div>
</div> 