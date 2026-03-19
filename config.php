<?php
// ============================================================
//  MLS PROPERTY SEARCH — Configuration
// ============================================================

// Load local secrets (not committed to git)
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// Trestle / Cotality
define('TRESTLE_BASE_URL',    'https://api.cotality.com');
if (!defined('TRESTLE_CLIENT_ID'))    define('TRESTLE_CLIENT_ID',    'YOUR_TRESTLE_CLIENT_ID');
if (!defined('TRESTLE_CLIENT_SECRET')) define('TRESTLE_CLIENT_SECRET', 'YOUR_TRESTLE_CLIENT_SECRET');
define('TOKEN_CACHE_FILE',    sys_get_temp_dir() . '/trestle_token.json');

// Google Maps (JavaScript API + Geocoding API)
if (!defined('GOOGLE_MAPS_API_KEY')) define('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY');

// ATTOM Data (public records / owner info) — optional
// Get a free key at: https://api.developer.attomdata.com/
// Leave empty string to skip ATTOM lookups
if (!defined('ATTOM_API_KEY')) define('ATTOM_API_KEY', '');

// ── OpenAI (CMA email AI descriptions) ─────────────────────
// Get your key at: https://platform.openai.com/api-keys
if (!defined('OPENAI_API_KEY')) define('OPENAI_API_KEY', '');

// ── Agent Profile (used in CMA emails) ──────────────────────
// Fill these in — they drive everything in the Quick CMA email.
// Nothing is hardcoded anywhere else in the app.
define('AGENT_NAME',        'Chip McAllister');           // e.g. Jane Smith
define('AGENT_TITLE',       'Broker Associate');           // e.g. Broker Associate
define('AGENT_LICENSE',     '01971252');           // e.g. 01234567
define('AGENT_EMAIL',       'Chip@chipandkim.com');           // e.g. jane@yourdomain.com
define('AGENT_PHONE',       '(949) 735-9415');           // e.g. (949) 555-1234
define('AGENT_WEBSITE',     'https://chipandkim.com');           // e.g. https://yourdomain.com
define('AGENT_PHOTO_URL',   'https://agentphoto.firstteam.com/chipmcallister9.jpg');           // Full URL to headshot image
define('AGENT_TEAM_NAME',   'Chip & Kim');           // e.g. The Smith Team (used in AI prompt & sign-off)

// Social links — leave blank to hide
define('AGENT_FACEBOOK',    'https://www.facebook.com/chip.mcallister');
define('AGENT_TWITTER',     'https://twitter.com/AmazingRaceChip');
define('AGENT_LINKEDIN',    'https://www.linkedin.com/in/chipmcallister/');
define('AGENT_YOUTUBE',     'https://www.youtube.com/channel/UCknZZilRhHjCnZLdiRhmcvA');
define('AGENT_INSTAGRAM',   'https://instagram.com/chip_mcallister');
define('AGENT_PINTEREST',   'http://pinterest.com/firstteam/');
define('AGENT_BLOG',        'http://www.firstteam.com/blog/');

// Brokerage
define('BROKERAGE_NAME',    'First Team Real Estate');           // e.g. First Team Real Estate
define('BROKERAGE_LOGO_URL','http://agentphoto.firstteam.com/sigblock/logos/ft-lpi-eSig.png');           // Full URL to brokerage logo
