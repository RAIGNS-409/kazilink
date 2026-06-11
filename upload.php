<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Get upload type from POST data
$uploadType = $_POST['type'] ?? 'document';

// Different allowed types based on upload type
$allowedTypes = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
$imageTypes = ['jpg', 'jpeg', 'png'];

if ($uploadType === 'profile') {
    $allowedTypes = $imageTypes;
    $maxSize = 2 * 1024 * 1024; // 2MB for profile pics
} else {
    $maxSize = 5 * 1024 * 1024; // 5MB for documents
}

if (!in_array($fileExtension, $allowedTypes)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowedTypes)]);
    exit;
}

if ($file['size'] > $maxSize) {
    $maxMB = $maxSize / (1024 * 1024);
    echo json_encode(['status' => 'error', 'message' => "File too large (max {$maxMB}MB)"]);
    exit;
}

$newFileName = uniqid($uploadType . '_', true) . '.' . $fileExtension;
$uploadDir = __DIR__ . '/../uploads/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$destination = $uploadDir . $newFileName;

if (move_uploaded_file($file['tmp_name'], $destination)) {
    $filePath = '/kazilink/uploads/' . $newFileName;
    
    // If it's a profile picture, update the user's profile_picture field
    if ($uploadType === 'profile') {
        $stmt = $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $stmt->execute([$filePath, $_SESSION['user_id']]);
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'File uploaded',
        'data' => [
            'file_path' => $filePath,
            'file_name' => $file['name']
        ]
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Upload failed']);
}
?>
