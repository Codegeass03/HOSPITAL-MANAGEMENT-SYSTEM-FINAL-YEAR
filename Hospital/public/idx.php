<?php
declare(strict_types=1);

// Basic autoload for app classes (no composer)
spl_autoload_register(function (string $class) {
    $base = __DIR__ . '/../app/';
    $file = $base . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Start secure session
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    // 'cookie_secure' => true, // enable when HTTPS
]);

// Load DB
$pdo = require __DIR__ . '/../config/database.php';

// Simple dependency container
$container = [
    'pdo' => $pdo,
];

// Create Router and dispatch
$router = new Core\Router($container);

// Define routes (simple)
// Public
$router->get('/', 'AuthController@showLogin');
$router->get('/login', 'AuthController@showLogin');
$router->post('/auth/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');

// Dashboard (protected example)
$router->get('/dashboard', 'DashboardController@index', ['auth']); // requires login

$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);

$router->get('/admin/users', 'UsersController@index', ['auth','permission:users.manage']);
$router->post('/admin/users/store', 'UsersController@store', ['auth','permission:users.manage']);
$router->post('/admin/users/delete', 'UsersController@delete', ['auth','permission:users.manage']);
