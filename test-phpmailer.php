<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$gmailAddress = 'kibetdaniel411@gmail.com';
$appPassword = 'rmmg qijt adgr opmi';  // ← Paste new password here

echo "<h1>Debug Info:</h1>";
echo "<p>Email: $gmailAddress</p>";
echo "<p>Password: " . substr($appPassword, 0, 4) . "..." . substr($appPassword, -4) . "</p>";
echo "<p>Password length: " . strlen($appPassword) . " characters</p>";
echo "<p>Password starts with space: " . (strpos($appPassword, ' ') === 0 ? 'YES ❌' : 'NO ✅') . "</p>";
echo "<p>Password ends with space: " . (substr($appPassword, -1) === ' ' ? 'YES ❌' : 'NO ✅') . "</p>";
echo "<hr>";

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $gmailAddress;
    $mail->Password   = $appPassword;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->SMTPDebug  = 2;  // Show detailed SMTP conversation
    $mail->Debugoutput = function($str, $level) { echo "<pre>$str</pre>"; };
    
    $mail->setFrom($gmailAddress, 'KaziLink Test');
    $mail->addAddress($gmailAddress);
    $mail->isHTML(true);
    $mail->Subject = 'Test Email';
    $mail->Body    = '<h1>Test</h1>';
    
    $mail->send();
    echo '<h2 style="color: green;">✅ Email sent!</h2>';
} catch (Exception $e) {
    echo '<h2 style="color: red;">❌ Error: ' . $mail->ErrorInfo . '</h2>';
}
?>