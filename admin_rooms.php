<?php

session_start();

require_once __DIR__ . "/includes/conn.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "admin") {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $room_name = trim($_POST["room_name"] ?? "");
    $capacity = filter_var($_POST["capacity"] ?? "", FILTER_VALIDATE_INT);


    if ($room_name === "" || $capacity === false || $capacity === null) {
        header("Location: admin_rooms.php?error=empty");
        exit;
    }
    if (mb_strlen($room_name, "UTF-8") > 50) {
        header("Location: admin_rooms.php?error=name_length");
        exit;
    }

    if ($capacity <= 0) {
        header("Location: admin_rooms.php?error=capacity");
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT room_id
            FROM rooms
            WHERE room_name = ?
        ");

        $stmt->execute([$room_name]);

        if ($stmt->fetch()) {
            header("Location: admin_rooms.php?error=exists");
            exit;
        }
        $stmt = $pdo->prepare("
            INSERT INTO rooms (room_name, capacity)
            VALUES (?, ?)
        ");

        $stmt->execute([
            $room_name,
            $capacity
        ]);

        header("Location: admin_rooms.php?success=added");
        exit;

    } catch (Exception $e) {
        header("Location: admin_rooms.php?error=database");
        exit;
    }
}

$success = "";
$error = "";

if (isset($_GET["success"])) {
    if ($_GET["success"] === "added") {
        $success = "تمت إضافة القاعة بنجاح.";
    }
}

if (isset($_GET["error"])) {
    if ($_GET["error"] === "empty") {
        $error = "يرجى تعبئة اسم القاعة والسعة.";
    } elseif ($_GET["error"] === "name_length") {
        $error = "اسم القاعة يجب أن لا يتجاوز 50 حرفًا.";
    } elseif ($_GET["error"] === "capacity") {
        $error = "سعة القاعة يجب أن تكون رقمًا صحيحًا أكبر من صفر.";
    } elseif ($_GET["error"] === "exists") {
        $error = "اسم القاعة مستخدم مسبقًا.";
    } elseif ($_GET["error"] === "database") {
        $error = "حدث خطأ أثناء حفظ البيانات.";
    }
}

$stmt = $pdo->query("
    SELECT room_id, room_name, capacity
    FROM rooms
    ORDER BY room_name
");

$rooms = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة القاعات</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>

    <div class="top-bar">
        <div>
            مرحبًا: <?php echo htmlspecialchars($_SESSION["name"]); ?>
            |
            الدور: <?php echo htmlspecialchars($_SESSION["role"]); ?>
        </div>

        <div>
            <a href="index.php">الرئيسية</a>
            |
            <a href="admin_bookings.php">إدارة الطلبات</a>
            |
            <a href="admin_users.php">إدارة المستخدمين</a>
            |
            <a href="logout.php" class="logout-btn">تسجيل الخروج</a>
        </div>
    </div>

    <div class="page-content">

        <h2>إدارة القاعات</h2>

        <?php if ($success !== ""): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <h3>إضافة قاعة جديدة</h3>

        <form method="POST" action="admin_rooms.php">

            <div class="form-group">
                <label for="room_name">اسم القاعة</label>
                <input
                    type="text"
                    id="room_name"
                    name="room_name"
                    placeholder="مثال: قاعة 103"
                    required
                >
            </div>

            <div class="form-group">
                <label for="capacity">سعة القاعة</label>
                <input
                    type="number"
                    id="capacity"
                    name="capacity"
                    min="10"
					max="200"
                    placeholder="مثال: 30"
                    required
                >
            </div>

            <button type="submit">إضافة القاعة</button>

        </form>

        <hr>

        <h3>قائمة القاعات</h3>

        <?php if (count($rooms) > 0): ?>

            <table>
                <thead>
                    <tr>
                        <th>رقم القاعة</th>
                        <th>اسم القاعة</th>
                        <th>السعة</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($rooms as $room): ?>
                        <tr>
                            <td><?php echo (int)$room["room_id"]; ?></td>
                            <td><?php echo htmlspecialchars($room["room_name"]); ?></td>
                            <td><?php echo (int)$room["capacity"]; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php else: ?>

            <p>لا توجد قاعات حاليًا.</p>

        <?php endif; ?>

    </div>

</body>

</html>