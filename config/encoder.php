<?php
/**
 * Encoder/Decoder functions for meeting_id and passcode
 * 
 * Encoding Logic:
 * - Add constants: meeting_id + 10082000, passcode + 2507
 * - Convert each digit to alphabet: 0=A, 1=B, 2=C, 3=D, 4=E, 5=F, 6=G, 7=H, 8=I, 9=J
 * 
 * Decoding Logic:
 * - Convert alphabet back to digits: A=0, B=1, C=2, D=3, E=4, F=5, G=6, H=7, I=8, J=9
 * - Subtract constants: meeting_id - 10082000, passcode - 2507
 */

// Constants for encoding
define('MEETING_ID_CONSTANT', 10082000);
define('PASSCODE_CONSTANT', 2507);

// Digit to alphabet mapping
$digit_to_alpha = [
    '0' => 'A', '1' => 'B', '2' => 'C', '3' => 'D', '4' => 'E',
    '5' => 'F', '6' => 'G', '7' => 'H', '8' => 'I', '9' => 'J'
];

// Alphabet to digit mapping
$alpha_to_digit = [
    'A' => '0', 'B' => '1', 'C' => '2', 'D' => '3', 'E' => '4',
    'F' => '5', 'G' => '6', 'H' => '7', 'I' => '8', 'J' => '9'
];

/**
 * Encode meeting_id
 * @param string $meeting_id - Original meeting ID
 * @return string - Encoded meeting ID
 */
function encodeMeetingId($meeting_id) {
    global $digit_to_alpha;
    
    if (empty($meeting_id)) {
        return '';
    }
    
    // Store original length to preserve leading zeros
    $original_length = strlen($meeting_id);
    
    // Add constant
    $numeric_value = intval($meeting_id) + MEETING_ID_CONSTANT;
    
    // Convert each digit to alphabet
    $encoded = '';
    $numeric_string = strval($numeric_value);
    
    for ($i = 0; $i < strlen($numeric_string); $i++) {
        $digit = $numeric_string[$i];
        if (isset($digit_to_alpha[$digit])) {
            $encoded .= $digit_to_alpha[$digit];
        } else {
            $encoded .= $digit; // Keep non-numeric characters as is
        }
    }
    
    // Add original length as prefix to preserve leading zeros
    $encoded = $original_length . '_' . $encoded;
    
    return $encoded;
}

/**
 * Decode meeting_id
 * @param string $encoded_meeting_id - Encoded meeting ID
 * @return string - Decoded meeting ID
 */
function decodeMeetingId($encoded_meeting_id) {
    global $alpha_to_digit;
    
    if (empty($encoded_meeting_id)) {
        return '';
    }
    
    // Check if this is the new format with length prefix
    if (strpos($encoded_meeting_id, '_') !== false) {
        // New format: length_encoded_value
        $parts = explode('_', $encoded_meeting_id, 2);
        if (count($parts) !== 2) {
            return $encoded_meeting_id; // Return as-is if format is invalid
        }
        
        $original_length = intval($parts[0]);
        $encoded_value = $parts[1];
        
        // Convert alphabet back to digits
        $decoded = '';
        for ($i = 0; $i < strlen($encoded_value); $i++) {
            $char = strtoupper($encoded_value[$i]);
            if (isset($alpha_to_digit[$char])) {
                $decoded .= $alpha_to_digit[$char];
            } else {
                $decoded .= $char; // Keep non-alphabetic characters as is
            }
        }
        
        // Subtract constant
        $numeric_value = intval($decoded) - MEETING_ID_CONSTANT;
        $result = strval($numeric_value);
        
        // Pad with leading zeros to match original length
        while (strlen($result) < $original_length) {
            $result = '0' . $result;
        }
        
        return $result;
    } else {
        // Old format (backward compatibility)
        // Convert alphabet back to digits
        $decoded = '';
        for ($i = 0; $i < strlen($encoded_meeting_id); $i++) {
            $char = strtoupper($encoded_meeting_id[$i]);
            if (isset($alpha_to_digit[$char])) {
                $decoded .= $alpha_to_digit[$char];
            } else {
                $decoded .= $char; // Keep non-alphabetic characters as is
            }
        }
        
        // Subtract constant
        $numeric_value = intval($decoded) - MEETING_ID_CONSTANT;
        
        return strval($numeric_value);
    }
}

