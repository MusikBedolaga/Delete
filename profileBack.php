<?php
session_start();
error_log("profileBack.php called via ".$_SERVER['REQUEST_METHOD']);
ini_set('display_errors', 1);
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
// Добавляем POST в разрешенные методы
header("Access-Control-Allow-Methods: GET, POST, OPTIONS"); 
header("Access-Control-Allow-Headers: Content-Type");

// Проверка авторизации
if (!isset($_SESSION['user']) || !$_SESSION['user']['authenticated']) {
    echo json_encode(["status" => "error", "message" => "Требуется авторизация"]);
    exit;
}

// Конфигурация базы данных
$host = "localhost";
$dbname = "bank_website";
$user = "root";
$pass = "12345678";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["status" => "error", "message" => "Ошибка подключения к БД"]);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    // Существующий GET-код без изменений
    $userId = $_SESSION['user']['id'];

    try {
        // Получаем данные пользователя
        $stmt = $pdo->prepare("SELECT full_name, email FROM User WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            echo json_encode(["status" => "error", "message" => "Пользователь не найден"]);
            exit;
        }

        // Получаем счета пользователя
        $accountsStmt = $pdo->prepare("SELECT id, balance, currency FROM BankAccount WHERE user_id = ?");
        $accountsStmt->execute([$userId]);
        $accounts = $accountsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Получаем транзакции
        $transactions = [];
        if (!empty($accounts)) {
            $accountIds = array_column($accounts, 'id');
            $placeholders = implode(',', array_fill(0, count($accountIds), '?'));
            
            $transactionsStmt = $pdo->prepare("
                SELECT t.*, b.currency 
                FROM Transaction t
                JOIN BankAccount b ON t.bank_account_id = b.id
                WHERE t.bank_account_id IN ($placeholders)
                ORDER BY t.transaction_date DESC
                LIMIT 20
            ");
            $transactionsStmt->execute($accountIds);
            $transactions = $transactionsStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            "status" => "success",
            "user" => $user,
            "accounts" => $accounts,
            "transactions" => $transactions
        ]);

    } catch (Exception $e) {
        error_log("Profile error: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Ошибка получения данных"]);
    }
} 
// Добавляем новый блок для обработки POST-запросов
elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userId = $_SESSION['user']['id'];
    
    // Получаем JSON данные из тела запроса
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Проверяем наличие обязательных полей
    if (!isset($data['full_name']) || !isset($data['email'])) {
        echo json_encode(["status" => "error", "message" => "Не указаны все обязательные поля"]);
        exit;
    }
    
    $fullName = trim($data['full_name']);
    $email = trim($data['email']);
    
    // Валидация данных
    if (empty($fullName) || empty($email)) {
        echo json_encode(["status" => "error", "message" => "Поля не могут быть пустыми"]);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => "error", "message" => "Некорректный email"]);
        exit;
    }
    
    try {
        // Проверяем, не занят ли email другим пользователем
        $checkStmt = $pdo->prepare("SELECT id FROM User WHERE email = ? AND id != ?");
        $checkStmt->execute([$email, $userId]);
        
        if ($checkStmt->fetch()) {
            echo json_encode(["status" => "error", "message" => "Этот email уже используется"]);
            exit;
        }
        
        // Обновляем данные пользователя
        $updateStmt = $pdo->prepare("UPDATE User SET full_name = ?, email = ? WHERE id = ?");
        $updateStmt->execute([$fullName, $email, $userId]);
        
        // Обновляем данные в сессии
        $_SESSION['user']['full_name'] = $fullName;
        $_SESSION['user']['email'] = $email;
        
        echo json_encode([
            "status" => "success",
            "message" => "Данные успешно обновлены",
            "user" => [
                "full_name" => $fullName,
                "email" => $email
            ]
        ]);
    } catch (Exception $e) {
        error_log("Update error: " . $e->getMessage());
        echo json_encode(["status" => "error", "message" => "Ошибка при обновлении данных"]);
    }
}
else {
    echo json_encode(["status" => "error", "message" => "Метод не поддерживается"]);
}
?>