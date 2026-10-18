<?php
require_once __DIR__ . '/../models/User.php';

class UserController {
    private $model;

    public function __construct() {
        $this->model = new User();
    }

    public function index() {
        $users = $this->model->getAllUsers();
        include __DIR__ . '/../views/users/index.php';
    }

    public function create() {
        include __DIR__ . '/../views/users/create.php';
    }

    public function store($data) {
        $this->model->createUser($data['username'], $data['email'], $data['password'], $data['role_id']);
        header("Location: /Hospital/routes/users.php?action=index&success=created");
        exit;
    }

    public function edit($id) {
        $user = $this->model->getUserById($id);
        include __DIR__ . '/../views/users/edit.php';
    }

    public function update($id, $data) {
        $this->model->updateUser($id, $data['username'], $data['email'], $data['role_id']);
        header("Location: /Hospital/routes/users.php?action=index&success=updated");
        exit;
    }

    public function delete($id) {
        $this->model->deleteUser($id);
        header("Location: /Hospital/routes/users.php?action=index&success=deleted");
        exit;
    }

    public function resetPassword($id, $newPassword) {
        $this->model->resetPassword($id, $newPassword);
        header("Location: /Hospital/routes/users.php?action=index&success=reset");
        exit;
    }
}
