<?php
// app/Core/Request.php
namespace Core;

class Request
{
    public static function input(string $key, $default = null)
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'POST') {
            return $_POST[$key] ?? $default;
        }
        return $_GET[$key] ?? $default;
    }

    public static function all(): array
    {
        return array_merge($_GET ?? [], $_POST ?? []);
    }

    public static function json(): ?array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        return is_array($data) ? $data : null;
    }
}
