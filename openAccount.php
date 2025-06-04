<?php
// Настройки сессии ДО session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');

session_start();

// Настройки CORS
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: http://localhost");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Проверка аутентификации
if (!isset($_SESSION['user']) || !$_SESSION['user']['authenticated']) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Необходима авторизация']);
    exit;
}

$user_id = $_SESSION['user']['id'];

// Конфигурация базы данных
$config = [
    'host' => 'localhost',
    'dbname' => 'bank_website',
    'user' => 'root',
    'pass' => '12345678'
];

try {
    $pdo = new PDO(
        "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
        $config['user'],
        $config['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    error_log("DB connection error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Ошибка подключения к базе данных']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Неверный формат данных']);
        exit;
    }
    
    $requiredFields = ['currency', 'accountType'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Поле $field обязательно для заполнения"]);
            exit;
        }
    }
    
    $allowedCurrencies = ['RUB', 'USD', 'EUR'];
    if (!in_array($input['currency'], $allowedCurrencies)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Указана недопустимая валюта']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
            INSERT INTO BankAccount (user_id, balance, currency, account_status) 
            VALUES (?, 0.00, ?, ?)
        ");
        
        $stmt->execute([
            $user_id,
            $input['currency'],
            $input['accountType']
        ]);
        
        $accountId = $pdo->lastInsertId();
        
        $accountNumber = generateAccountNumber($input['currency']);
        
        $pdo->commit();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Счет успешно создан',
            'accountId' => $accountId,
            'accountNumber' => $accountNumber,
            'currency' => $input['currency'],
            'accountType' => $input['accountType']
        ]);
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Account creation error: " . $e->getMessage());
        
        $message = 'Ошибка при создании счета';
        if (strpos($e->getMessage(), 'Для создания счета пользователь должен быть старше 18 лет') !== false) {
            $message = 'Для создания счета вам должно быть 18 лет или больше';
        } elseif (strpos($e->getMessage(), 'Пользователь может иметь не более 5 счетов') !== false) {
            $message = 'Вы достигли максимального количества счетов (5)';
        }
        
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $message]);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Метод не поддерживается']);
}

function generateAccountNumber($currency) {
    $prefix = '';
    switch ($currency) {
        case 'RUB': $prefix = '810'; break;
        case 'USD': $prefix = '840'; break;
        case 'EUR': $prefix = '978'; break;
        default: $prefix = '000';
    }
    
    $randomPart = mt_rand(1000000000, 9999999999);
    return $prefix . $randomPart;
}