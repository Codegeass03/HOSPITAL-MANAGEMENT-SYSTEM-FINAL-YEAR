<?php
// app/Controllers/DashboardController.php
namespace App\Controllers;

use Core\Session;
use Core\Response;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $user = Session::get('user');
        if (!$user) {
            Response::redirect('/login');
        }

        // Very simple HTML output for testing login
        echo "<!doctype html><html><head><meta charset='utf-8'><title>Dashboard</title>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
        </head><body class='p-4'>
        <div class='container'>
          <h2>Welcome, " . htmlspecialchars($user['first_name'] ?: $user['username']) . "</h2>
          <p>Role ID: " . (int)$user['role_id'] . "</p>
          <p><a href='/logout' class='btn btn-sm btn-danger'>Logout</a></p>
        </div></body></html>";
    }
}
