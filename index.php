<?php
require_once __DIR__ . '/config.php';
$s = getSettings();

// Personalised guest token
$guestName  = '';
$guestToken = '';
if (!empty($_GET['token'])) {
    $token = preg_replace('/[^a-f0-9]/', '', $_GET['token']);
    $stmt  = getDB()->prepare('SELECT name, invite_token FROM guests WHERE invite_token = ?');
    $stmt->execute([$token]);
    $guest = $stmt->fetch();
    if ($guest) {
        $guestName  = $guest['name'];
        $guestToken = $guest['invite_token'];
    }
}

// RSVP count for garden display
$attending = getDB()->query("SELECT COUNT(*) FROM rsvp_responses WHERE attending = 'yes'")->fetchColumn();
$total     = getDB()->query("SELECT COUNT(*) FROM rsvp_responses")->fetchColumn();

// Format event date parts
$eventDate  = $s['event_date'] ?? '2024-09-30';
$eventTs    = strtotime($eventDate);
$eventDay   = date('d', $eventTs);
$eventMonth = strtoupper(date('M', $eventTs));
$eventYear  = date('Y', $eventTs);

$coverPhoto  = !empty($s['cover_photo'])  ? UPLOAD_URL . $s['cover_photo']  : '';
$couplePhoto = !empty($s['couple_photo']) ? UPLOAD_URL . $s['couple_photo'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($s['couple_name_1']) ?> & <?= e($s['couple_name_2']) ?> — <?= e($s['ceremony_type']) ?> Invitation</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400;1,600&family=Montserrat:wght@300;400;500;600&family=Great+Vibes&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/main.css">
</head>
<body>

<!-- ══════════════════════════════════════════════
     AMBIENT AUDIO (optional)
══════════════════════════════════════════════ -->
<?php if (!empty($s['music_url'])): ?>
<audio id="bgMusic" loop <?= $s['music_autoplay'] === '1' ? 'autoplay' : '' ?>>
    <source src="<?= e($s['music_url']) ?>">
</audio>
<button class="music-toggle" id="musicToggle" title="Toggle music">
    <i class="fas fa-music"></i>
</button>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     FLOATING PARTICLES CANVAS
══════════════════════════════════════════════ -->
<canvas id="particleCanvas"></canvas>

<!-- ══════════════════════════════════════════════
     HERO SECTION
══════════════════════════════════════════════ -->
<section class="hero" id="hero">
    <div class="hero__bg" <?= $coverPhoto ? 'style="background-image:url(' . e($coverPhoto) . ')"' : '' ?>></div>
    <div class="hero__overlay"></div>

    <?php if ($guestName): ?>
    <div class="hero__greeting reveal-up" style="animation-delay:.1s">
        <span class="greeting-line">Dear <?= e($guestName) ?>,</span>
        <span class="greeting-sub">You are cordially invited</span>
    </div>
    <?php endif; ?>

    <!-- Botanical frame SVG -->
    <div class="botanical-frame reveal-scale">
        <svg class="frame-svg" viewBox="0 0 600 700" xmlns="http://www.w3.org/2000/svg">
            <!-- Main circle -->
            <circle cx="300" cy="350" r="230" fill="none" stroke="url(#goldGrad)" stroke-width="1.5" opacity="0.8"/>
            <circle cx="300" cy="350" r="226" fill="none" stroke="url(#goldGrad)" stroke-width="0.5" opacity="0.4"/>
            <!-- Shimmer arcs -->
            <path d="M130,250 Q300,20 470,250" fill="none" stroke="url(#goldGrad)" stroke-width="0.8" opacity="0.3"/>
            <defs>
                <linearGradient id="goldGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%"   stop-color="#C9A84C"/>
                    <stop offset="50%"  stop-color="#F0D080"/>
                    <stop offset="100%" stop-color="#C9A84C"/>
                </linearGradient>
            </defs>
        </svg>

        <!-- Botanical leaf decorations -->
        <div class="leaf leaf--tl"><img src="<?= BASE_URL ?>/assets/images/leaf-gold.svg" alt=""></div>
        <div class="leaf leaf--tr"><img src="<?= BASE_URL ?>/assets/images/leaf-gold.svg" alt=""></div>
        <div class="leaf leaf--bl"><img src="<?= BASE_URL ?>/assets/images/leaf-gold.svg" alt=""></div>
        <div class="pearl pearl--1"></div>
        <div class="pearl pearl--2"></div>
        <div class="pearl pearl--3"></div>
        <div class="pearl pearl--4"></div>
        <div class="pearl pearl--5"></div>

        <!-- Inner content -->
        <div class="hero__card">
            <p class="save-the-date reveal-up" style="animation-delay:.3s">SAVE THE DATE</p>
            <h1 class="names reveal-up" style="animation-delay:.5s">
                <span class="name-script"><?= e($s['couple_name_1']) ?></span>
                <span class="name-surname"><?= e($s['couple_surname_1'] ?? '') ?></span>
                <span class="ampersand">&amp;</span>
                <span class="name-script"><?= e($s['couple_name_2']) ?></span>
                <span class="name-surname"><?= e($s['couple_surname_2'] ?? '') ?></span>
            </h1>
            <p class="tagline reveal-up" style="animation-delay:.7s"><?= e($s['tagline']) ?></p>

            <div class="divider-ornament reveal-up" style="animation-delay:.9s">
                <span></span><i class="fas fa-diamond"></i><span></span>
            </div>

            <div class="event-details reveal-up" style="animation-delay:1.1s">
                <div class="event-detail">
                    <i class="fas fa-calendar-days"></i>
                    <div>
                        <strong><?= $eventDay ?> <?= $eventMonth ?></strong>
                        <span><?= $eventYear ?></span>
                    </div>
                </div>
                <div class="event-divider">|</div>
                <div class="event-detail">
                    <i class="fas fa-clock"></i>
                    <div>
                        <strong><?= e($s['event_time']) ?></strong>
                        <span><?= e($s['event_timezone']) ?></span>
                    </div>
                </div>
            </div>

            <div class="venue reveal-up" style="animation-delay:1.3s">
                <i class="fas fa-location-dot"></i>
                <div>
                    <strong><?= e($s['venue_name']) ?></strong>
                    <span><?= e($s['venue_address']) ?></span>
                </div>
            </div>

            <?php if (!empty($s['rsvp_phone_1']) || !empty($s['rsvp_phone_2'])): ?>
            <div class="rsvp-contacts reveal-up" style="animation-delay:1.5s">
                <p>RSVP:</p>
                <?php if (!empty($s['rsvp_phone_1'])): ?>
                <a href="tel:<?= e($s['rsvp_phone_1']) ?>"><?= e($s['rsvp_phone_1']) ?></a>
                <?php endif; ?>
                <?php if (!empty($s['rsvp_phone_2'])): ?>
                <a href="tel:<?= e($s['rsvp_phone_2']) ?>"><?= e($s['rsvp_phone_2']) ?></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <a href="#rsvp" class="scroll-cta reveal-up" style="animation-delay:1.8s">
        <span>RSVP Now</span>
        <i class="fas fa-chevron-down"></i>
    </a>
</section>

<!-- ══════════════════════════════════════════════
     COUNTDOWN SECTION
══════════════════════════════════════════════ -->
<section class="countdown-section section" id="countdown">
    <div class="section-ornament"></div>
    <h2 class="section-title reveal-up">Counting Down to Our Day</h2>
    <div class="countdown-grid reveal-up" data-date="<?= e($eventDate) ?>T<?= date('H:i', strtotime($s['event_time'])) ?>">
        <div class="countdown-unit"><span class="count-num" id="cd-days">00</span><span class="count-label">Days</span></div>
        <div class="countdown-unit"><span class="count-num" id="cd-hours">00</span><span class="count-label">Hours</span></div>
        <div class="countdown-unit"><span class="count-num" id="cd-mins">00</span><span class="count-label">Minutes</span></div>
        <div class="countdown-unit"><span class="count-num" id="cd-secs">00</span><span class="count-label">Seconds</span></div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     COUPLE STORY / PHOTO
══════════════════════════════════════════════ -->
<?php if ($couplePhoto || !empty($s['custom_message'])): ?>
<section class="story-section section" id="story">
    <div class="section-ornament"></div>
    <?php if ($couplePhoto): ?>
    <div class="couple-photo reveal-scale">
        <div class="couple-photo__frame">
            <img src="<?= e($couplePhoto) ?>" alt="Couple photo">
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($s['custom_message'])): ?>
    <blockquote class="couple-message reveal-up">
        <i class="fas fa-quote-left"></i>
        <?= nl2br(e($s['custom_message'])) ?>
        <i class="fas fa-quote-right"></i>
    </blockquote>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     RSVP FORM SECTION
