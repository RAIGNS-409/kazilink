<?php
// CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

// Load email helper if exists, otherwise define fallback functions
if (file_exists(__DIR__ . '/emailHelper.php')) {
    require_once 'emailHelper.php';
} else {
    if (!function_exists('sendEmail')) {
        function sendEmail($to, $subject, $body) { return true; }
    }
    if (!function_exists('generateToken')) {
        function generateToken() { return bin2hex(random_bytes(32)); }
    }
    if (!function_exists('getVerificationEmail')) {
        function getVerificationEmail($name, $token, $baseUrl) { return "Verify: $baseUrl/frontend/verify-email.html?token=$token"; }
    }
    if (!function_exists('getPasswordResetEmail')) {
        function getPasswordResetEmail($name, $token, $baseUrl) { return "Reset: $baseUrl/frontend/reset-password.html?token=$token"; }
    }
    if (!function_exists('getAccountVerifiedEmail')) {
        function getAccountVerifiedEmail($name) { return "Account verified!"; }
    }
    if (!function_exists('getApplicationAcceptedEmail')) {
        function getApplicationAcceptedEmail($name, $jobTitle, $company, $baseUrl) { 
            return "<h2>Hi $name,</h2><p>Your application for <strong>$jobTitle</strong> at <strong>$company</strong> has been accepted! 🎉</p>"; 
        }
    }
    if (!function_exists('getApplicationRejectedEmail')) {
        function getApplicationRejectedEmail($name, $jobTitle, $company, $baseUrl) { 
            return "<h2>Hi $name,</h2><p>Thank you for applying to <strong>$jobTitle</strong> at <strong>$company</strong>. Unfortunately, we've decided to move forward with other candidates.</p>"; 
        }
    }
    if (!function_exists('getNewJobMatchEmail')) {
        function getNewJobMatchEmail($name, $jobTitle, $company, $location, $salary, $category, $jobId, $baseUrl) { 
            return "<h2>Hi $name,</h2><p>New job match: <strong>$jobTitle</strong> at <strong>$company</strong> in $location (KES $salary)</p>"; 
        }
    }
    if (!function_exists('getContactEmail')) {
        function getContactEmail($name, $email, $subject, $message) {
            $safeMessage = nl2br(htmlspecialchars($message));
            $safeName = htmlspecialchars($name);
            $safeSubject = htmlspecialchars($subject);
            return "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #f8fafc; padding: 20px;'>
                <div style='background: linear-gradient(135deg, #6366F1, #0D9488); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h1 style='color: white; margin: 0;'> New Contact Message</h1>
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
    }
}

// Parse input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if ($input === null && !empty($_POST)) {
    $input = $_POST;
}

if ($input === null) {
    $input = [];
}

$action = $_GET['action'] ?? '';

function sendResponse($status, $message, $data = null) {
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// ==================== AUTH ====================

if ($action === 'login') {
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        sendResponse('error', 'Email and password are required');
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse('error', 'No account found with this email');
        }

        // Check if email is verified
        if (isset($user['email_verified']) && !$user['email_verified']) {
            sendResponse('error', 'Please verify your email before logging in. Check your inbox for the verification link.');
        }

        // Check account status
        if (isset($user['account_status']) && $user['account_status'] === 'suspended') {
            sendResponse('error', 'Your account has been suspended. Please contact support.');
        }

        $passwordMatch = false;
        if (password_verify($password, $user['password'])) {
            $passwordMatch = true;
        } elseif ($password === $user['password']) {
            $passwordMatch = true;
        }

        if ($passwordMatch) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            sendResponse('success', 'Login successful', $user);
        } else {
            sendResponse('error', 'Incorrect password');
        }
    } catch (Exception $e) {
        sendResponse('error', 'Login failed: ' . $e->getMessage());
    }
}

