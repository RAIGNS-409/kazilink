<?php
require_once __DIR__ . '/config.php';

// Direct includes
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);
        
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error: " . $mail->ErrorInfo);
        return false;
    }
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function getVerificationEmail($name, $token, $baseUrl) {
    $verifyLink = $baseUrl . "/frontend/verify-email.html?token=" . $token;
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8fafc; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #6366F1, #0D9488); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='color: white; margin: 0;'>Welcome to KaziLink! 🎉</h1>
        </div>
        <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px;'>
            <h2>Hello $name,</h2>
            <p>Thank you for registering! Please verify your email:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$verifyLink' style='background: #6366F1; color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                    Verify Email Address
                </a>
            </div>
            <p style='font-size: 12px; color: #64748b;'>Link expires in 24 hours.</p>
        </div>
    </div>
    ";
}

function getPasswordResetEmail($name, $token, $baseUrl) {
    $resetLink = $baseUrl . "/frontend/reset-password.html?token=" . $token;
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8fafc; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #EF4444, #DC2626); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='color: white; margin: 0;'>Password Reset Request 🔒</h1>
        </div>
        <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px;'>
            <h2>Hello $name,</h2>
            <p>Click below to reset your password:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$resetLink' style='background: #EF4444; color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                    Reset Password
                </a>
            </div>
            <p style='font-size: 12px; color: #64748b;'>Link expires in 1 hour.</p>
        </div>
    </div>
    ";
}

function getAccountVerifiedEmail($name) {
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8fafc; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #10B981, #059669); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='color: white; margin: 0;'>Account Verified! ✅</h1>
        </div>
        <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px;'>
            <h2>Hello $name,</h2>
            <p>Your account is now active. Login to get started!</p>
        </div>
    </div>
    ";
}

function getApplicationAcceptedEmail($seekerName, $jobTitle, $companyName, $baseUrl) {
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8fafc; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #10B981, #059669); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='color: white; margin: 0;'>🎉 Application Accepted!</h1>
        </div>
        <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px;'>
            <h2>Hi $seekerName,</h2>
            <p style='font-size: 16px; color: #10B981; font-weight: bold;'>Great news! Your application has been accepted!</p>
            <div style='background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 16px; margin: 20px 0;'>
                <h3 style='margin: 0 0 8px 0; color: #166534;'>$jobTitle</h3>
                <p style='margin: 0; color: #15803d;'>at <strong>$companyName</strong></p>
            </div>
            <p>The employer will contact you soon with next steps. Make sure to check your email and phone regularly.</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$baseUrl/frontend/index.html' style='background: #10B981; color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                    View My Applications
                </a>
            </div>
            <p style='font-size: 12px; color: #64748b;'>Congratulations and best of luck in your new role!</p>
        </div>
    </div>
    ";
}

function getApplicationRejectedEmail($seekerName, $jobTitle, $companyName, $baseUrl) {
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8fafc; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #6B7280, #4B5563); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='color: white; margin: 0;'>Application Update</h1>
        </div>
        <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px;'>
            <h2>Hi $seekerName,</h2>
            <p>Thank you for your interest in the following position:</p>
            <div style='background: #f3f4f6; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; margin: 20px 0;'>
                <h3 style='margin: 0 0 8px 0; color: #374151;'>$jobTitle</h3>
                <p style='margin: 0; color: #6B7280;'>at <strong>$companyName</strong></p>
            </div>
            <p>Unfortunately, the employer has decided to move forward with other candidates at this time.</p>
            <p style='color: #6B7280;'>Don't be discouraged! There are many more opportunities waiting for you on KaziLink.</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$baseUrl/frontend/index.html' style='background: #6366F1; color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                    Browse More Jobs
                </a>
            </div>
            <p style='font-size: 12px; color: #64748b;'>Keep applying - your dream job is out there!</p>
        </div>
    </div>
    ";
}

function getNewJobMatchEmail($seekerName, $jobTitle, $companyName, $location, $salary, $category, $jobId, $baseUrl) {
    $jobLink = $baseUrl . "/frontend/index.html#job-" . $jobId;
    $formattedSalary = number_format($salary);
    
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8fafc; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #6366F1, #0D9488); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='color: white; margin: 0;'>🔥 New Job Match!</h1>
        </div>
        <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px;'>
            <h2>Hi $seekerName,</h2>
            <p>We found a new job that matches your skills and interests:</p>
            <div style='background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px; padding: 16px; margin: 20px 0;'>
                <h3 style='margin: 0 0 8px 0; color: #4338ca;'>$jobTitle</h3>
                <p style='margin: 0 0 4px 0; color: #6366F1;'><strong>$companyName</strong></p>
                <p style='margin: 0; color: #6B7280; font-size: 14px;'>
                     $location | 💰 KES $formattedSalary | 🏷️ $category
                </p>
            </div>
            <p>This job was just posted and matches your profile. Don't miss this opportunity!</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='$jobLink' style='background: #6366F1; color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                    View & Apply Now
                </a>
            </div>
            <p style='font-size: 12px; color: #64748b;'>You're receiving this because you have a KaziLink account. Update your skills in your profile to get better matches.</p>
        </div>
    </div>
    ";
}

function getContactEmail($name, $email, $subject, $message) {
    $safeMessage = nl2br(htmlspecialchars($message));
    $safeName = htmlspecialchars($name);
    $safeSubject = htmlspecialchars($subject);
    
    return "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8fafc; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #6366F1, #0D9488); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
            <h1 style='color: white; margin: 0;'>📬 New Contact Message</h1>
        </div>
        <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px;'>
            <div style='background: #f1f5f9; padding: 16px; border-radius: 8px; margin-bottom: 20px;'>
                <p style='margin: 0 0 8px 0;'><strong>From:</strong> $safeName</p>
                <p style='margin: 0 0 8px 0;'><strong>Email:</strong> <a href='mailto:$email' style='color: #6366F1;'>$email</a></p>
                <p style='margin: 0;'><strong>Subject:</strong> $safeSubject</p>
            </div>
            <h3 style='color: #334155; margin-bottom: 10px;'>Message:</h3>
            <div style='background: #f8fafc; border-left: 4px solid #6366F1; padding: 16px; border-radius: 4px;'>
                <p style='margin: 0; color: #475569; line-height: 1.6;'>$safeMessage</p>
            </div>
            <div style='margin-top: 20px; padding-top: 20px; border-top: 1px solid #e2e8f0;'>
                <a href='mailto:$email' style='background: #6366F1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>
                    Reply to $safeName
                </a>
            </div>
            <p style='font-size: 12px; color: #94a3b8; margin-top: 20px;'>Sent from KaziLink Contact Form</p>
        </div>
    </div>
    ";
}
?>