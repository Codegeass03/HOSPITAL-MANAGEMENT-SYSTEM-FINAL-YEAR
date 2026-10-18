<?php
// app/Controllers/BaseController.php
namespace App\Controllers;

use PDO;

class BaseController
{
    protected PDO $db;
    protected array $container;

    public function __construct(array $container)
    {
        $this->container = $container;
        $this->db = $container['pdo'];
    }

    protected function view(string $path, array $data = []): void
    {
        // simple view renderer
        extract($data, EXTR_SKIP);
        $viewFile = __DIR__ . '/../Views/' . $path . '.php';
        if (!file_exists($viewFile)) {
            echo "View not found: {$viewFile}";
            return;
        }
        require __DIR__ . '/../Views/layouts/auth.php'; // base layout (login uses auth layout)
    }

    protected function renderPartial(string $path, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = __DIR__ . '/../Views/' . $path . '.php';
        require $viewFile;
    }
}
