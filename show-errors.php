<?php
// Turn on ALL error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>PHP Error Display Test</h1>";
echo "<p>If you see this, PHP is working!</p>";
echo "<p>PHP Version: " . phpversion() . "</p>";

echo "<h2>Checking PHPMailer Files:</h2>";

$paths = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php',
    __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php',
    __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php',
];

echo "<ul>";
foreach ($paths as $path) {
    $exists = file_exists($path) ? '✅ EXISTS' : '❌ NOT FOUND';
    echo "<li><code>$path</code> - $exists</li>";
}
echo "</ul>";

echo "<h2>Trying to load PHPMailer:</h2>";

// Try direct include
$phpmailerFile = __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
if (file_exists($phpmailerFile)) {
    echo "<p>✅ PHPMailer.php file exists!</p>";
    echo "<p>Attempting to include it...</p>";
    
    try {
        require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
        echo "<p>✅ Exception.php loaded</p>";
        
        require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
        echo "<p>✅ PHPMailer.php loaded</p>";
        
        require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
        echo "<p>✅ SMTP.php loaded</p>";
        
        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            echo "<p style='color: green; font-size: 20px;'>🎉 SUCCESS! PHPMailer class is available!</p>";
        } else {
            echo "<p style='color: red;'>❌ Class still not found after loading files</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ PHPMailer.php file NOT found at expected location!</p>";
    echo "<p>Please check the folder structure.</p>";
}
?>