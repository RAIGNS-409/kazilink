<?php
spl_autoload_register(function ($class) {
    // PHPMailer namespace: PHPMailer\PHPMailer\ClassName
    if (strpos($class, 'PHPMailer\\PHPMailer\\') === 0) {
        $className = str_replace('PHPMailer\\PHPMailer\\', '', $class);
        $file = __DIR__ . '/phpmailer/phpmailer/src/' . $className . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});
?>