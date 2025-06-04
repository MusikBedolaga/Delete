<?php
session_start();
error_log("signInBack.php called via ".$_SERVER['REQUEST_METHOD']);
ini_set('display_errors', 1);
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Конфигурация базы данных
$host = "localhost";
$dbname = "bank_website";
$user = "root";
$pass = "12345678";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Ошибка подключения к БД"]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Получаем JSON данные
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if ($data === null) {
        echo json_encode(["status" => "error", "message" => "Неверный формат данных"]);
        exit;
    }

    $email = trim($data['email'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        echo json_encode(["status" => "error", "message" => "Email и пароль обязательны"]);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, full_name, email, password_hash, role_id FROM User WHERE email = :email LIMIT 1");
        $stmt->execute(["email" => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            echo json_encode(["status" => "error", "message" => "Неверный email или пароль"]);
            exit;
        }

        // Устанавливаем сессию
        $_SESSION['user'] = [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role_id' => $user['role_id'],
            'authenticated' => true
        ];

        // Убедимся, что сессия записана
        session_write_close();

        echo json_encode([
            "status" => "success",
            "message" => "Авторизация успешна",
            "user" => [
                "id" => $user['id'],
                "full_name" => $user['full_name'],
                "email" => $user['email']
            ]
        ]);
    } catch (Exception $e) {
        error_log("Auth error: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Ошибка авторизации", "debug" => $e->getMessage()]);
    }

} elseif ($_SERVER["REQUEST_METHOD"] === "GET") {
    if (isset($_SESSION['user']) && $_SESSION['user']['authenticated'] === true) {
        echo json_encode([
            "status" => "success",
            "authenticated" => true,
            "user" => $_SESSION['user']
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "authenticated" => false,
            "message" => "Пользователь не авторизован"
        ]);
    }
} else {
    echo json_encode([
        "status" => "error",
        "message" => "Метод не поддерживается"
    ]);
}
?>