/**
 * Encode passcode
 * @param string $passcode - Original passcode
 * @return string - Encoded passcode
 */
function encodePasscode($passcode) {
    global $digit_to_alpha;
    
    if (empty($passcode)) {
        return '';
    }
    
    // Store original length to preserve leading zeros
    $original_length = strlen($passcode);
    
    // Add constant
    $numeric_value = intval($passcode) + PASSCODE_CONSTANT;
    
    // Convert each digit to alphabet
    $encoded = '';
    $numeric_string = strval($numeric_value);
    
    for ($i = 0; $i < strlen($numeric_string); $i++) {
        $digit = $numeric_string[$i];
        if (isset($digit_to_alpha[$digit])) {
            $encoded .= $digit_to_alpha[$digit];
        } else {
            $encoded .= $digit; // Keep non-numeric characters as is
        }
    }
    
    // Add original length as prefix to preserve leading zeros
    $encoded = $original_length . '_' . $encoded;
    
    return $encoded;
}

/**
 * Decode passcode
 * @param string $encoded_passcode - Encoded passcode
 * @return string - Decoded passcode
 */
function decodePasscode($encoded_passcode) {
    global $alpha_to_digit;
    
    if (empty($encoded_passcode)) {
        return '';
    }
    
    // Check if this is the new format with length prefix
    if (strpos($encoded_passcode, '_') !== false) {
        // New format: length_encoded_value
        $parts = explode('_', $encoded_passcode, 2);
        if (count($parts) !== 2) {
            return $encoded_passcode; // Return as-is if format is invalid
        }
        
        $original_length = intval($parts[0]);
        $encoded_value = $parts[1];
        
        // Convert alphabet back to digits
        $decoded = '';
        for ($i = 0; $i < strlen($encoded_value); $i++) {
            $char = strtoupper($encoded_value[$i]);
            if (isset($alpha_to_digit[$char])) {
                $decoded .= $alpha_to_digit[$char];
            } else {
                $decoded .= $char; // Keep non-alphabetic characters as is
            }
        }
        
        // Subtract constant
        $numeric_value = intval($decoded) - PASSCODE_CONSTANT;
        $result = strval($numeric_value);
        
        // Pad with leading zeros to match original length
        while (strlen($result) < $original_length) {
            $result = '0' . $result;
        }
        
        return $result;
    } else {
        // Old format (backward compatibility)
        // Convert alphabet back to digits
        $decoded = '';
        for ($i = 0; $i < strlen($encoded_passcode); $i++) {
            $char = strtoupper($encoded_passcode[$i]);
            if (isset($alpha_to_digit[$char])) {
                $decoded .= $alpha_to_digit[$char];
            } else {
                $decoded .= $char; // Keep non-alphabetic characters as is
            }
        }
        
        // Subtract constant
        $numeric_value = intval($decoded) - PASSCODE_CONSTANT;
        
        return strval($numeric_value);
    }
}

/**
 * Test function to verify encoding/decoding works correctly
 * Uncomment to test
 */
/*
function testEncoderDecoder() {
    $test_meeting_id = "123456789";
    $test_passcode = "1234";
    
    echo "Original Meeting ID: " . $test_meeting_id . "\n";
    $encoded_meeting = encodeMeetingId($test_meeting_id);
    echo "Encoded Meeting ID: " . $encoded_meeting . "\n";
    $decoded_meeting = decodeMeetingId($encoded_meeting);
    echo "Decoded Meeting ID: " . $decoded_meeting . "\n";
    echo "Meeting ID Match: " . ($test_meeting_id === $decoded_meeting ? "YES" : "NO") . "\n\n";
    
    echo "Original Passcode: " . $test_passcode . "\n";
    $encoded_passcode = encodePasscode($test_passcode);
    echo "Encoded Passcode: " . $encoded_passcode . "\n";
    $decoded_passcode = decodePasscode($encoded_passcode);
    echo "Decoded Passcode: " . $decoded_passcode . "\n";
    echo "Passcode Match: " . ($test_passcode === $decoded_passcode ? "YES" : "NO") . "\n";
}

// Uncomment to run test
// testEncoderDecoder();
*/
?> 