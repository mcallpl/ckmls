<?php
// ============================================================
//  AUTH API — password + Touch ID / Face ID passkey endpoints
//  (CRUD lives in the app's own endpoints: search.php, cma.php, p.php)
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/webauthn.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Actions that don't require an existing session
$publicActions = ['login', 'check_setup', 'webauthn_login_options', 'webauthn_login'];

if (!in_array($action, $publicActions, true) && !isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

switch ($action) {

    case 'check_setup':
        $creds = loadCredentials();
        echo json_encode(['hasPasskeys' => count($creds['credentials']) > 0]);
        break;

    case 'login':
        $input = json_decode(file_get_contents('php://input'), true);
        $password = $input['password'] ?? '';
        $creds = loadCredentials();
        if (password_verify($password, $creds['password_hash'])) {
            session_regenerate_id(true); // prevent session fixation
            $_SESSION['authenticated'] = true;
            $_SESSION['last_activity'] = time();
            echo json_encode(['success' => true]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid password']);
        }
        break;

    case 'webauthn_register_options':
        echo json_encode(webauthn_register_options());
        break;

    case 'webauthn_register':
        $input = json_decode(file_get_contents('php://input'), true);
        $result = webauthn_register_verify($input);
        if (isset($result['error'])) http_response_code(400);
        echo json_encode($result);
        break;

    case 'webauthn_login_options':
        echo json_encode(webauthn_login_options());
        break;

    case 'webauthn_login':
        $input = json_decode(file_get_contents('php://input'), true);
        $result = webauthn_login_verify($input);
        if (isset($result['error'])) http_response_code(401);
        echo json_encode($result);
        break;

    case 'logout':
        session_unset();
        session_destroy();
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
