<?php
header('Content-Type: application/json');

// Настройки базы данных
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
    echo json_encode([
        'status' => 'error',
        'message' => 'Ошибка подключения к базе данных'
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'passport' => preg_replace('/\D/', '', $_POST['passport'] ?? ''),
        'inn' => preg_replace('/\D/', '', $_POST['inn'] ?? ''),
        'birthdate' => $_POST['birthdate'] ?? ''
    ];

    try {
        // Проверка существующего пользователя
        $stmt = $pdo->prepare("
            SELECT id FROM User 
            WHERE email = :email OR inn = :inn OR passport_number = :passport
            LIMIT 1
        ");
        $stmt->execute([
            'email' => $data['email'],
            'inn' => $data['inn'],
            'passport' => $data['passport']
        ]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Пользователь с такими данными уже существует'
            ]);
            exit;
        }

        // Получаем или создаем роль "client"
        $roleId = $pdo->query("SELECT id FROM Role WHERE role_name = 'client' LIMIT 1")
            ->fetchColumn();

        if (!$roleId) {
            $pdo->exec("INSERT INTO Role (role_name) VALUES ('client')");
            $roleId = $pdo->lastInsertId();
        }

        // Хеширование пароля
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        // Попытка вставки пользователя — валидация будет через триггер
        $stmt = $pdo->prepare("
            INSERT INTO User (
                full_name, email, password_hash, 
                passport_number, inn, birth_date, role_id
            ) VALUES (
                :full_name, :email, :password_hash,
                :passport, :inn, :birthdate, :role_id
            )
        ");

        $stmt->execute([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password_hash' => $hashedPassword,
            'passport' => $data['passport'],
            'inn' => $data['inn'],
            'birthdate' => $data['birthdate'],
            'role_id' => $roleId
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Регистрация прошла успешно'
        ]);

    } catch (PDOException $e) {
        // Обработка ошибок из триггера
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage() // сообщение из триггера попадет сюда
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Метод не поддерживается'
    ]);
}
