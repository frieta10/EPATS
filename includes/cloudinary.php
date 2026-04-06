<?php
// ============================================================
// Cloudinary Upload Helper
// Free tier: 25 GB storage / 25 GB bandwidth per month
// Setup: https://cloudinary.com/users/register/free
// ============================================================

/**
 * Get Cloudinary credentials from environment
 * Supports both individual vars and CLOUDINARY_URL
 */
function getCloudinaryCredentials(): ?array {
    // Try individual vars first
    $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey = getenv('CLOUDINARY_API_KEY');
    $apiSecret = getenv('CLOUDINARY_API_SECRET');
    
    if ($cloudName && $apiKey && $apiSecret) {
        return [
            'cloud_name' => $cloudName,
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
        ];
    }
    
    // Try CLOUDINARY_URL format: cloudinary://api_key:api_secret@cloud_name
    $url = getenv('CLOUDINARY_URL');
    if ($url) {
        $parsed = parse_url($url);
        if ($parsed && isset($parsed['host']) && isset($parsed['user']) && isset($parsed['pass'])) {
            return [
                'cloud_name' => $parsed['host'],
                'api_key' => $parsed['user'],
                'api_secret' => $parsed['pass'],
            ];
        }
    }
    
    return null;
}

/**
 * Upload an image file to Cloudinary.
 * Returns the secure HTTPS URL of the uploaded image, or null on failure.
 */
function uploadToCloudinary(string $tmpFilePath, string $folder = 'epats'): ?string {
    $creds = getCloudinaryCredentials();
    if (!$creds) {
        return null; // Credentials not configured
    }
    
    $cloudName = $creds['cloud_name'];
    $apiKey = $creds['api_key'];
    $apiSecret = $creds['api_secret'];

    $timestamp = time();
    $params    = [
        'folder'    => $folder,
        'timestamp' => $timestamp,
    ];

    // Build signed request
    ksort($params);
    $signStr  = urldecode(http_build_query($params)) . $apiSecret;
    $signature = sha1($signStr);

    $postFields = array_merge($params, [
        'file'      => new CURLFile($tmpFilePath),
        'api_key'   => $apiKey,
        'signature' => $signature,
    ]);

    $ch = curl_init("https://api.cloudinary.com/v1_1/{$cloudName}/image/upload");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || !$response) return null;

    $data = json_decode($response, true);
    return $data['secure_url'] ?? null;
}

/**
 * Check whether Cloudinary is configured via environment variables.
 */
function cloudinaryEnabled(): bool {
    return getCloudinaryCredentials() !== null;
}
