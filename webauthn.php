<?php
require_once __DIR__ . '/config.php';

// ── Relying Party / origin helpers ──
//
// The RP ID must be a registrable-domain suffix of the page origin. Deriving it
// straight from HTTP_HOST breaks the moment the app is reached from more than one
// host — this app is served at BOTH "pws.peoplestar.com" and "peoplestar.com/pws",
// so a host-based rpId produces two different scopes and a passkey registered under
// one is rejected under the other ("no matching passkeys").
//
// Fix: pin the rpId to the registrable domain (eTLD+1, e.g. "peoplestar.com"). It's
// a valid registrable-domain suffix of every sub-host, so ONE passkey works from the
// apex, www, pws, or any other subdomain — and stays stable forever.
//
// Assumes a single-label TLD (.com/.org/.net/…), which covers every domain in use
// here. Multi-part TLDs (.co.uk) would need the Public Suffix List.

function webauthn_rp_id() {
    $host = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]);
    // Leave localhost and bare IP addresses untouched (can't scope those to eTLD+1).
    if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
        return $host;
    }
    // Collapse any subdomain to the registrable domain: last two labels.
    $labels = explode('.', $host);
    if (count($labels) >= 2) {
        $host = $labels[count($labels) - 2] . '.' . $labels[count($labels) - 1];
    }
    return $host;
}

function webauthn_current_host() {
    return strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0]);
}

// Validate the challenge, type, RP ID hash, origin, and required flags shared by
// both registration and authentication. Returns null on success or an error string.
function webauthn_validate_common($clientDataJSON, $authData, $expectedType) {
    $clientData = json_decode($clientDataJSON, true);
    if (!is_array($clientData)) {
        return 'Malformed client data';
    }
    if (($clientData['type'] ?? '') !== $expectedType) {
        return 'Invalid response type';
    }
    if (empty($_SESSION['webauthn_challenge']) ||
        !hash_equals($_SESSION['webauthn_challenge'], (string)($clientData['challenge'] ?? ''))) {
        return 'Challenge mismatch';
    }

    // Origin must belong to our RP (apex or any subdomain of it).
    $rpId = webauthn_rp_id();
    $originHost = strtolower(parse_url($clientData['origin'] ?? '', PHP_URL_HOST) ?? '');
    if ($originHost !== $rpId && substr($originHost, -strlen('.' . $rpId)) !== '.' . $rpId
        && $originHost !== webauthn_current_host()) {
        return 'Origin not allowed';
    }

    if (strlen($authData) < 37) {
        return 'Authenticator data too short';
    }
    // RP ID hash is the first 32 bytes and is cryptographically bound — the strong check.
    $rpIdHash = substr($authData, 0, 32);
    if (!hash_equals(hash('sha256', $rpId, true), $rpIdHash)) {
        return 'RP ID hash mismatch';
    }

    $flags = ord($authData[32]);
    if (($flags & 0x01) === 0) {   // UP — user present
        return 'User not present';
    }
    if (($flags & 0x04) === 0) {   // UV — user verified (Touch ID / Face ID)
        return 'User verification failed';
    }
    return null;
}

// ── Minimal CBOR Decoder for WebAuthn ──
class CBORDecoder {
    private $data;
    private $offset;

    public function decode($data) {
        $this->data = $data;
        $this->offset = 0;
        return $this->decodeItem();
    }

    private function decodeItem() {
        if ($this->offset >= strlen($this->data)) {
            return null;
        }
        $byte = ord($this->data[$this->offset++]);
        $majorType = $byte >> 5;
        $info = $byte & 0x1f;

        switch ($majorType) {
            case 0: return $this->decodeUnsigned($info);
            case 1: return -1 - $this->decodeUnsigned($info);
            case 2:
                $len = $this->decodeUnsigned($info);
                $result = substr($this->data, $this->offset, $len);
                $this->offset += $len;
                return $result;
            case 3:
                $len = $this->decodeUnsigned($info);
                $result = substr($this->data, $this->offset, $len);
                $this->offset += $len;
                return $result;
            case 4:
                $count = $this->decodeUnsigned($info);
                $arr = [];
                for ($i = 0; $i < $count; $i++) {
                    $arr[] = $this->decodeItem();
                }
                return $arr;
            case 5:
                $count = $this->decodeUnsigned($info);
                $map = [];
                for ($i = 0; $i < $count; $i++) {
                    $key = $this->decodeItem();
                    $map[$key] = $this->decodeItem();
                }
                return $map;
            case 7:
                if ($info === 20) return false;
                if ($info === 21) return true;
                if ($info === 22) return null;
                if ($info === 25) { $this->offset += 2; return 0; }
                if ($info === 26) { $this->offset += 4; return 0; }
                if ($info === 27) { $this->offset += 8; return 0; }
                return $info;
            default: return null;
        }
    }