if ($action === 'register') {
    $name = $input['full_name'] ?? '';
    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';
    $role = $input['role'] ?? 'seeker';
    $phone = $input['phone'] ?? '';
    $company = ($role === 'employer') ? $name : null;

    if (empty($name) || empty($email) || empty($password)) {
        sendResponse('error', 'Name, email, and password are required');
    }

    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $verificationToken = generateToken();
        $tokenExpiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, phone, company, verification_token, token_expires, email_verified, account_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'pending')");
        $stmt->execute([$name, $email, $hashedPassword, $role, $phone, $company, $verificationToken, $tokenExpiry]);
        
        $newUserId = $pdo->lastInsertId();
        
        // Send verification email
        require_once 'emailHelper.php';
        $emailBody = getVerificationEmail($name, $verificationToken, BASE_URL);
        $emailSent = sendEmail($email, 'Verify Your Email - KaziLink', $emailBody);
        
        if ($emailSent) {
            sendResponse('success', 'Registration successful! Please check your email to verify your account.', ['user_id' => $newUserId]);
        } else {
            sendResponse('error', 'Account created but failed to send verification email. Please contact support.');
        }
    } catch (Exception $e) {
        sendResponse('error', 'Registration failed: ' . $e->getMessage());
    }
}

if ($action === 'get_user') {
    if (!isset($_SESSION['user_id'])) {
        sendResponse('error', 'Not logged in');
    }
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if ($user) {
            sendResponse('success', 'User fetched', $user);
        } else {
            sendResponse('error', 'User not found');
        }
    } catch (Exception $e) {
        sendResponse('error', 'Error: ' . $e->getMessage());
    }
}

if ($action === 'logout') {
    session_destroy();
    sendResponse('success', 'Logged out');
}

// ==================== JOBS ====================

if ($action === 'get_jobs') {
    try {
        // Fixed: use profile_picture instead of avatar
        $stmt = $pdo->query("SELECT jobs.*, users.company, users.profile_picture as employer_avatar FROM jobs JOIN users ON jobs.employer_id = users.id ORDER BY posted_at DESC");
        $jobs = $stmt->fetchAll();
        sendResponse('success', 'Jobs fetched', $jobs);
    } catch (Exception $e) {
        sendResponse('error', 'Failed to fetch jobs: ' . $e->getMessage());
    }
}

if ($action === 'post_job') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
        sendResponse('error', 'Unauthorized - must be employer');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO jobs (employer_id, title, category, description, location, salary, deadline, job_type, required_qualifications) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $input['title'] ?? '',
            $input['category'] ?? '',
            $input['description'] ?? '',
            $input['location'] ?? '',
            $input['salary'] ?? 0,
            $input['deadline'] ?? '',
            $input['job_type'] ?? 'Full-time',
            $input['required_qualifications'] ?? ''
        ]);
        
        $newJobId = $pdo->lastInsertId();
        $jobTitle = $input['title'] ?? '';
        $jobCategory = $input['category'] ?? '';
        $jobLocation = $input['location'] ?? '';
        $jobSalary = $input['salary'] ?? 0;
        
        // Notify matching seekers
        require_once 'emailHelper.php';
        
        // Find all verified seekers whose skills match the job category
        $stmt = $pdo->prepare("
            SELECT id, full_name, email, skills 
            FROM users 
            WHERE role = 'seeker' 
            AND email_verified = 1 
            AND account_status = 'active'
            AND skills IS NOT NULL 
            AND skills != ''
        ");
        $stmt->execute();
        $seekers = $stmt->fetchAll();
        
        $notifiedCount = 0;
        foreach ($seekers as $seeker) {
            $seekerSkills = strtolower($seeker['skills']);
            $jobCategoryLower = strtolower($jobCategory);
            $jobTitleLower = strtolower($jobTitle);
            
            // Check if seeker's skills match the job category or title
            $skillsArray = array_map('trim', explode(',', $seekerSkills));
            $isMatch = false;
            
            foreach ($skillsArray as $skill) {
                $skillLower = strtolower($skill);
                if (
                    strpos($jobCategoryLower, $skillLower) !== false ||
                    strpos($jobTitleLower, $skillLower) !== false ||
                    strpos($skillLower, $jobCategoryLower) !== false
                ) {
                    $isMatch = true;
                    break;
                }
            }
            
            if ($isMatch) {
                $companyStmt = $pdo->prepare("SELECT company, full_name FROM users WHERE id = ?");
                $companyStmt->execute([$_SESSION['user_id']]);
                $employer = $companyStmt->fetch();
                $companyName = $employer['company'] ?: $employer['full_name'];
                
                $emailBody = getNewJobMatchEmail(
                    $seeker['full_name'],
                    $jobTitle,
                    $companyName,
                    $jobLocation,
                    $jobSalary,
                    $jobCategory,
                    $newJobId,
                    BASE_URL
                );
                
                sendEmail(
                    $seeker['email'],
                    "🔥 New Job Match: $jobTitle at $companyName",
                    $emailBody
                );
                $notifiedCount++;
            }
        }
        
        sendResponse('success', "Job posted successfully! Notified $notifiedCount matching seekers.");
    } catch (Exception $e) {
        sendResponse('error', 'Failed to post job: ' . $e->getMessage());
    }
}

