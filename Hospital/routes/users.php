<?php
session_start();
require_once __DIR__ . '/../controllers/UserController.php';

$controller = new UserController();

$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'index':
        $controller->index();
        break;
    case 'create':
        $controller->create();
        break;
    case 'store':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->store($_POST);
        }
        break;
    case 'edit':
        $controller->edit($_GET['id']);
        break;
    case 'update':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller->update($_GET['id'], $_POST);
        }
        break;
    case 'delete':
        $controller->delete($_GET['id']);
        break;
    default:
        $controller->index();
        break;
}
