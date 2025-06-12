<?php
// Database configuration
$host = "89.117.157.103";
$user = "u334258298_campus_coach";
$password = "1@CampusCoach";
$dbname = "u334258298_campus_coach";

// Create connection
$conn = mysqli_connect($host, $user, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8mb4
mysqli_set_charset($conn, "utf8mb4");

$uri = 'https://backend.campuscoach.in/storage/app/';
date_default_timezone_set('Asia/Kolkata');
$current_time = time();

// API Configuration
define('API_BASE_URL', 'https://backend.campuscoach.in/public');
define('API_TOKEN', 'your_api_token_here');

// Common API calling method
function callAPI($endpoint, $method = 'GET', $data = [], $files = []) {
    $curl = curl_init();
    $url = API_BASE_URL . $endpoint;
    
    $headers = [
        'Accept: application/json',
        'Authorization: Bearer ' . API_TOKEN
    ];
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers
    ];
    
    if ($method === 'POST' || $method === 'PUT') {
        if (!empty($files)) {
            $postData = array_merge($data, $files);
        } else {
            $postData = $data;
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        $options[CURLOPT_POSTFIELDS] = $postData;
    }
    
    curl_setopt_array($curl, $options);
    
    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    $decodedResponse = json_decode($response, true);
    
    // Handle the API response format
    if (isset($decodedResponse['status']) && $decodedResponse['status']) {
        return [
            'success' => true,
            'message' => $decodedResponse['message'] ?? 'Success',
            'data' => $decodedResponse['data'] ?? []
        ];
    } else {
        return [
            'success' => false,
            'message' => $decodedResponse['message'] ?? 'API request failed',
            'error' => $decodedResponse['error'] ?? null
        ];
    }
}

function callAPI1($method, $urlpoint, $data, $token){
    if (!isset($token)) {
        $token = "";
    }
    
    $url = 'https://backend.campuscoach.in/public/api/'.$urlpoint.'';
    $curl = curl_init($url);
    switch ($method){
       case "POST":
          curl_setopt($curl, CURLOPT_POST, 1);
          if ($data)
             curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
             
          break;
       case "PUT":
          curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
          if ($data)
             curl_setopt($curl, CURLOPT_POSTFIELDS, $data);			 					
          break;
       default:
          if ($data)
             $url = sprintf("%s?%s", $url, http_build_query($data));
    }
    
    // OPTIONS:
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
    curl_setopt($curl, CURLOPT_HTTPHEADER, array(
       'Content-Type: multipart/form-data',
       $token
    ));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_BINARYTRANSFER,TRUE);
    curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    // EXECUTE:
    $result = curl_exec($curl);
     echo $result;
    if(!$result){echo curl_error($curl);}
    curl_close($curl);
    return $result;
 }



return $conn;



?> 