if ($action === 'apply_job') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seeker') {
        sendResponse('error', 'Only seekers can apply');
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM applications WHERE job_id = ? AND applicant_id = ?");
        $stmt->execute([$input['job_id'] ?? 0, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            sendResponse('error', 'Already applied');
        }

        $stmt = $pdo->prepare("INSERT INTO applications (job_id, applicant_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$input['job_id'] ?? 0, $_SESSION['user_id']]);
        sendResponse('success', 'Application submitted');
    } catch (Exception $e) {
        sendResponse('error', 'Failed to apply: ' . $e->getMessage());
    }
}

// ==================== PROFILE ====================

if ($action === 'update_profile') {
    if (!isset($_SESSION['user_id'])) {
        sendResponse('error', 'Not logged in');
    }
    try {
        $stmt = $pdo->prepare("UPDATE users SET full_name=?, phone=?, bio=?, skills=? WHERE id=?");
        $stmt->execute([
            $input['full_name'] ?? '',
            $input['phone'] ?? '',
            $input['bio'] ?? '',
            $input['skills'] ?? '',
            $_SESSION['user_id']
        ]);
        sendResponse('success', 'Profile updated');
    } catch (Exception $e) {
        sendResponse('error', 'Failed to update: ' . $e->getMessage());
    }
}

if ($action === 'add_qualification') {
    if (!isset($_SESSION['user_id'])) {
        sendResponse('error', 'Not logged in');
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO user_qualifications (user_id, qualification_name, issuer, year_obtained, document_path) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $input['name'] ?? '',
            $input['issuer'] ?? '',
            $input['date'] ?? '',
            $input['doc'] ?? ''
        ]);
        sendResponse('success', 'Qualification added');
    } catch (Exception $e) {
        sendResponse('error', 'Failed to add: ' . $e->getMessage());
    }
}

if ($action === 'add_experience') {
    if (!isset($_SESSION['user_id'])) {
        sendResponse('error', 'Not logged in');
    }
    try {
        $stmt = $pdo->prepare("INSERT INTO user_experience (user_id, job_title, duration, proof_details) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $input['title'] ?? '',
            $input['duration'] ?? '',
            $input['proof'] ?? ''
        ]);
        sendResponse('success', 'Experience added');
    } catch (Exception $e) {
        sendResponse('error', 'Failed to add: ' . $e->getMessage());
    }
}

if ($action === 'update_social_links') {
    if (!isset($_SESSION['user_id'])) {
        sendResponse('error', 'Not logged in');
    }
    try {
        $stmt = $pdo->prepare("UPDATE users SET portfolio_url=?, github_url=?, linkedin_url=?, twitter_url=?, facebook_url=?, instagram_url=? WHERE id=?");
        $stmt->execute([
            $input['portfolio_url'] ?? '',
            $input['github_url'] ?? '',
            $input['linkedin_url'] ?? '',
            $input['twitter_url'] ?? '',
            $input['facebook_url'] ?? '',
            $input['instagram_url'] ?? '',
            $_SESSION['user_id']
        ]);
        sendResponse('success', 'Social links updated');
    } catch (Exception $e) {
        sendResponse('error', 'Failed: ' . $e->getMessage());
    }
}

