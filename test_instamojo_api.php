<?php
include 'config/show_errors.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Instamojo API credentials
$instamojo_client_id = 'jzbKzPzBmvukguUBoo2HOQtvnKKvti9OLppTlGMt';
$instamojo_client_secret = 'nUvHLo8RJRrvvyVKviWJ3IiJnWGZiDUy5t8JRHRoOitqwGWNp0UgS6TeLYAZT3Wyntw76bDfEUcDR85286Jcp0OB5ml9bvmqsFD8m7MN4r4rPNvzWUaaIdJfxFwdD6GZ';
$instamojo_base_url = 'https://api.instamojo.com/v2/';
$instamojo_oauth_url = 'https://api.instamojo.com/oauth2/token/';

header('Content-Type: application/json');

try {
    // Test 1: Get OAuth2 access token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $instamojo_oauth_url);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    
    $payload = array(
        'grant_type' => 'client_credentials',
        'client_id' => $instamojo_client_id,
        'client_secret' => $instamojo_client_secret
    );
    
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($http_code !== 200) {
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to get access token. HTTP Code: ' . $http_code . ', Response: ' . $response,
            'details' => [
                'http_code' => $http_code,
                'response' => $response,
                'curl_error' => $curl_error
            ]
        ]);
        exit();
    }
    
    $token_data = json_decode($response, true);
    if (!isset($token_data['access_token'])) {
        echo json_encode([
            'success' => false, 
            'error' => 'Access token not found in response: ' . $response
        ]);
        exit();
    }
    
    $access_token = $token_data['access_token'];
    
    // Test 2: Check if we can reach the payment requests endpoint
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $instamojo_base_url . 'payment_requests/');
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token"
    ]);
    curl_setopt($ch, CURLOPT_POST, FALSE); // Just GET to test connection
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    // Log the test attempt
    error_log("Instamojo API Test - HTTP Code: $http_code, Response: $response");
    if ($curl_error) {
        error_log("Instamojo API Test - Curl Error: $curl_error");
    }
    
    // Analyze the response
    if ($curl_error) {
        echo json_encode([
            'success' => false, 
            'error' => 'CURL Error: ' . $curl_error,
            'details' => [
                'http_code' => $http_code,
                'curl_error' => $curl_error
            ]
        ]);
    } elseif ($http_code === 401) {
        echo json_encode([
            'success' => false, 
            'error' => 'Authentication failed - Check your API credentials',
            'details' => [
                'http_code' => $http_code,
                'response' => $response
            ]
        ]);
    } elseif ($http_code === 403) {
        echo json_encode([
            'success' => false, 
            'error' => 'Access forbidden - Check API permissions',
            'details' => [
                'http_code' => $http_code,
                'response' => $response
            ]
        ]);
    } elseif ($http_code === 404) {
        echo json_encode([
            'success' => false, 
            'error' => 'API endpoint not found - Check API version and URL',
            'details' => [
                'http_code' => $http_code,
                'response' => $response
            ]
        ]);
    } elseif ($http_code >= 200 && $http_code < 300) {
        echo json_encode([
            'success' => true, 
            'message' => 'API connection successful',
            'details' => [
                'http_code' => $http_code,
                'response' => $response
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Unexpected response from API',
            'details' => [
                'http_code' => $http_code,
                'response' => $response
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Instamojo API Test Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Exception: ' . $e->getMessage()
    ]);
}
?> 