    private function decodeUnsigned($info) {
        if ($info < 24) return $info;
        if ($info === 24) return ord($this->data[$this->offset++]);
        if ($info === 25) {
            $v = unpack('n', substr($this->data, $this->offset, 2))[1];
            $this->offset += 2;
            return $v;
        }
        if ($info === 26) {
            $v = unpack('N', substr($this->data, $this->offset, 4))[1];
            $this->offset += 4;
            return $v;
        }
        if ($info === 27) {
            $hi = unpack('N', substr($this->data, $this->offset, 4))[1];
            $lo = unpack('N', substr($this->data, $this->offset + 4, 4))[1];
            $this->offset += 8;
            return ($hi << 32) | $lo;
        }
        return 0;
    }
}

// ── Key extraction and conversion ──

function extractCredentialFromAuthData($authData) {
    // rpIdHash(32) + flags(1) + signCount(4) = offset 37
    $offset = 37;
    // AAGUID(16)
    $offset += 16;
    // credentialIdLength(2)
    $credIdLen = unpack('n', substr($authData, $offset, 2))[1];
    $offset += 2;
    // credentialId
    $credentialId = substr($authData, $offset, $credIdLen);
    $offset += $credIdLen;
    // COSE public key (CBOR)
    $cbor = new CBORDecoder();
    $coseKey = $cbor->decode(substr($authData, $offset));

    return ['credentialId' => $credentialId, 'coseKey' => $coseKey];
}

function coseKeyToPem($coseKey) {
    $x = $coseKey[-2];
    $y = $coseKey[-3];
    // Pre-computed ASN.1 DER header for EC P-256 SubjectPublicKeyInfo
    // SEQUENCE(SEQUENCE(OID ecPublicKey, OID prime256v1), BIT STRING(uncompressed point))
    $header = hex2bin('3059301306072a8648ce3d020106082a8648ce3d03010703420004');
    $der = $header . $x . $y;
    return "-----BEGIN PUBLIC KEY-----\n" .
        chunk_split(base64_encode($der), 64, "\n") .
        "-----END PUBLIC KEY-----";
}

// ── Registration ──

function webauthn_register_options() {
    $challenge = random_bytes(32);
    $_SESSION['webauthn_challenge'] = base64url_encode($challenge);
    $rpId = webauthn_rp_id();

    return [
        'challenge' => base64url_encode($challenge),
        'rp' => ['id' => $rpId, 'name' => 'ckMLS'],
        // Distinct user handle + names: the rpId is the shared registrable domain
        // (peoplestar.com), so a unique handle keeps this passkey separate from
        // other peoplestar.com apps (e.g. pws) in the OS passkey list.
        'user' => [
            'id' => base64url_encode('ckmls-admin'),
            'name' => 'ckMLS Admin',
            'displayName' => 'ckMLS Administrator'
        ],
        'pubKeyCredParams' => [['type' => 'public-key', 'alg' => -7]],
        'authenticatorSelection' => [
            'authenticatorAttachment' => 'platform',
            'userVerification' => 'required',
            'residentKey' => 'preferred'
        ],
        'timeout' => 60000,
        'attestation' => 'none'
    ];
}