if ($action === 'upload_profile_picture') {
    if (!isset($_SESSION['user_id'])) {
        sendResponse('error', 'Not logged in');
    }

    if (!isset($_FILES['profile_picture']) || $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
        sendResponse('error', 'No file uploaded');
    }

    $file = $_FILES['profile_picture'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedTypes = ['jpg', 'jpeg', 'png'];

    if (!in_array($fileExtension, $allowedTypes)) {
        sendResponse('error', 'Invalid file type. Only JPG, JPEG, PNG allowed');
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        sendResponse('error', 'File too large (max 2MB)');
    }

    $newFileName = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $fileExtension;
    $uploadDir = __DIR__ . '/../uploads/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $destination = $uploadDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $filePath = '/kazilink/uploads/' . $newFileName;

        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $stmt->execute([$filePath, $_SESSION['user_id']]);

        sendResponse('success', 'Profile picture updated', ['file_path' => $filePath]);
    } else {
        sendResponse('error', 'Upload failed');
    }
}

// ==================== SEEKER APPLICATIONS ====================

if ($action === 'get_my_applications') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'seeker') {
        sendResponse('error', 'Only seekers can view applications');
    }
    try {
        // Fixed: changed j.company to u.company (company is in users table, not jobs)
        $stmt = $pdo->prepare("
            SELECT a.id, a.status, a.applied_at, 
                   j.title, u.company, j.location, j.salary, j.job_type,
                   u.full_name as employer_name, u.email as employer_email
            FROM applications a
            JOIN jobs j ON a.job_id = j.id
            JOIN users u ON j.employer_id = u.id
            WHERE a.applicant_id = ?
            ORDER BY a.applied_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $applications = $stmt->fetchAll();
        sendResponse('success', 'Applications fetched', $applications);
    } catch (Exception $e) {
        sendResponse('error', 'Failed: ' . $e->getMessage());
    }
}

// ==================== EMPLOYER APPLICANTS ====================

