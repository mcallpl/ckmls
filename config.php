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
// Cache the short-lived Trestle token in the system temp dir (kept OUT of the
// web root so it can never be downloaded over HTTP). The filename is namespaced
// per install: /tmp is world-writable but sticky, so PHP cannot overwrite a
// trestle_token.json left there by a different OS user (a stale root-owned file
// is what caused the "Permission denied" errors) — a unique name is always
// created and owned by the PHP user, so writes always succeed.
if (!defined('TOKEN_CACHE_FILE')) {
    define('TOKEN_CACHE_FILE',
        sys_get_temp_dir() . '/trestle_token_' . substr(md5(__DIR__), 0, 8) . '.json');
}

// Google Maps (JavaScript API + Geocoding API)
if (!defined('GOOGLE_MAPS_API_KEY')) define('GOOGLE_MAPS_API_KEY', 'YOUR_GOOGLE_MAPS_API_KEY');

// ATTOM Data (public records / owner info) — optional
// Get a free key at: https://api.developer.attomdata.com/
// Leave empty string to skip ATTOM lookups
if (!defined('ATTOM_API_KEY')) define('ATTOM_API_KEY', '');

// ── OpenAI (CMA email AI descriptions) ─────────────────────
// Get your key at: https://platform.openai.com/api-keys
if (!defined('OPENAI_API_KEY')) define('OPENAI_API_KEY', '');

// ── SendGrid (CMA email delivery) ──────────────────────────
// Real key comes from the vault via config.local.php.
if (!defined('SENDGRID_API_KEY')) define('SENDGRID_API_KEY', '');
// Every CMA is silently BCC-copied to the agent for their records.
if (!defined('AGENT_COPY_EMAIL')) define('AGENT_COPY_EMAIL', 'mcallpl@gmail.com');

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

// ============================================================
//  AUTHENTICATION (password + Touch ID / Face ID passkeys)
//  Ported from the pws/MQI Records implementation. Gates the app
//  and the paid API endpoints (search.php, cma.php). p.php and
//  photo.php stay public — they serve CMA email recipients.
// ============================================================

// Admin password: from the vault ($vault_ckmls_app_password), falling back to
// the shared pws password, then a hardcoded default. Only used to seed
// sec/credentials.json on first run; after that, the stored hash is the source.
if (!defined('APP_PASSWORD')) {
    $ckmls_app_pw = $vault_ckmls_app_password
        ?? ($vault_pws_app_password ?? (getenv('APP_PASSWORD') ?: 'amazing'));
    define('APP_PASSWORD', $ckmls_app_pw);
}

define('SESSION_TIMEOUT', 300); // 5 minutes of inactivity
if (!defined('CREDENTIALS_FILE')) {
    define('CREDENTIALS_FILE', __DIR__ . '/sec/credentials.json');
}

// Session setup (safe on public endpoints too — starts before any body output)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Authentication check with idle timeout
if (!function_exists('isAuthenticated')) {
    function isAuthenticated() {
        if (empty($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
            return false;
        }
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return true;
    }
}

// Base64url encode/decode for WebAuthn
if (!function_exists('base64url_encode')) {
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
if (!function_exists('base64url_decode')) {
    function base64url_decode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4));
    }
}

// Credential storage (single-admin: password hash + registered passkeys)
if (!function_exists('loadCredentials')) {
    function loadCredentials() {
        if (!file_exists(CREDENTIALS_FILE)) {
            $default = [
                'credentials' => [],
                'password_hash' => password_hash(APP_PASSWORD, PASSWORD_DEFAULT),
            ];
            saveCredentials($default);
            return $default;
        }
        return json_decode(file_get_contents(CREDENTIALS_FILE), true);
    }
}
if (!function_exists('saveCredentials')) {
    // Returns true on success, false if the store could not be written (e.g. the
    // sec/ dir isn't writable by the PHP-FPM user) so callers can surface a real
    // error instead of a silent, fake "success".
    function saveCredentials($data) {
        $dir = dirname(CREDENTIALS_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return @file_put_contents(CREDENTIALS_FILE, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }
}
