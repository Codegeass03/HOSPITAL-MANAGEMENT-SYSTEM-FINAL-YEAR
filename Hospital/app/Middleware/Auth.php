<?php
// app/Middleware/Auth.php
namespace App\Middleware;

use Core\Session;
use Core\Response;

class Auth
{
    protected array $container;

    public function __construct(array $container = [])
    {
        $this->container = $container;
    }

    public function handle()
    {
        $user = Session::get('user');
        if (!$user) {
            // if AJAX request, return JSON
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                Response::json(['success' => false, 'message' => 'Authentication required'], 401);
                return false;
            }
            Response::redirect('/login');
            return false;
        }
        // allowed
        return true;
    }
}
