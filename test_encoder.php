<?php
// Test file for encoder/decoder functions
require_once 'config/encoder.php';

echo "<h2>Encoder/Decoder Test Results</h2>";

// Test cases
$test_cases = [
    ['meeting_id' => '123456789', 'passcode' => '1234'],
    ['meeting_id' => '987654321', 'passcode' => '5678'],
    ['meeting_id' => '555666777', 'passcode' => '9999'],
    ['meeting_id' => '111222333', 'passcode' => '0000'],
    ['meeting_id' => '067018', 'passcode' => '0123'],  // Leading zeros test
    ['meeting_id' => '001234', 'passcode' => '0001'],  // Multiple leading zeros
    ['meeting_id' => '000000123', 'passcode' => '0000'] // Many leading zeros
];

foreach ($test_cases as $index => $test) {
    echo "<h3>Test Case " . ($index + 1) . "</h3>";
    
    // Test Meeting ID
    echo "<strong>Meeting ID Test:</strong><br>";
    echo "Original: " . $test['meeting_id'] . "<br>";
    $encoded_meeting = encodeMeetingId($test['meeting_id']);
    echo "Encoded: " . $encoded_meeting . "<br>";
    $decoded_meeting = decodeMeetingId($encoded_meeting);
    echo "Decoded: " . $decoded_meeting . "<br>";
    echo "Match: " . ($test['meeting_id'] === $decoded_meeting ? "✅ YES" : "❌ NO") . "<br><br>";
    
    // Test Passcode
    echo "<strong>Passcode Test:</strong><br>";
    echo "Original: " . $test['passcode'] . "<br>";
    $encoded_passcode = encodePasscode($test['passcode']);
    echo "Encoded: " . $encoded_passcode . "<br>";
    $decoded_passcode = decodePasscode($encoded_passcode);
    echo "Decoded: " . $decoded_passcode . "<br>";
    echo "Match: " . ($test['passcode'] === $decoded_passcode ? "✅ YES" : "❌ NO") . "<br><br>";
    
    echo "<hr>";
}

// Test empty values
echo "<h3>Empty Values Test</h3>";
echo "Empty Meeting ID - Encoded: '" . encodeMeetingId('') . "'<br>";
echo "Empty Passcode - Encoded: '" . encodePasscode('') . "'<br>";
echo "Empty Meeting ID - Decoded: '" . decodeMeetingId('') . "'<br>";
echo "Empty Passcode - Decoded: '" . decodePasscode('') . "'<br>";

echo "<h3>Constants Used</h3>";
echo "Meeting ID Constant: " . MEETING_ID_CONSTANT . "<br>";
echo "Passcode Constant: " . PASSCODE_CONSTANT . "<br>";

echo "<h3>Digit to Alphabet Mapping</h3>";
echo "0=A, 1=B, 2=C, 3=D, 4=E, 5=F, 6=G, 7=H, 8=I, 9=J<br>";
?> 