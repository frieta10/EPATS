<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$db = getDB();

// Gather fields
$name        = trim($_POST['name'] ?? '');
$email       = trim($_POST['email'] ?? '');
$phone       = trim($_POST['phone'] ?? '');
$attending   = $_POST['attending'] ?? 'yes';
$plusOne     = !empty($_POST['plus_one']) ? 1 : 0;
$plusOneName = trim($_POST['plus_one_name'] ?? '');
$dietary     = trim($_POST['dietary'] ?? '');
$message     = trim($_POST['message'] ?? '');
$cityCountry = trim($_POST['city_country'] ?? '');
$guestToken  = trim($_POST['guest_token'] ?? '');
$capsuleMsg  = trim($_POST['capsule_message'] ?? '');

if (!$name) {
    echo json_encode(['error' => 'Name is required']);
    exit;
}
if (!in_array($attending, ['yes', 'no', 'maybe'])) {
    $attending = 'yes';
}

// Parse city / country
$city = $country = '';
if (strpos($cityCountry, ',') !== false) {
    [$city, $country] = array_map('trim', explode(',', $cityCountry, 2));
} else {
    $city = $cityCountry;
}

// Resolve guest_id
$guestId = null;
if ($guestToken) {
    $gs = $db->prepare('SELECT id FROM guests WHERE invite_token = ?');
    $gs->execute([$guestToken]);
    $g = $gs->fetch();
    if ($g) $guestId = $g['id'];
}

// Check for duplicate (same name + email within 10 min)
if ($email) {
    $dup = $db->prepare("SELECT id FROM rsvp_responses WHERE email = ? AND created_at > NOW() - INTERVAL '10 minutes'");
    $dup->execute([$email]);
    if ($dup->fetch()) {
        echo json_encode(['error' => 'You have already submitted an RSVP recently.']);
        exit;
    }
}

$qrToken = generateToken(16);

// Handle time capsule photo upload
$capsulePhoto = null;
if (!empty($_FILES['capsule_photo']) && $_FILES['capsule_photo']['error'] === UPLOAD_ERR_OK) {
    $capsulePhoto = handleUpload($_FILES['capsule_photo'], 'capsule');
}

// Geocode from city/country (simple fallback — use stored coords if available)
$lat = $lng = null;
// We store city/country only; a JS geocode call can update later

$db->beginTransaction();
try {
    $stmt = $db->prepare('
        INSERT INTO rsvp_responses
            (guest_id, name, email, phone, attending, plus_one, plus_one_name,
             dietary_requirements, message, city, country, qr_token)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        RETURNING id
    ');
    $stmt->execute([
        $guestId, $name, $email, $phone, $attending, $plusOne, $plusOneName,
        $dietary, $message, $city, $country, $qrToken
    ]);
    $rsvpId = $stmt->fetchColumn();

    // Time capsule
    if ($capsuleMsg) {
        $tc = $db->prepare('INSERT INTO time_capsule (rsvp_id, guest_name, message, photo_path) VALUES (?, ?, ?, ?)');
        $tc->execute([$rsvpId, $name, $capsuleMsg, $capsulePhoto]);
    }

    // Map pin
    if ($city || $country) {
        $mp = $db->prepare('INSERT INTO map_pins (rsvp_id, guest_name, city, country) VALUES (?, ?, ?, ?)');
        $mp->execute([$rsvpId, $name, $city, $country]);
    }

    // Update guest status
    if ($guestId) {
        $db->prepare("UPDATE guests SET notes = COALESCE(notes, '') || ' [RSVP: ' || ? || ']' WHERE id = ?")
           ->execute([$attending, $guestId]);
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['error' => 'Could not save RSVP: ' . $e->getMessage()]);
    exit;
}

$portalUrl = BASE_URL . '/guest-portal.php?token=' . $qrToken;

echo json_encode([
    'success'    => true,
    'qr_token'   => $qrToken,
    'portal_url' => $portalUrl,
    'attending'  => $attending,
    'name'       => $name,
]);
