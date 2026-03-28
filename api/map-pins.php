<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$pins = getDB()->query("
    SELECT mp.guest_name, mp.city, mp.country, mp.lat, mp.lng, r.attending
    FROM map_pins mp
    LEFT JOIN rsvp_responses r ON r.id = mp.rsvp_id
    WHERE mp.city != '' OR mp.country != ''
    ORDER BY mp.created_at DESC
    LIMIT 200
")->fetchAll();

echo json_encode($pins);
