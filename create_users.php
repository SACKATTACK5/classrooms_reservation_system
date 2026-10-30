<?php

require_once __DIR__ . "/includes/conn.php";

echo '<meta charset="UTF-8">';
echo "<h2>starting with fake data</h2>";

$users = [
    [
        "name" => "Admin",
        "email" => "admin@edu.com",
        "password" => "Admin1234",
        "role" => "admin"
    ],
    [
        "name" => "Lecturer",
        "email" => "lecturer@edu.com",
        "password" => "Lecturer1234",
        "role" => "lecturer"
    ]
];

foreach ($users as $user) {
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$user["email"]]);

    if ($stmt->fetch()) {
        echo "المستخدم موجود مسبقًا: " . htmlspecialchars($user["email"]) . "<br>";
        continue;
    }
    $hashedPassword = password_hash($user["password"], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO users (name, email, password, role)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->execute([
        $user["name"],
        $user["email"],
        $hashedPassword,
        $user["role"]
    ]);

    echo "user created" . htmlspecialchars($user["email"]) . "<br>";
}

echo "<hr>";
echo "<strong>مهمة جدًا:</strong> احذف ملف create_users.php بعد الانتهاء من إنشاء المستخدمين.";