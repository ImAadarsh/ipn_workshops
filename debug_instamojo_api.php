<?php
include 'config/show_errors.php';

// Instamojo API credentials
$instamojo_client_id = 'jzbKzPzBmvukguUBoo2HOQtvnKKvti9OLppTlGMt';
$instamojo_client_secret = 'nUvHLo8RJRrvvyVKviWJ3IiJnWGZiDUy5t8JRHRoOitqwGWNp0UgS6TeLYAZT3Wyntw76bDfEUcDR85286Jcp0OB5ml9bvmqsFD8m7MN4r4rPNvzWUaaIdJfxFwdD6GZ';
$instamojo_base_url = 'https://api.instamojo.com/v2/';
$instamojo_oauth_url = 'https://api.instamojo.com/oauth2/token/';

// The problematic payment request ID
$payment_request_id = '051e6f0fa0434aa7b2e1aa7213002da7';

echo "<h2>Instamojo API Debug Test</h2>";
echo "<p><strong>Payment Request ID:</strong> $payment_request_id</p>";
echo "<p><strong>Payment Link:</strong> <a href='https://www.instamojo.com/@ipnacademy/$payment_request_id' target='_blank'>https://www.instamojo.com/@ipnacademy/$payment_request_id</a></p>";

// Step 1: Get OAuth2 Access Token
echo "<h3>Step 1: Getting OAuth2 Access Token</h3>";

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

echo "<p><strong>OAuth2 Request:</strong></p>";
echo "<pre>URL: $instamojo_oauth_url
Method: POST
Payload: " . http_build_query($payload) . "</pre>";

echo "<p><strong>OAuth2 Response:</strong></p>";
echo "<p><strong>HTTP Code:</strong> $http_code</p>";
if ($curl_error) {
    echo "<p><strong>Curl Error:</strong> $curl_error</p>";
}
echo "<pre>" . htmlspecialchars($response) . "</pre>";

if ($http_code === 200) {
    $token_data = json_decode($response, true);
    if (isset($token_data['access_token'])) {
        $access_token = $token_data['access_token'];
        echo "<p><strong>✅ Access Token Obtained:</strong> " . substr($access_token, 0, 20) . "...</p>";
        
        // Step 2: Test API Connection
        echo "<h3>Step 2: Testing API Connection</h3>";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $instamojo_base_url . 'payment_requests/');
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $access_token"
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        echo "<p><strong>API Test Request:</strong></p>";
        echo "<pre>URL: " . $instamojo_base_url . "payment_requests/
Method: GET
Authorization: Bearer " . substr($access_token, 0, 20) . "...</pre>";
        
        echo "<p><strong>API Test Response:</strong></p>";
        echo "<p><strong>HTTP Code:</strong> $http_code</p>";
        if ($curl_error) {
            echo "<p><strong>Curl Error:</strong> $curl_error</p>";
        }
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
        // Step 3: Try to get specific payment request
        echo "<h3>Step 3: Getting Specific Payment Request</h3>";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $instamojo_base_url . 'payment_requests/' . $payment_request_id . '/');
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $access_token"
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        echo "<p><strong>Payment Request Request:</strong></p>";
        echo "<pre>URL: " . $instamojo_base_url . "payment_requests/$payment_request_id/
Method: GET
Authorization: Bearer " . substr($access_token, 0, 20) . "...</pre>";
        
        echo "<p><strong>Payment Request Response:</strong></p>";
        echo "<p><strong>HTTP Code:</strong> $http_code</p>";
        if ($curl_error) {
            echo "<p><strong>Curl Error:</strong> $curl_error</p>";
        }
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
        // Step 4: Test Enable/Disable Endpoints
        echo "<h3>Step 4: Testing Enable/Disable Endpoints</h3>";
        
        $test_endpoints = [
            'payment_requests/' . $payment_request_id . '/disable/',
            'payment_requests/' . $payment_request_id . '/enable/',
            'payment_requests/' . $payment_request_id . '/activate/',
            'payment_requests/' . $payment_request_id . '/deactivate/'
        ];
        
        foreach ($test_endpoints as $endpoint) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $instamojo_base_url . $endpoint);
            curl_setopt($ch, CURLOPT_HEADER, FALSE);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer $access_token"
            ]);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "<p><strong>Endpoint:</strong> $endpoint - <strong>HTTP Code:</strong> $http_code</p>";
            if ($http_code === 200) {
                echo "<p><strong>✅ SUCCESS!</strong> Found working endpoint: $endpoint</p>";
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
            }
        }
        
        // Step 5: Test PATCH with mark_fulfilled
        echo "<h3>Step 5: Testing PATCH with mark_fulfilled</h3>";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $instamojo_base_url . 'payment_requests/' . $payment_request_id . '/');
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $access_token",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['mark_fulfilled' => false]));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        echo "<p><strong>PATCH mark_fulfilled=false:</strong> HTTP Code: $http_code</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        
    } else {
        echo "<p><strong>❌ No access token in response</strong></p>";
    }
} else {
    echo "<p><strong>❌ Failed to get access token</strong></p>";
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p>This debug script will help identify the correct API endpoint and any authentication issues.</p>";
?> 