<?php
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Response.php';
require_once __DIR__ . '/../core/Validator.php';
require_once __DIR__ . '/../core/Logger.php';
require_once __DIR__ . '/../middleware/CsrfMiddleware.php';

class AuthController
{
    public function login(Request $request): void
    {
        $email = $request->input('email');
        $password = $request->input('password');
        $v = new Validator(['email' => $email, 'password' => $password]);
        $v->required('email')->email('email')->required('password');
        $v->validate();
        if (Auth::attempt($email, $password)) {
            Auth::regenerateSession();
            session_write_close();
            session_start();
            $user = Auth::user();
            $user['_impersonating'] = false;
            $user['_impersonator'] = null;
            Logger::log('login', 'users', $user['id'], 'User logged in');
            Response::success([
                'user' => $user,
                'csrf_token' => CsrfMiddleware::generateToken(),
            ], 'Login successful');
        } else {
            Response::error('Invalid credentials', 401);
        }
    }

    public function logout(): void
    {
        $userId = Auth::id();
        Auth::logout();
        if ($userId) {
            Logger::log('logout', 'users', $userId, 'User logged out');
        }
        Response::success(null, 'Logged out');
    }

    public function me(): void
    {
        if (!Auth::check()) {
            Response::error('Unauthenticated', 401);
        }
        $user = Auth::user();
        $user['permissions'] = User::getPermissions($user['id']);
        $user['_impersonating'] = Auth::isImpersonating();
        $user['_impersonator'] = Auth::getImpersonator();
        Response::success($user);
    }

    public function register(Request $request): void
    {
        $data = $request->all();
        $v = new Validator($data);
        $v->required('name')->required('email')->email('email')->required('password')->min('password', 12)
          ->regex('password', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_])/', 'Password', 'Password must be at least 12 characters and contain uppercase, lowercase, digit, and special character')
          ->required('password_confirmation')->unique('email', 'users', 'email');
        $v->validate();
        if ($data['password'] !== $data['password_confirmation']) {
            Response::error('Passwords do not match', 422);
        }
        $userId = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => 'user',
        ]);
        Logger::log('register', 'users', $userId, 'User registered');
        GdprModel::recordConsent($userId, 'registration', '1.0', 'granted');
        Response::success(['user_id' => $userId], 'Registration successful', 201);
    }

    public function updateProfile(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::error('Unauthenticated', 401);
        }
        $data = $request->all();
        $allowed = ['name', 'phone', 'job_title', 'language', 'theme'];
        $update = array_intersect_key($data, array_flip($allowed));
        if (!empty($update)) {
            User::update($user['id'], $update);
            Auth::refreshUser();
            Logger::log('update_profile', 'users', $user['id'], 'Profile updated');
        }
        Response::success(Auth::user(), 'Profile updated');
    }

    public function updatePassword(Request $request): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::error('Unauthenticated', 401);
        }
        $current = $request->input('current_password');
        $newPassword = $request->input('new_password');
        $confirm = $request->input('new_password_confirmation');
        if (!password_verify($current, $user['password'])) {
            Response::error('Current password is incorrect', 422);
        }
        $v = new Validator(['new_password' => $newPassword, 'new_password_confirmation' => $confirm]);
        $v->required('new_password')->min('new_password', 12)->regex('new_password', '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_])/', 'Password', 'Password must be at least 12 characters and contain uppercase, lowercase, digit, and special character')
          ->required('new_password_confirmation');
        $v->validate();
        if ($newPassword !== $confirm) {
            Response::error('Passwords do not match', 422);
        }
        User::update($user['id'], ['password' => $newPassword]);
        Auth::regenerateSession();
        Logger::log('change_password', 'users', $user['id'], 'Password changed');
        Response::success(null, 'Password updated');
    }

    public function csrfToken(): void
    {
        Response::success(['csrf_token' => CsrfMiddleware::generateToken()]);
    }

    // --- Microsoft SSO ---

    public function microsoftUrl(): void
    {
        $state = bin2hex(random_bytes(16));
        $_SESSION['microsoft_oauth_state'] = $state;

        $config = require __DIR__ . '/../config/intune.php';
        $redirectUri = $config['oauth_redirect_uri'];

        $url = 'https://login.microsoftonline.com/' . $config['tenant_id'] . '/oauth2/v2.0/authorize?' . http_build_query([
            'client_id' => $config['client_id'],
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'response_mode' => 'query',
            'scope' => 'openid profile email User.Read',
            'state' => $state,
        ]);

        Response::success(['url' => $url]);
    }

    public function microsoftCallback(Request $request): void
    {
        $code = $request->input('code');
        $state = $request->input('state');

        if (!$code || !$state) {
            Response::error('Missing code or state', 400);
        }

        $storedState = $_SESSION['microsoft_oauth_state'] ?? null;
        if (!$storedState || !hash_equals($storedState, $state)) {
            Response::error('Invalid state parameter', 403);
        }

        unset($_SESSION['microsoft_oauth_state']);

        $config = require __DIR__ . '/../config/intune.php';
        $tokenUrl = 'https://login.microsoftonline.com/' . $config['tenant_id'] . '/oauth2/v2.0/token';

        $body = http_build_query([
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'code' => $code,
            'redirect_uri' => $config['oauth_redirect_uri'],
            'grant_type' => 'authorization_code',
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $tokenUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 30,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $err = json_decode($response, true);
            Response::error('Token exchange failed: ' . ($err['error_description'] ?? $response), 502);
        }

        $tokens = json_decode($response, true);
        $idToken = $tokens['id_token'] ?? null;

        if (!$idToken) {
            Response::error('No ID token received', 502);
        }

        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            Response::error('Invalid ID token', 502);
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        if (!$payload || empty($payload['email'])) {
            Response::error('Could not extract user info from token', 502);
        }

        $email = $payload['email'];
        $name = $payload['name'] ?? $payload['preferred_username'] ?? $email;
        $sub = $payload['sub'] ?? null;

        $user = User::findByEmail($email);
        if (!$user) {
            $userId = Database::insert('users', [
                'name' => $name,
                'email' => $email,
                'password' => Auth::hashPassword(bin2hex(random_bytes(16))),
                'role' => 'user',
                'microsoft_sub' => $sub,
                'email_verified_at' => date('Y-m-d H:i:s'),
            ]);
            $user = User::find($userId);
            if (!$user) {
                Response::error('Failed to create user', 500);
            }
        }

        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];

        Database::update('users', ['last_login_at' => date('Y-m-d H:i:s'), 'microsoft_sub' => $sub], 'id = ?', [$user['id']]);

        Auth::regenerateSession();
        Logger::log('login', 'users', $user['id'], 'User logged in via Microsoft SSO');

        Response::success([
            'user' => $user,
            'csrf_token' => CsrfMiddleware::generateToken(),
        ], 'Microsoft SSO login successful');
    }
}