function webauthn_register_verify($data) {
    if (empty($_SESSION['webauthn_challenge'])) {
        return ['error' => 'No challenge in session'];
    }
    if (empty($data['response']['clientDataJSON']) || empty($data['response']['attestationObject'])) {
        return ['error' => 'Missing registration response'];
    }

    $clientDataJSON = base64url_decode($data['response']['clientDataJSON']);

    $attestationObject = base64url_decode($data['response']['attestationObject']);
    $cbor = new CBORDecoder();
    $attestation = $cbor->decode($attestationObject);
    if (!is_array($attestation) || empty($attestation['authData'])) {
        return ['error' => 'Malformed attestation object'];
    }
    $authData = $attestation['authData'];

    $err = webauthn_validate_common($clientDataJSON, $authData, 'webauthn.create');
    if ($err !== null) {
        return ['error' => $err];
    }

    $flags = ord($authData[32]);
    if (($flags & 0x40) === 0) {   // AT — attested credential data present
        return ['error' => 'No credential data in response'];
    }

    $credential = extractCredentialFromAuthData($authData);
    if (empty($credential['coseKey']) || !isset($credential['coseKey'][-2], $credential['coseKey'][-3])) {
        return ['error' => 'Unsupported credential key (expected ES256)'];
    }
    $pem = coseKeyToPem($credential['coseKey']);

    $newId = base64url_encode($credential['credentialId']);
    $creds = loadCredentials();
    // Drop any existing entry with the same credential id (re-registering a device
    // heals a stale entry instead of piling up duplicates). Different devices keep
    // their own entries, so multi-device passkeys still work.
    $creds['credentials'] = array_values(array_filter(
        $creds['credentials'],
        function ($c) use ($newId) { return $c['id'] !== $newId; }
    ));
    $creds['credentials'][] = [
        'id' => $newId,
        'publicKey' => $pem,
        'signCount' => 0,
        'createdAt' => date('Y-m-d H:i:s')
    ];
    if (!saveCredentials($creds)) {
        return ['error' => 'Could not save the passkey on the server (credential store not writable)'];
    }
    unset($_SESSION['webauthn_challenge']);

    return ['success' => true];
}

// ── Authentication ──

function webauthn_login_options() {
    $challenge = random_bytes(32);
    $_SESSION['webauthn_challenge'] = base64url_encode($challenge);
    $rpId = webauthn_rp_id();

    $creds = loadCredentials();
    $allow = [];
    foreach ($creds['credentials'] as $c) {
        $allow[] = ['type' => 'public-key', 'id' => $c['id'], 'transports' => ['internal']];
    }

    return [
        'challenge' => base64url_encode($challenge),
        'rpId' => $rpId,
        'allowCredentials' => $allow,
        'userVerification' => 'required',
        'timeout' => 60000
    ];
}

function webauthn_login_verify($data) {
    if (empty($_SESSION['webauthn_challenge'])) {
        return ['error' => 'No challenge in session'];
    }
    if (empty($data['id']) || empty($data['response']['clientDataJSON']) ||
        empty($data['response']['authenticatorData']) || empty($data['response']['signature'])) {
        return ['error' => 'Missing authentication response'];
    }

    $clientDataJSON = base64url_decode($data['response']['clientDataJSON']);
    $authData = base64url_decode($data['response']['authenticatorData']);

    $err = webauthn_validate_common($clientDataJSON, $authData, 'webauthn.get');
    if ($err !== null) {
        return ['error' => $err];
    }

    $credentialId = $data['id'];
    $creds = loadCredentials();
    $storedCred = null;
    $credIndex = -1;
    foreach ($creds['credentials'] as $i => $c) {
        if (hash_equals($c['id'], $credentialId)) {
            $storedCred = $c;
            $credIndex = $i;
            break;
        }
    }
    if (!$storedCred) {
        return ['error' => 'Unknown credential'];
    }

    $signature = base64url_decode($data['response']['signature']);
    $clientDataHash = hash('sha256', $clientDataJSON, true);
    $signedData = $authData . $clientDataHash;

    $pubKey = openssl_pkey_get_public($storedCred['publicKey']);
    if (!$pubKey) {
        return ['error' => 'Invalid stored public key'];
    }

    if (openssl_verify($signedData, $signature, $pubKey, OPENSSL_ALGO_SHA256) !== 1) {
        return ['error' => 'Signature verification failed'];
    }

    $newSignCount = unpack('N', substr($authData, 33, 4))[1];
    $creds['credentials'][$credIndex]['signCount'] = $newSignCount;
    saveCredentials($creds);
    unset($_SESSION['webauthn_challenge']);

    // No session_regenerate_id() here — a cookie swapped inside a fetch() response
    // is unreliably applied by iOS Safari/PWA (caused a login loop). The session id
    // is freshly issued on the login page load instead.
    $_SESSION['authenticated'] = true;
    $_SESSION['last_activity'] = time();

    return ['success' => true];
}
