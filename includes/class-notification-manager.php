<?php
namespace GitHub_Deployer;

/**
 * Notification Manager class
 * 
 * Handles notifications for deployments, updates, and errors
 */
class Notification_Manager {
    private $settings;
    
    public function __construct() {
        $this->settings = get_option('github_deployer_notification_settings', array(
            'enable_email' => true,
            'email_recipients' => get_option('admin_email'),
            'enable_slack' => false,
            'slack_webhook_url' => '',
            'notify_on_deploy' => true,
            'notify_on_update' => true,
            'notify_on_error' => true,
            'notify_on_rollback' => true
        ));
        
        // Register action hooks
        add_action('github_deployer_after_deploy', array($this, 'notify_deployment'), 10, 5);
        add_action('github_deployer_deploy_failed', array($this, 'notify_deployment_failed'), 10, 5);
        add_action('github_deployer_after_update', array($this, 'notify_update'), 10, 5);
        add_action('github_deployer_update_failed', array($this, 'notify_update_failed'), 10, 5);
        add_action('github_deployer_after_rollback', array($this, 'notify_rollback'), 10, 4);
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Register notification settings
     */
    public function register_settings() {
        register_setting(
            'github_deployer',
            'github_deployer_notification_settings',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );
        
        add_settings_section(
            'github_deployer_notifications_section',
            __('Notification Settings', 'github-deployer'),
            array($this, 'render_notifications_section'),
            'github_deployer_notifications'
        );
        
        add_settings_field(
            'github_deployer_enable_email',
            __('Email Notifications', 'github-deployer'),
            array($this, 'render_enable_email_field'),
            'github_deployer_notifications',
            'github_deployer_notifications_section'
        );
        
        add_settings_field(
            'github_deployer_email_recipients',
            __('Email Recipients', 'github-deployer'),
            array($this, 'render_email_recipients_field'),
            'github_deployer_notifications',
            'github_deployer_notifications_section'
        );
        
        add_settings_field(
            'github_deployer_enable_slack',
            __('Slack Notifications', 'github-deployer'),
            array($this, 'render_enable_slack_field'),
            'github_deployer_notifications',
            'github_deployer_notifications_section'
        );
        
        add_settings_field(
            'github_deployer_slack_webhook_url',
            __('Slack Webhook URL', 'github-deployer'),
            array($this, 'render_slack_webhook_field'),
            'github_deployer_notifications',
            'github_deployer_notifications_section'
        );
        
        add_settings_field(
            'github_deployer_notification_events',
            __('Notification Events', 'github-deployer'),
            array($this, 'render_notification_events_field'),
            'github_deployer_notifications',
            'github_deployer_notifications_section'
        );
    }
    
    /**
     * Sanitize settings
     * 
     * @param array $input Settings input
     * @return array Sanitized settings
     */
    public function sanitize_settings($input) {
        // Ensure input is an array, default to empty array if null or not array
        $input = is_array($input) ? $input : [];

        $output = array(
            // Use null coalescing operator for cleaner defaults
            'enable_email' => !empty($input['enable_email']),
            'email_recipients' => sanitize_text_field($input['email_recipients'] ?? ''), 
            'enable_slack' => !empty($input['enable_slack']),
            'slack_webhook_url' => esc_url_raw($input['slack_webhook_url'] ?? ''), 
            'notify_on_deploy' => !empty($input['notify_on_deploy']),
            'notify_on_update' => !empty($input['notify_on_update']),
            'notify_on_error' => !empty($input['notify_on_error']),
            'notify_on_rollback' => !empty($input['notify_on_rollback'])
        );
        
        return $output;
    }
    
    /**
     * Render the notifications settings section
     */
    public function render_notifications_section() {
        echo '<p>' . __('Configure notifications for GitHub Deployer events.', 'github-deployer') . '</p>';
    }
    
    /**
     * Render the enable email field
     */
    public function render_enable_email_field() {
        $checked = isset($this->settings['enable_email']) && $this->settings['enable_email'] ? 'checked' : '';
        
        echo '<label>';
        echo '<input type="checkbox" name="github_deployer_notification_settings[enable_email]" value="1" ' . $checked . ' />';
        echo ' ' . __('Enable email notifications', 'github-deployer');
        echo '</label>';
    }
    
    /**
     * Render the email recipients field
     */
    public function render_email_recipients_field() {
        $value = isset($this->settings['email_recipients']) ? $this->settings['email_recipients'] : get_option('admin_email');
        
        echo '<input type="text" name="github_deployer_notification_settings[email_recipients]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Comma-separated list of email addresses to receive notifications.', 'github-deployer') . '</p>';
    }
    