══════════════════════════════════════════════ -->
<section class="rsvp-section section" id="rsvp">
    <div class="section-ornament"></div>
    <h2 class="section-title reveal-up">Kindly Respond</h2>
    <p class="section-subtitle reveal-up">Please respond by <?= formatDateShort($s['rsvp_deadline'] ?? $eventDate) ?></p>

    <div id="rsvpFormWrap" class="rsvp-form-wrap reveal-up">
        <form id="rsvpForm" class="rsvp-form">
            <input type="hidden" name="guest_token" value="<?= e($guestToken) ?>">

            <div class="form-row">
                <div class="form-group">
                    <label>Your Full Name *</label>
                    <input type="text" name="name" required placeholder="<?= $guestName ? e($guestName) : 'Enter your name' ?>" value="<?= e($guestName) ?>">
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="your@email.com">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" placeholder="+1 234 567 8900">
                </div>
                <div class="form-group">
                    <label>Will you attend? *</label>
                    <div class="attend-options">
                        <label class="radio-card"><input type="radio" name="attending" value="yes" checked><span><i class="fas fa-heart"></i> Joyfully Accepts</span></label>
                        <label class="radio-card"><input type="radio" name="attending" value="no"><span><i class="fas fa-heart-crack"></i> Regretfully Declines</span></label>
                        <label class="radio-card"><input type="radio" name="attending" value="maybe"><span><i class="fas fa-circle-question"></i> Maybe</span></label>
                    </div>
                </div>
            </div>

            <div class="form-row plus-one-row">
                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="plus_one" id="plusOneCheck">
                        <span>Bringing a plus one?</span>
                    </label>
                </div>
                <div class="form-group plus-one-name" id="plusOneName" style="display:none">
                    <label>Plus One Name</label>
                    <input type="text" name="plus_one_name" placeholder="Guest's name">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Your City & Country</label>
                    <input type="text" name="city_country" placeholder="e.g. London, United Kingdom" id="cityCountry">
                </div>
                <div class="form-group">
                    <label>Dietary Requirements</label>
                    <input type="text" name="dietary" placeholder="Vegetarian, Halal, None…">
                </div>
            </div>

            <div class="form-group full">
                <label>Message to the Couple</label>
                <textarea name="message" rows="3" placeholder="Share your wishes or a special note…"></textarea>
            </div>

            <!-- Time Capsule -->
            <?php if ($s['show_time_capsule'] === '1'): ?>
            <div class="time-capsule-section">
                <div class="tc-header">
                    <i class="fas fa-lock"></i>
                    <div>
                        <strong>Time Capsule Wish</strong>
                        <small>Your message will be sealed and revealed to the couple on their wedding day (<?= formatDateShort($s['time_capsule_unlock'] ?? $eventDate) ?>)</small>
                    </div>
                </div>
                <div class="form-group full">
                    <label>Your Sealed Message *</label>
                    <textarea name="capsule_message" rows="3" placeholder="Write a heartfelt message that will be revealed on the wedding day…" required></textarea>
                </div>
                <div class="form-group full">
                    <label>Attach a Photo (optional)</label>
                    <div class="file-drop" id="capsuleFileDrop">
                        <i class="fas fa-image"></i>
                        <span>Click or drag a photo here</span>
                        <input type="file" name="capsule_photo" accept="image/*" id="capsulePhotoInput">
                    </div>
                    <div id="capsulePhotoPreview" class="file-preview" style="display:none"></div>
                </div>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn-submit">
                <span class="btn-text">Send My RSVP</span>
                <span class="btn-loading" style="display:none"><i class="fas fa-spinner fa-spin"></i> Sending…</span>
                <i class="fas fa-paper-plane btn-icon"></i>
            </button>
        </form>
    </div>

    <!-- Success state -->
    <div id="rsvpSuccess" class="rsvp-success" style="display:none">
        <div class="success-animation">
            <canvas id="confettiCanvas"></canvas>
            <div class="success-icon"><i class="fas fa-heart"></i></div>
        </div>
        <h3>Thank You!</h3>
        <p id="successMessage">Your RSVP has been received. We can't wait to celebrate with you!</p>
        <div id="qrSection" style="display:none" class="qr-section">
            <p>Your personal QR code for the event:</p>
            <div id="qrCode" class="qr-code-wrap"></div>
            <a id="guestPortalLink" href="#" class="btn-portal">View My Invitation Portal <i class="fas fa-arrow-right"></i></a>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════════════
     GUEST GARDEN (Unique Feature)
