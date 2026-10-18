<?php
// app/Controllers/AuthController.php
namespace App\Controllers;

use App\Models\User;
use Core\Request;
use Core\Response;
use Core\Session;

class AuthController extends BaseController
{
    protected User $userModel;

    public function __construct(array $container)
    {
        parent::__construct($container);
        $this->userModel = new User($this->db);
    }

    public function showLogin(): void
    {
        // If already logged in, redirect:
        if (Session::get('user')) {
            Response::redirect('/dashboard');
        }
        // Render login view (wrapped by layouts/auth.php)
        $this->renderPartial('auth/login', []);
    }

    public function login(): void
    {
        // Expect AJAX POST
        $login = trim(Request::input('login', ''));
        $password = Request::input('password', '');

        if (empty($login) || empty($password)) {
            return Response::json(['success' => false, 'message' => 'Please enter username/email and password'], 422);
        }

        $user = $this->userModel->findByUsernameOrEmail($login);
        if (!$user) {
            // generic message to avoid user enumeration
            return Response::json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        if ($user['status'] !== 'active') {
            return Response::json(['success' => false, 'message' => 'Account is not active. Contact admin.'], 403);
        }

        if (!$this->userModel->verifyPassword($user, $password)) {
            return Response::json(['success' => false, 'message' => 'Invalid credentials'], 401);
        }

        // Regenerate session id to prevent fixation
        Session::regenerate();

        // Store minimal user info in session (do NOT store password hash)
        Session::set('user', [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'role_id' => (int)$user['role_id'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
        ]);

        // update last_login
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $stmt->execute([':id' => $user['id']]);

        return Response::json(['success' => true, 'message' => 'Login successful', 'redirect' => '/dashboard']);
    }

    public function logout(): void
    {
        Session::destroy();
        Response::redirect('/login');
    }
}