    /**
     * Render the enable Slack field
     */
    public function render_enable_slack_field() {
        $checked = isset($this->settings['enable_slack']) && $this->settings['enable_slack'] ? 'checked' : '';
        
        echo '<label>';
        echo '<input type="checkbox" name="github_deployer_notification_settings[enable_slack]" value="1" ' . $checked . ' />';
        echo ' ' . __('Enable Slack notifications', 'github-deployer');
        echo '</label>';
    }
    
    /**
     * Render the Slack webhook URL field
     */
    public function render_slack_webhook_field() {
        $value = isset($this->settings['slack_webhook_url']) ? $this->settings['slack_webhook_url'] : '';
        
        echo '<input type="url" name="github_deployer_notification_settings[slack_webhook_url]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Enter your Slack webhook URL to receive notifications.', 'github-deployer') . '</p>';
        echo '<p class="description"><a href="https://api.slack.com/messaging/webhooks" target="_blank">' . __('How to create a Slack webhook', 'github-deployer') . '</a></p>';
    }
    
    /**
     * Render the notification events field
     */
    public function render_notification_events_field() {
        $events = array(
            'notify_on_deploy' => __('Successful deployments', 'github-deployer'),
            'notify_on_update' => __('Successful updates', 'github-deployer'),
            'notify_on_error' => __('Errors and failures', 'github-deployer'),
            'notify_on_rollback' => __('Rollbacks', 'github-deployer')
        );
        
        echo '<p>' . __('Send notifications for:', 'github-deployer') . '</p>';
        echo '<ul class="github-deployer-checkbox-list">';
        
        foreach ($events as $key => $label) {
            $checked = isset($this->settings[$key]) && $this->settings[$key] ? 'checked' : '';
            
            echo '<li>';
            echo '<label>';
            echo '<input type="checkbox" name="github_deployer_notification_settings[' . $key . ']" value="1" ' . $checked . ' />';
            echo ' ' . $label;
            echo '</label>';
            echo '</li>';
        }
        
        echo '</ul>';
    }
    
    /**
     * Notify about successful deployment
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $ref Branch, tag, or commit
     * @param string $type Deployment type (plugin or theme)
     * @param string $destination Full path to the deployed directory
     */
    public function notify_deployment($owner, $repo, $ref, $type, $destination) {
        if (empty($this->settings['notify_on_deploy'])) {
            return;
        }
        
        $subject = sprintf(
            __('[%s] Successfully deployed %s from GitHub', 'github-deployer'),
            get_bloginfo('name'),
            $repo
        );
        
        $message = sprintf(
            __("GitHub repository %s/%s (%s) has been successfully deployed as a %s to your WordPress site.\n\nSite: %s\nTime: %s", 'github-deployer'),
            $owner,
            $repo,
            $ref,
            $type,
            get_bloginfo('url'),
            current_time('mysql')
        );
        
        // Send notifications
        $this->send_email($subject, $message);
        $this->send_slack_message($subject, $message, 'good');
    }
    
    /**
     * Notify about deployment failure
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $ref Branch, tag, or commit
     * @param string $type Deployment type (plugin or theme)
     * @param WP_Error $error The error that occurred
     */
    public function notify_deployment_failed($owner, $repo, $ref, $type, $error) {
        if (empty($this->settings['notify_on_error'])) {
            return;
        }
        
        $subject = sprintf(
            __('[%s] Failed to deploy %s from GitHub', 'github-deployer'),
            get_bloginfo('name'),
            $repo
        );
        
        $message = sprintf(
            __("GitHub repository %s/%s (%s) deployment failed.\n\nError: %s\n\nSite: %s\nTime: %s", 'github-deployer'),
            $owner,
            $repo,
            $ref,
            $error->get_error_message(),
            get_bloginfo('url'),
            current_time('mysql')
        );
        
        // Send notifications
        $this->send_email($subject, $message);
        $this->send_slack_message($subject, $message, 'danger');
    }
    
    /**
     * Notify about successful update
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $ref Branch, tag, or commit
     * @param string $type Deployment type (plugin or theme)
     * @param string $destination Full path to the updated directory
     */
    public function notify_update($owner, $repo, $ref, $type, $destination) {
        if (empty($this->settings['notify_on_update'])) {
            return;
        }
        
        $subject = sprintf(
            __('[%s] Successfully updated %s from GitHub', 'github-deployer'),
            get_bloginfo('name'),
            $repo
        );
        
        $message = sprintf(
            __("GitHub repository %s/%s (%s) has been successfully updated as a %s on your WordPress site.\n\nSite: %s\nTime: %s", 'github-deployer'),
            $owner,
            $repo,
            $ref,
            $type,
            get_bloginfo('url'),
            current_time('mysql')
        );
        
        // Send notifications
        $this->send_email($subject, $message);
        $this->send_slack_message($subject, $message, 'good');
    }
    
    /**
     * Notify about update failure
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $ref Branch, tag, or commit
     * @param string $type Deployment type (plugin or theme)
     * @param WP_Error $error The error that occurred
     */
    public function notify_update_failed($owner, $repo, $ref, $type, $error) {
        if (empty($this->settings['notify_on_error'])) {
            return;
        }
        
        $subject = sprintf(
            __('[%s] Failed to update %s from GitHub', 'github-deployer'),
            get_bloginfo('name'),
            $repo
        );
        
        $message = sprintf(
            __("GitHub repository %s/%s (%s) update failed.\n\nError: %s\n\nSite: %s\nTime: %s", 'github-deployer'),
            $owner,
            $repo,
            $ref,
            $error->get_error_message(),
            get_bloginfo('url'),
            current_time('mysql')
        );
        
        // Send notifications
        $this->send_email($subject, $message);
        $this->send_slack_message($subject, $message, 'danger');
    }
    
    /**
     * Notify about rollback
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $backup_file Backup file used for rollback
     * @param array $metadata Backup metadata
     */
    public function notify_rollback($owner, $repo, $backup_file, $metadata) {
        if (empty($this->settings['notify_on_rollback'])) {
            return;
        }
        
        $subject = sprintf(
            __('[%s] Rolled back %s to previous version', 'github-deployer'),
            get_bloginfo('name'),
            $repo
        );
        
        $message = sprintf(
            __("GitHub repository %s/%s has been rolled back to a previous version.\n\nBackup: %s\nRef: %s\nType: %s\n\nSite: %s\nTime: %s", 'github-deployer'),
            $owner,
            $repo,
            $backup_file,
            isset($metadata['ref']) ? $metadata['ref'] : 'unknown',
            isset($metadata['type']) ? $metadata['type'] : 'unknown',
            get_bloginfo('url'),
            current_time('mysql')
        );
        
        // Send notifications
        $this->send_email($subject, $message);
        $this->send_slack_message($subject, $message, 'warning');
    }
    
    /**
     * Send email notification
     * 
     * @param string $subject Email subject
     * @param string $message Email message
     * @return bool Whether the email was sent
     */
    private function send_email($subject, $message) {
        if (empty($this->settings['enable_email']) || empty($this->settings['email_recipients'])) {
            return false;
        }
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        $recipients = explode(',', $this->settings['email_recipients']);
        $recipients = array_map('trim', $recipients);
        $recipients = array_filter($recipients);
        
        if (empty($recipients)) {
            return false;
        }
        
        foreach ($recipients as $recipient) {
            wp_mail($recipient, $subject, $message, $headers);
        }
        
        return true;
    }
    
    /**
     * Send Slack notification
     * 
     * @param string $title Message title
     * @param string $message Message text
     * @param string $color Message color (good, warning, danger)
     * @return bool Whether the message was sent
     */
    private function send_slack_message($title, $message, $color = 'good') {
        if (empty($this->settings['enable_slack']) || empty($this->settings['slack_webhook_url'])) {
            return false;
        }
        
        $payload = array(
            'attachments' => array(
                array(
                    'fallback' => $title,
                    'color' => $color,
                    'title' => $title,
                    'text' => $message,
                    'footer' => 'GitHub Deployer for WordPress',
                    'ts' => time()
                )
            )
        );
        
        $response = wp_remote_post(
            $this->settings['slack_webhook_url'],
            array(
                'body' => json_encode($payload),
                'headers' => array('Content-Type' => 'application/json'),
                'timeout' => 30
            )
        );
        
        if (is_wp_error($response)) {
            error_log('GitHub Deployer: Failed to send Slack notification: ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        
        if ($response_code !== 200) {
            error_log('GitHub Deployer: Slack API error: ' . wp_remote_retrieve_body($response));
            return false;
        }
        
        return true;
    }
} 