<?php
require_once __DIR__ . '/config.php';
if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ckMLS — Sign In</title>
    <!-- premium link + icon assets (relative paths: work at both pws.peoplestar.com/ and peoplestar.com/pws/) -->
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16.png">
    <link rel="apple-touch-icon" href="apple-touch-icon.png">
    <meta name="theme-color" content="#0f172a">
    <meta property="og:type" content="website">
    <meta property="og:title" content="ckMLS">
    <meta property="og:description" content="Instant MLS property search that just works.">
    <meta property="og:image" content="https://ckmls.peoplestar.com/public/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="ckMLS">
    <meta name="twitter:description" content="Instant MLS property search that just works.">
    <meta name="twitter:image" content="https://ckmls.peoplestar.com/public/og-image.png">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            overflow-x: hidden;
            width: 100%;
            max-width: 100vw;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-x: none;
            touch-action: pan-y;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(99, 102, 241, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(139, 92, 246, 0.1) 0%, transparent 50%),
                radial-gradient(ellipse at 50% 80%, rgba(59, 130, 246, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
        }
        @media (max-width: 480px) {
            .login-card { padding: 36px 24px; border-radius: 20px; }
        }

        .logo {
            width: 72px; height: 72px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 20px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 8px 32px rgba(99, 102, 241, 0.3);
        }
        .logo svg { width: 36px; height: 36px; color: #fff; }

        h1 {
            text-align: center;
            font-size: 26px;
            font-weight: 700;
            color: #f1f5f9;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        .subtitle {
            text-align: center;
            font-size: 14px;
            color: #94a3b8;
            margin-bottom: 36px;
        }

        .screen { display: none; }
        .screen.active { display: block; }

        label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 6px;
            letter-spacing: 0.3px;
        }
        input[type="password"] {
            width: 100%;
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: #f1f5f9;
            font-size: 16px;
            outline: none;
            transition: all 0.2s;
        }
        input[type="password"]:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        input[type="password"]::placeholder { color: #475569; }

        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            padding: 14px 20px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn:active { transform: scale(0.98); }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #fff;
            box-shadow: 0 4px 16px rgba(99, 102, 241, 0.3);
        }
        .btn-primary:hover { box-shadow: 0 6px 24px rgba(99, 102, 241, 0.4); }
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-passkey {
            background: linear-gradient(135deg, #059669, #10b981);
            color: #fff;
            box-shadow: 0 4px 16px rgba(5, 150, 105, 0.3);
            padding: 18px 20px;
            font-size: 16px;
        }
        .btn-passkey:hover { box-shadow: 0 6px 24px rgba(5, 150, 105, 0.4); }

        .btn-ghost {
            background: transparent;
            color: #94a3b8;
            font-size: 14px;
            padding: 12px;
        }
        .btn-ghost:hover { color: #e2e8f0; background: rgba(255,255,255,0.05); }

        .divider {
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 24px 0;
            color: #475569;
            font-size: 13px;
        }
        .divider::before, .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.08);
        }

        .form-group { margin-bottom: 20px; }

        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
            margin-bottom: 20px;
            display: none;
        }
        .error-msg.show { display: block; }

        .success-msg {
            text-align: center;
            padding: 32px 20px;
        }
        .success-msg .checkmark {
            width: 64px; height: 64px;
            background: rgba(16, 185, 129, 0.15);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .success-msg .checkmark svg { width: 32px; height: 32px; color: #10b981; }
        .success-msg h2 { color: #f1f5f9; font-size: 20px; margin-bottom: 8px; }
        .success-msg p { color: #94a3b8; font-size: 14px; margin-bottom: 24px; }

        .passkey-icon { width: 28px; height: 28px; }

        .setup-prompt { text-align: center; }
        .setup-prompt .icon-wrap {
            width: 80px; height: 80px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
        }
        .setup-prompt .icon-wrap svg { width: 40px; height: 40px; color: #10b981; }
        .setup-prompt h2 { color: #f1f5f9; font-size: 20px; margin-bottom: 8px; }
        .setup-prompt p { color: #94a3b8; font-size: 14px; margin-bottom: 28px; line-height: 1.5; }

        .spinner {
            display: inline-block;
            width: 20px; height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

        .footer-text {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #475569;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="logo">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
        </svg>
    </div>
    <h1>ckMLS</h1>
    <p class="subtitle">MLS Property Search</p>

    <div id="error" class="error-msg"></div>

    <!-- SCREEN: Passkey Login -->
    <div id="screen-passkey" class="screen">
        <button class="btn btn-passkey" onclick="loginWithPasskey()">
            <svg class="passkey-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0119.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 004.5 10.5a7.464 7.464 0 01-1.15 3.993m1.989 3.559A11.209 11.209 0 008.25 10.5a3.75 3.75 0 117.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 01-3.6 9.75m6.633-4.596a18.666 18.666 0 01-2.485 5.33"/>
            </svg>
            Sign in with Touch ID / Face ID
        </button>
        <div class="divider">or</div>
        <button class="btn btn-ghost" onclick="showScreen('password')">Use password instead</button>
    </div>

    <!-- SCREEN: Password Login -->
    <div id="screen-password" class="screen">
        <form onsubmit="loginWithPassword(event)">
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" placeholder="Enter your password" autocomplete="current-password" required>
            </div>
            <button type="submit" class="btn btn-primary" id="login-btn">Sign In</button>
        </form>
        <div id="passkey-alt" style="display:none">
            <div class="divider">or</div>
            <button class="btn btn-ghost" onclick="showScreen('passkey')">
                <svg style="width:18px;height:18px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0119.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 004.5 10.5a7.464 7.464 0 01-1.15 3.993m1.989 3.559A11.209 11.209 0 008.25 10.5a3.75 3.75 0 117.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 01-3.6 9.75m6.633-4.596a18.666 18.666 0 01-2.485 5.33"/></svg>
                Use Touch ID / Face ID
            </button>
        </div>
    </div>

    <!-- SCREEN: Setup Passkey -->
    <div id="screen-setup" class="screen">
        <div class="setup-prompt">
            <div class="icon-wrap">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0119.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 004.5 10.5a7.464 7.464 0 01-1.15 3.993m1.989 3.559A11.209 11.209 0 008.25 10.5a3.75 3.75 0 117.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 01-3.6 9.75m6.633-4.596a18.666 18.666 0 01-2.485 5.33"/>
                </svg>
            </div>
            <h2>Enable Biometric Login</h2>
            <p>Use Touch ID on your Mac or Face ID on your iPhone for faster, more secure sign-ins.</p>
            <button class="btn btn-passkey" onclick="registerPasskey()" id="setup-btn">
                <svg class="passkey-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                Set Up Now
            </button>
            <button class="btn btn-ghost" onclick="window.location.href='index.php'" style="margin-top:8px">Skip for now</button>
        </div>
    </div>

    <!-- SCREEN: Loading -->
    <div id="screen-loading" class="screen">
        <div class="success-msg">
            <div class="checkmark"><span class="spinner"></span></div>
            <h2>Signing you in...</h2>
        </div>
    </div>

    <!-- SCREEN: Setup Complete -->
    <div id="screen-done" class="screen">
        <div class="success-msg">
            <div class="checkmark">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            </div>
            <h2>All Set!</h2>
            <p>Biometric login is now enabled. You can use Touch ID or Face ID next time.</p>
            <button class="btn btn-primary" onclick="window.location.href='index.php'">Continue to App</button>
        </div>
    </div>

    <p class="footer-text">Secure &middot; Private &middot; Encrypted</p>
</div>

<script>
const webAuthnAvailable = (window.PublicKeyCredential !== undefined);
let hasPasskeys = false;

function showScreen(name) {
    document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
    const el = document.getElementById('screen-' + name);
    if (el) el.classList.add('active');
    // Land on the screen ready to type/speak: focus its first text input.
    const input = el && el.querySelector('input[type="password"], input[type="text"]');
    if (input) {
        // rAF so focus lands after the screen becomes visible (needed on iOS Safari).
        requestAnimationFrame(() => { try { input.focus(); } catch(e) {} });
    }
}

function showError(msg) {
    const el = document.getElementById('error');
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 5000);
}

function base64urlToBuffer(base64url) {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const pad = '='.repeat((4 - base64.length % 4) % 4);
    const binary = atob(base64 + pad);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
    return bytes.buffer;
}

function bufferToBase64url(buffer) {
    const bytes = new Uint8Array(buffer);
    let str = '';
    bytes.forEach(b => str += String.fromCharCode(b));
    return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
}

async function init() {
    try {
        const res = await fetch('api.php?action=check_setup');
        const data = await res.json();
        hasPasskeys = data.hasPasskeys;
    } catch(e) {
        hasPasskeys = false;
    }

    if (webAuthnAvailable && hasPasskeys) {
        showScreen('passkey');
        document.getElementById('passkey-alt').style.display = 'block';
    } else {
        showScreen('password');
    }
}

async function loginWithPassword(e) {
    e.preventDefault();
    const btn = document.getElementById('login-btn');
    const pw = document.getElementById('password').value;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Signing in...';

    try {
        const res = await fetch('api.php?action=login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: pw })
        });
        const data = await res.json();

        if (data.success) {
            // Offer Face ID setup whenever the browser supports it — not only on the
            // first ever login. This lets you (re)register this device even when an old
            // or wrong-scoped passkey already exists, so you can never get locked out of
            // biometric login. The setup screen has a "Skip for now" button.
            if (webAuthnAvailable) {
                showScreen('setup');
            } else {
                window.location.href = 'index.php';
            }
        } else {
            showError(data.error || 'Invalid password');
            btn.disabled = false;
            btn.textContent = 'Sign In';
        }
    } catch(err) {
        showError('Connection error. Please try again.');
        btn.disabled = false;
        btn.textContent = 'Sign In';
    }
}

async function loginWithPasskey() {
    try {
        showScreen('loading');
        const optRes = await fetch('api.php?action=webauthn_login_options');
        const options = await optRes.json();

        options.challenge = base64urlToBuffer(options.challenge);
        options.allowCredentials = (options.allowCredentials || []).map(c => ({
            ...c, id: base64urlToBuffer(c.id)
        }));

        const credential = await navigator.credentials.get({ publicKey: options });
        const body = {
            id: credential.id,
            rawId: bufferToBase64url(credential.rawId),
            type: credential.type,
            response: {
                authenticatorData: bufferToBase64url(credential.response.authenticatorData),
                clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
                signature: bufferToBase64url(credential.response.signature)
            }
        };

        const verifyRes = await fetch('api.php?action=webauthn_login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const result = await verifyRes.json();

        if (result.success) {
            window.location.href = 'index.php';
        } else {
            showError(result.error || 'Authentication failed');
            showScreen('passkey');
        }
    } catch(err) {
        if (err.name === 'NotAllowedError') {
            showError('Authentication cancelled or timed out.');
        } else if (err.name === 'SecurityError') {
            showError('Face ID needs a secure (HTTPS) connection on this domain.');
        } else {
            showError('Biometric sign-in failed: ' + (err.message || err.name || 'unknown error'));
        }
        showScreen('passkey');
    }
}

async function registerPasskey() {
    const btn = document.getElementById('setup-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Setting up...';

    try {
        const optRes = await fetch('api.php?action=webauthn_register_options');
        const options = await optRes.json();

        options.challenge = base64urlToBuffer(options.challenge);
        options.user.id = base64urlToBuffer(options.user.id);

        const credential = await navigator.credentials.create({ publicKey: options });
        const body = {
            id: credential.id,
            rawId: bufferToBase64url(credential.rawId),
            type: credential.type,
            response: {
                attestationObject: bufferToBase64url(credential.response.attestationObject),
                clientDataJSON: bufferToBase64url(credential.response.clientDataJSON)
            }
        };

        const verifyRes = await fetch('api.php?action=webauthn_register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        });
        const result = await verifyRes.json();

        if (result.success) {
            showScreen('done');
        } else {
            showError(result.error || 'Setup failed');
            btn.disabled = false;
            btn.innerHTML = 'Set Up Now';
        }
    } catch(err) {
        if (err.name === 'NotAllowedError') {
            showError('Setup cancelled or timed out.');
        } else if (err.name === 'SecurityError') {
            showError('Face ID setup needs a secure (HTTPS) connection on this domain.');
        } else if (err.name === 'InvalidStateError') {
            showError('A passkey is already registered on this device.');
        } else {
            showError('Could not set up Face ID: ' + (err.message || err.name || 'unknown error'));
        }
        btn.disabled = false;
        btn.innerHTML = 'Set Up Now';
    }
}

init();
</script>
</body>
</html>
