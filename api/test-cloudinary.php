<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$creds = getCloudinaryCredentials();

if (!$creds) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Cloudinary not configured',
        'env_check' => [
            'CLOUDINARY_URL' => getenv('CLOUDINARY_URL') ? 'SET (masked)' : 'NOT SET',
            'CLOUDINARY_CLOUD_NAME' => getenv('CLOUDINARY_CLOUD_NAME') ?: 'NOT SET',
            'CLOUDINARY_API_KEY' => getenv('CLOUDINARY_API_KEY') ? 'SET' : 'NOT SET',
            'CLOUDINARY_API_SECRET' => getenv('CLOUDINARY_API_SECRET') ? 'SET' : 'NOT SET',
        ]
    ]);
    exit;
}

echo json_encode([
    'status' => 'ok',
    'cloud_name' => $creds['cloud_name'],
    'api_key' => substr($creds['api_key'], 0, 5) . '...',
    'api_secret_length' => strlen($creds['api_secret']),
]);