══════════════════════════════════════════════ -->
<?php if ($s['show_guest_garden'] === '1' && $attending > 0): ?>
<section class="garden-section section" id="garden">
    <div class="section-ornament"></div>
    <h2 class="section-title reveal-up">Our Guest Garden</h2>
    <p class="section-subtitle reveal-up"><?= $attending ?> blooms have joined — each flower represents a confirmed guest</p>
    <div class="garden-wrap reveal-up" id="guestGarden" data-count="<?= $attending ?>"></div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     GUEST JOURNEY MAP (Unique Feature)
══════════════════════════════════════════════ -->
<?php if ($s['show_map'] === '1' && $total > 0): ?>
<section class="map-section section" id="map">
    <div class="section-ornament"></div>
    <h2 class="section-title reveal-up">Guests Are Travelling From…</h2>
    <p class="section-subtitle reveal-up">Our loved ones are joining us from around the world</p>
    <div id="guestMap" class="guest-map reveal-up"></div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════════════
     FOOTER
══════════════════════════════════════════════ -->
<footer class="invitation-footer">
    <div class="footer-ornament">
        <span></span><i class="fas fa-heart"></i><span></span>
    </div>
    <p class="footer-names"><?= e($s['couple_name_1']) ?> &amp; <?= e($s['couple_name_2']) ?></p>
    <p class="footer-date"><?= formatDate($eventDate) ?></p>
    <p class="footer-venue"><?= e($s['venue_name']) ?> · <?= e($s['venue_address']) ?></p>
    <a href="<?= BASE_URL ?>/admin/" class="admin-link"><i class="fas fa-lock"></i></a>
</footer>

<!-- ══════════════════════════════════════════════
     SCRIPTS
══════════════════════════════════════════════ -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<?php if ($s['show_map'] === '1' && $total > 0): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<?php endif; ?>
<script>
const BASE_URL = '<?= BASE_URL ?>';
const EVENT_DATE = '<?= $eventDate ?>';
const SHOW_MAP = <?= $s['show_map'] === '1' ? 'true' : 'false' ?>;
</script>
<script src="<?= BASE_URL ?>/assets/js/invitation.js"></script>
</body>
</html>