if ($action === 'get_job_applicants') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
        sendResponse('error', 'Only employers can view applicants');
    }
    
    $jobId = $input['job_id'] ?? 0;
    if (!$jobId) {
        sendResponse('error', 'Job ID is required');
    }

    try {
        // Verify this job belongs to the employer
        $stmt = $pdo->prepare("SELECT id FROM jobs WHERE id = ? AND employer_id = ?");
        $stmt->execute([$jobId, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            sendResponse('error', 'Unauthorized - job does not belong to you');
        }

        $stmt = $pdo->prepare("
            SELECT a.id as application_id, a.status, a.applied_at,
                   u.id as applicant_id, u.full_name, u.email, u.phone, u.bio, u.skills, u.profile_picture,
                   u.portfolio_url, u.github_url, u.linkedin_url
            FROM applications a
            JOIN users u ON a.applicant_id = u.id
            WHERE a.job_id = ?
            ORDER BY a.applied_at DESC
        ");
        $stmt->execute([$jobId]);
        $applicants = $stmt->fetchAll();
        sendResponse('success', 'Applicants fetched', $applicants);
    } catch (Exception $e) {
        sendResponse('error', 'Failed: ' . $e->getMessage());
    }
}

if ($action === 'update_application_status') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'employer') {
        sendResponse('error', 'Only employers can update status');
    }
    
    $applicationId = $input['application_id'] ?? 0;
    $status = $input['status'] ?? '';
    
    if (!$applicationId || !in_array($status, ['accepted', 'rejected'])) {
        sendResponse('error', 'Invalid application ID or status');
    }

    try {
        // Verify employer owns the job AND get application details
        $stmt = $pdo->prepare("
            SELECT a.id, a.applicant_id, a.job_id, j.title as job_title, j.employer_id,
                   u.full_name as seeker_name, u.email as seeker_email,
                   emp.company as company_name
            FROM applications a
            JOIN jobs j ON a.job_id = j.id
            JOIN users u ON a.applicant_id = u.id
            JOIN users emp ON j.employer_id = emp.id
            WHERE a.id = ? AND j.employer_id = ?
        ");
        $stmt->execute([$applicationId, $_SESSION['user_id']]);
        $appDetails = $stmt->fetch();
        
        if (!$appDetails) {
            sendResponse('error', 'Unauthorized');
        }

        // Update the status
        $stmt = $pdo->prepare("UPDATE applications SET status = ? WHERE id = ?");
        $stmt->execute([$status, $applicationId]);
        
        // Send email notification to the seeker
        require_once 'emailHelper.php';
        $seekerName = $appDetails['seeker_name'];
        $seekerEmail = $appDetails['seeker_email'];
        $jobTitle = $appDetails['job_title'];
        $companyName = $appDetails['company_name'] ?: 'Unknown Company';
        
        if ($status === 'accepted') {
            $emailBody = getApplicationAcceptedEmail($seekerName, $jobTitle, $companyName, BASE_URL);
            sendEmail($seekerEmail, "🎉 Application Accepted - $jobTitle at $companyName", $emailBody);
        } elseif ($status === 'rejected') {
            $emailBody = getApplicationRejectedEmail($seekerName, $jobTitle, $companyName, BASE_URL);
            sendEmail($seekerEmail, "Application Update - $jobTitle at $companyName", $emailBody);
        }
        
        sendResponse('success', 'Application status updated to ' . $status . ' and notification sent');
    } catch (Exception $e) {
        sendResponse('error', 'Failed: ' . $e->getMessage());
    }
}

// ==================== ADMIN ====================

if ($action === 'get_all_users') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        sendResponse('error', 'Unauthorized');
    }
    try {
        $stmt = $pdo->query("SELECT id, full_name, email, role, phone, created_at FROM users ORDER BY created_at DESC");
        $users = $stmt->fetchAll();
        sendResponse('success', 'Users fetched', $users);
    } catch (Exception $e) {
        sendResponse('error', 'Failed: ' . $e->getMessage());
    }
}

if ($action === 'delete_user') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        sendResponse('error', 'Unauthorized');
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
        $stmt->execute([$input['user_id'] ?? 0]);
        sendResponse('success', 'User deleted');
    } catch (Exception $e) {
        sendResponse('error', 'Failed: ' . $e->getMessage());
    }
}

if ($action === 'get_stats') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        sendResponse('error', 'Unauthorized');
    }
    try {
        $stats = [];
        $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['total_jobs'] = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
        $stats['active_jobs'] = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status='active'")->fetchColumn();
        $stats['total_applications'] = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
        sendResponse('success', 'Stats fetched', $stats);
    } catch (Exception $e) {
        sendResponse('error', 'Failed: ' . $e->getMessage());
    }
}

if ($action === 'get_all_jobs') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        sendResponse('error', 'Unauthorized');
    }
    try {
        $stmt = $pdo->query("SELECT jobs.*, users.full_name as employer_name, users.email as employer_email, users.company FROM jobs JOIN users ON jobs.employer_id = users.id ORDER BY jobs.posted_at DESC");
        $jobs = $stmt->fetchAll();
        sendResponse('success', 'Jobs fetched', $jobs);
    } catch (Exception $e) {
        sendResponse('error', 'Failed: ' . $e->getMessage());
    }
}

if ($action === 'delete_job') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        sendResponse('error', 'Unauthorized');
    }
    try {
        $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
        $stmt->execute([$input['job_id'] ?? 0]);
        sendResponse('success', 'Job deleted');
    } catch (Exception $e) {
        sendResponse('error', 'Failed: ' . $e->getMessage());
    }
}

