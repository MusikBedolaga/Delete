<?php
session_start();
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

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
    // Получение всех отзывов
    try {
        $stmt = $pdo->query("
            SELECT r.*, u.full_name as user_name 
            FROM Review r
            LEFT JOIN User u ON r.user_id = u.id
            ORDER BY r.id DESC
        ");
        $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(["status" => "success", "reviews" => $reviews]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Ошибка получения отзывов"]);
    }
} 
elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Добавление нового отзыва
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || empty($data['review_text'])) {
        echo json_encode(["status" => "error", "message" => "Текст отзыва обязателен"]);
        exit;
    }
    
    $reviewText = trim($data['review_text']);
    $userId = isset($_SESSION['user']['id']) ? $_SESSION['user']['id'] : null;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO Review (review_text, user_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$reviewText, $userId]);
        
        $newReviewId = $pdo->lastInsertId();
        
        // Получаем добавленный отзыв
        $stmt = $pdo->prepare("
            SELECT r.*, u.full_name as user_name 
            FROM Review r
            LEFT JOIN User u ON r.user_id = u.id
            WHERE r.id = ?
        ");
        $stmt->execute([$newReviewId]);
        $newReview = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            "status" => "success", 
            "message" => "Отзыв добавлен",
            "review" => $newReview
        ]);
    } catch (Exception $e) {
        echo json_encode(["status" => "error", "message" => "Ошибка добавления отзыва"]);
    }
} 
else {
    echo json_encode(["status" => "error", "message" => "Метод не поддерживается"]);
}
?>