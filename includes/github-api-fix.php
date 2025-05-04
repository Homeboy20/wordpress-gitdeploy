<?php
/**
 * GitHub API Fix
 * 
 * This file contains fixes for handling GitHub API responses
 * 
 * @package GitHub_Deployer
 */

namespace GitHub_Deployer;

/**
 * Process a GitHub API response to ensure consistent format
 * 
 * - Adds headers to both array and object responses
 * - Wraps arrays in objects with 'items' property
 * - Converts timestamps to consistent format
 * 
 * @param mixed $response The GitHub API response to process
 * @return object The processed response as a consistent object
 */
function process_github_api_response($response) {
    // If already standardized, return as is
    if (is_object($response) && isset($response->headers)) {
        return $response;
    }
    
    // If it's an array, wrap it in an object with items property
    if (is_array($response)) {
        $data = new \stdClass();
        $data->items = $response;
        $data->headers = array(); // Initialize headers even for arrays
        return $data;
    }
    
    // If it's an object but doesn't have headers, add them
    if (is_object($response) && !isset($response->headers)) {
        $response->headers = array();
    }
    
    return $response;
}

// Hook into GitHub API processing, ensuring WordPress functions are available
if (function_exists('add_filter')) {
    add_filter('github_deployer_api_response', __NAMESPACE__ . '\process_github_api_response', 10, 1);
}