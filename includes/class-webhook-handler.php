<?php
/**
 * Webhook Handler Class
 *
 * @package GitHub_Deployer
 */

namespace GitHub_Deployer;

/**
 * Webhook Handler class.
 * Responsible for processing incoming GitHub webhooks and triggering deployments.
 */
class Webhook_Handler {

    /**
     * Plugin instance.
     *
     * @var Plugin
     */
    private $plugin;

    /**
     * Constructor.
     *
     * @param Plugin $plugin Plugin instance.
     */
    public function __construct(Plugin $plugin) {
        $this->plugin = $plugin;
        $this->init();
    }

    /**
     * Initialize the webhook handler.
     */
    private function init() {
        // Register the webhook endpoint
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
    }

    /**
     * Register the webhook REST API endpoint.
     */
    public function register_webhook_endpoint() {
        register_rest_route('github-deployer/v1', '/webhook', array(
            'methods'  => 'POST',
            'callback' => array($this, 'process_webhook'),
            'permission_callback' => '__return_true', // We'll validate via secret token
        ));
    }

    /**
     * Process incoming webhook request.
     *
     * @param \WP_REST_Request $request Request object.
     * @return \WP_REST_Response Response object.
     */
    public function process_webhook($request) {
        // Get the raw payload
        $payload = $request->get_body();
        
        // Verify the webhook signature if provided
        if (!$this->verify_webhook_signature($request, $payload)) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid webhook signature',
            ), 401);
        }
        
        // Parse the payload
        $data = json_decode($payload, true);
        if (empty($data)) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid payload format',
            ), 400);
        }
        
        // Handle different event types
        $event = $request->get_header('X-GitHub-Event');
        
        // Process based on event type
        switch ($event) {
            case 'ping':
                return $this->handle_ping_event($data);
            case 'push':
                return $this->handle_push_event($data);
            case 'release':
                return $this->handle_release_event($data);
            default:
                return new \WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Unsupported event type: ' . $event,
                ), 400);
        }
    }
    
    /**
     * Verify the webhook signature.
     *
     * @param \WP_REST_Request $request Request object.
     * @param string           $payload Raw payload.
     * @return bool Whether the signature is valid.
     */
    private function verify_webhook_signature($request, $payload) {
        $signature = $request->get_header('X-Hub-Signature-256');
        if (empty($signature)) {
            // If no signature provided, check if we require one
            $settings = $this->plugin->get_settings();
            $webhook_secret = $settings->get('webhook_secret');
            return empty($webhook_secret); // If we have a secret configured, require a signature
        }
        
        // Get the webhook secret from settings
        $settings = $this->plugin->get_settings();
        $webhook_secret = $settings->get('webhook_secret');
        
        if (empty($webhook_secret)) {
            return true; // No secret configured, accept any signature
        }
        
        // Verify the signature
        $expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $webhook_secret);
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Handle ping event.
     *
     * @param array $data Event data.
     * @return \WP_REST_Response Response object.
     */
    private function handle_ping_event($data) {
        return new \WP_REST_Response(array(
            'success' => true,
            'message' => 'Webhook received successfully',
            'event'   => 'ping',
        ), 200);
    }
    
    /**
     * Handle push event.
     *
     * @param array $data Event data.
     * @return \WP_REST_Response Response object.
     */
    private function handle_push_event($data) {
        // Extract repository information
        if (empty($data['repository'])) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => 'Repository information missing',
            ), 400);
        }
        
        $repo_full_name = $data['repository']['full_name'];
        $repo_branch = isset($data['ref']) ? str_replace('refs/heads/', '', $data['ref']) : null;
        
        // Check if this repository is configured for auto-updates
        $repo_manager = $this->plugin->get_repository_manager();
        $repositories = $repo_manager->get_repositories();
        
        $matching_repo = null;
        foreach ($repositories as $repo) {
            if ($repo['full_name'] === $repo_full_name && isset($repo['auto_update']) && $repo['auto_update']) {
                $matching_repo = $repo;
                break;
            }
        }
        
        if (!$matching_repo) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => 'Repository not configured for auto-updates',
                'repo'    => $repo_full_name,
            ), 200);
        }
        
        // Check if the pushed branch matches the configured branch
        if (!empty($repo_branch) && !empty($matching_repo['branch']) && $repo_branch !== $matching_repo['branch']) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => 'Push to non-tracked branch',
                'repo'    => $repo_full_name,
                'branch'  => $repo_branch,
            ), 200);
        }
        
        // Trigger the deployment
        $deployer = $this->plugin->get_deployer();
        $result = $deployer->deploy_repository($matching_repo['id']);
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
                'repo'    => $repo_full_name,
            ), 500);
        }
        
        return new \WP_REST_Response(array(
            'success' => true,
            'message' => 'Deployment triggered successfully',
            'repo'    => $repo_full_name,
            'event'   => 'push',
        ), 200);
    }
    
    /**
     * Handle release event.
     *
     * @param array $data Event data.
     * @return \WP_REST_Response Response object.
     */
    private function handle_release_event($data) {
        // Only process published releases
        if (empty($data['action']) || $data['action'] !== 'published' || empty($data['release'])) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => 'Not a published release',
            ), 200);
        }
        
        // Extract repository information
        if (empty($data['repository'])) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => 'Repository information missing',
            ), 400);
        }
        
        $repo_full_name = $data['repository']['full_name'];
        $release_tag = $data['release']['tag_name'];
        
        // Check if this repository is configured for auto-updates
        $repo_manager = $this->plugin->get_repository_manager();
        $repositories = $repo_manager->get_repositories();
        
        $matching_repo = null;
        foreach ($repositories as $repo) {
            if ($repo['full_name'] === $repo_full_name && isset($repo['auto_update']) && $repo['auto_update']) {
                $matching_repo = $repo;
                break;
            }
        }
        
        if (!$matching_repo) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => 'Repository not configured for auto-updates',
                'repo'    => $repo_full_name,
            ), 200);
        }
        
        // Trigger the deployment with the release tag
        $deployer = $this->plugin->get_deployer();
        $matching_repo['tag'] = $release_tag; // Set the tag to deploy
        $result = $deployer->deploy_repository($matching_repo['id']);
        
        if (is_wp_error($result)) {
            return new \WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
                'repo'    => $repo_full_name,
                'tag'     => $release_tag,
            ), 500);
        }
        
        return new \WP_REST_Response(array(
            'success' => true,
            'message' => 'Deployment of release triggered successfully',
            'repo'    => $repo_full_name,
            'tag'     => $release_tag,
            'event'   => 'release',
        ), 200);
    }
} 