if ($action === 'toggle_job_status') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        sendResponse('error', 'Unauthorized');
    }
    try {
        $stmt = $pdo->prepare("SELECT status FROM jobs WHERE id = ?");
        $stmt->execute([$input['job_id'] ?? 0]);
        $job = $stmt->fetch();

        $newStatus = $job['status'] === 'active' ? 'closed' : 'active';
        $stmt = $pdo->prepare("UPDATE jobs SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $input['job_id'] ?? 0]);

        sendResponse('success', 'Job status updated to ' . $newStatus);
    } catch (Exception $e) {
        sendResponse('error', 'Failed: ' . $e->getMessage());
    }
}

if ($action === 'get_all_applications') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        sendResponse('error', 'Unauthorized');
    }
    try {
        $stmt = $pdo->query("SELECT a.*, u.full_name as applicant_name, u.email as applicant_email, j.title as job_title, e.full_name as employer_name, e.company FROM applications a JOIN users u ON a.applicant_id = u.id JOIN jobs j ON a.job_id = j.id JOIN users e ON j.employer_id = e.id ORDER BY a.applied_at DESC");
        $apps = $stmt->fetchAll();
        sendResponse('success', 'Applications fetched', $apps);
    } catch (Exception $e) {
        sendResponse('error', 'Failed: ' . $e->getMessage());
    }
}

if ($action === 'get_dashboard_stats') {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        sendResponse('error', 'Unauthorized');
    }
    try {
        $stats = [];
        $stats['total_users'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $stats['total_seekers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='seeker'")->fetchColumn();
        $stats['total_employers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role='employer'")->fetchColumn();
        $stats['total_jobs'] = $pdo->query("SELECT COUNT(*) FROM jobs")->fetchColumn();
        $stats['active_jobs'] = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status='active'")->fetchColumn();
        $stats['closed_jobs'] = $pdo->query("SELECT COUNT(*) FROM jobs WHERE status='closed'")->fetchColumn();
        $stats['total_applications'] = $pdo->query("SELECT COUNT(*) FROM applications")->fetchColumn();
        $stats['pending_apps'] = $pdo->query("SELECT COUNT(*) FROM applications WHERE status='pending'")->fetchColumn();
        $stats['accepted_apps'] = $pdo->query("SELECT COUNT(*) FROM applications WHERE status='accepted'")->fetchColumn();
        $stats['rejected_apps'] = $pdo->query("SELECT COUNT(*) FROM applications WHERE status='rejected'")->fetchColumn();

        $stmt = $pdo->query("SELECT category, COUNT(*) as count FROM jobs GROUP BY category");
        $stats['jobs_by_category'] = $stmt->fetchAll();

        sendResponse('success', 'Stats fetched', $stats);
    } catch (Exception $e) {
        sendResponse('error', 'Failed: ' . $e->getMessage());
    }
}

// ==================== EMAIL VERIFICATION & PASSWORD RESET ====================

if ($action === 'send_verification') {
    if (!isset($_SESSION['user_id'])) {
        sendResponse('error', 'Not logged in');
    }

    try {
        $token = generateToken();
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $pdo->prepare("UPDATE users SET verification_token = ?, token_expires = ? WHERE id = ?");
        $stmt->execute([$token, $expires, $_SESSION['user_id']]);

        $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        $emailBody = getVerificationEmail($user['full_name'], $token, BASE_URL);

        if (sendEmail($user['email'], 'Verify Your Email - KaziLink', $emailBody)) {
            sendResponse('success', 'Verification email sent! Check your inbox.');
        } else {
            sendResponse('error', 'Failed to send email. Please try again.');
        }
    } catch (Exception $e) {
        sendResponse('error', 'Error: ' . $e->getMessage());
    }
}

if ($action === 'verify_email') {
    $token = trim($input['token'] ?? '');

    if (empty($token)) {
        sendResponse('error', 'Invalid verification link');
    }

    try {
        $stmt = $pdo->prepare("SELECT id, email_verified FROM users WHERE verification_token = ? AND token_expires > NOW()");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse('error', 'Invalid or expired verification link');
        }

        if ($user['email_verified']) {
            sendResponse('success', 'Email already verified');
        }

        $stmt = $pdo->prepare("UPDATE users SET email_verified = 1, verification_token = NULL, token_expires = NULL, account_status = 'active' WHERE id = ?");
        $stmt->execute([$user['id']]);

        // Send confirmation email
        $stmt = $pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $stmt->execute([$user['id']]);
        $verifiedUser = $stmt->fetch();
        
        $confirmationBody = getAccountVerifiedEmail($verifiedUser['full_name']);
        sendEmail($verifiedUser['email'], 'Account Verified - KaziLink', $confirmationBody);

        sendResponse('success', 'Email verified successfully! You can now login.');
    } catch (Exception $e) {
        sendResponse('error', 'Error: ' . $e->getMessage());
    }
}

if ($action === 'forgot_password') {
    $email = trim($input['email'] ?? '');

    if (empty($email)) {
        sendResponse('error', 'Email is required');
    }

    try {
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse('success', 'If an account exists with this email, a reset link has been sent.');
        }

        $token = generateToken();
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
        $stmt->execute([$token, $expires, $user['id']]);

        $emailBody = getPasswordResetEmail($user['full_name'], $token, BASE_URL);

        sendEmail($email, 'Password Reset Request - KaziLink', $emailBody);

        sendResponse('success', 'If an account exists with this email, a reset link has been sent.');
    } catch (Exception $e) {
        sendResponse('error', 'Error: ' . $e->getMessage());
    }
}

