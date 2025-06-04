<?php
// Настройки сессии
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

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Проверка авторизации
if (!isset($_SESSION['user']) || !$_SESSION['user']['authenticated']) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Необходима авторизация']);
    exit;
}

$user_id = $_SESSION['user']['id'];

// Конфигурация подключения к БД
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

// Обработка POST-запроса
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

        // Вставка счета — триггер проверит возраст и количество
        $stmt = $pdo->prepare("
            INSERT INTO BankAccount (user_id, balance, currency, account_status)
            VALUES (?, 0.00, ?, ?)
        ");
        $stmt->execute([$user_id, $input['currency'], $input['accountType']]);

        $accountId = $pdo->lastInsertId();
        $accountNumber = generateAccountNumber($input['currency']);

        // Проценты — только для сберегательного счета
        $interest = null;
        if ($input['accountType'] === 'savings') {
            $principal = 0;
            $annualRate = 5.0;
            $months = 12;

            $stmt = $pdo->prepare("
                SELECT calculate_interest(?, ?, ?, TRUE) AS interest
            ");
            $stmt->execute([$principal, $annualRate, $months]);
            $interest = $stmt->fetch()['interest'];

            $stmt = $pdo->prepare("
                INSERT INTO AccountInterest (account_id, interest_amount)
                VALUES (?, ?)
            ");
            $stmt->execute([$accountId, $interest]);
        }

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Счет успешно создан',
            'accountId' => $accountId,
            'accountNumber' => $accountNumber,
            'currency' => $input['currency'],
            'accountType' => $input['accountType'],
            'interest' => $interest
        ]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Метод не поддерживается']);
}

// Генератор номера счета
function generateAccountNumber($currency) {
    $prefix = match($currency) {
        'RUB' => '810',
        'USD' => '840',
        'EUR' => '978',
        default => '000',
    };
    $randomPart = mt_rand(1000000000, 9999999999);
    return $prefix . $randomPart;
}