if ($action === 'reset_password') {
    $token = trim($input['token'] ?? '');
    $newPassword = $input['new_password'] ?? '';

    if (empty($token) || empty($newPassword)) {
        sendResponse('error', 'Token and new password are required');
    }

    if (strlen($newPassword) < 6) {
        sendResponse('error', 'Password must be at least 6 characters');
    }

    try {
        $stmt = $pdo->prepare("SELECT id, reset_token, reset_token_expires FROM users WHERE reset_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            sendResponse('error', 'Invalid or expired reset link. Please request a new one.');
        }

        // Check if expired
        $expires = strtotime($user['reset_token_expires']);
        $now = time();
        
        if ($expires < $now) {
            sendResponse('error', 'Reset link has expired. Please request a new one.');
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
        $stmt->execute([$hashedPassword, $user['id']]);

        sendResponse('success', 'Password reset successfully! You can now login.');
    } catch (Exception $e) {
        sendResponse('error', 'Error: ' . $e->getMessage());
    }
}

if ($action === 'check_verification') {
    if (!isset($_SESSION['user_id'])) {
        sendResponse('error', 'Not logged in');
    }

    try {
        $stmt = $pdo->prepare("SELECT email_verified, account_status FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        sendResponse('success', 'Status fetched', [
            'email_verified' => $user['email_verified'],
            'account_status' => $user['account_status']
        ]);
    } catch (Exception $e) {
        sendResponse('error', 'Error: ' . $e->getMessage());
    }
}

// ==================== CONTACT FORM ====================

if ($action === 'contact') {
    $name = trim($input['name'] ?? '');
    $email = trim($input['email'] ?? '');
    $subject = trim($input['subject'] ?? '');
    $message = trim($input['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        sendResponse('error', 'All fields are required');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendResponse('error', 'Invalid email address');
    }

    try {
        require_once 'emailHelper.php';
        
        // Send to admin email
        $adminEmail = 'kibetdaniel411@gmail.com';
        $emailBody = getContactEmail($name, $email, $subject, $message);
        
        $sent = sendEmail($adminEmail, "📬 Contact Form: $subject", $emailBody);
        
        if ($sent) {
            sendResponse('success', 'Message sent successfully! We will get back to you soon.');
        } else {
            sendResponse('error', 'Failed to send message. Please try again later.');
        }
    } catch (Exception $e) {
        sendResponse('error', 'Error: ' . $e->getMessage());
    }
}

// If no action matched
sendResponse('error', 'Invalid action: ' . $action);
?>