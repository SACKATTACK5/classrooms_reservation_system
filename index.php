<?php

session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
$name = htmlspecialchars($_SESSION["name"] ?? "");
$role = htmlspecialchars($_SESSION["role"] ?? "");

?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الرئيسية</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>

    <div class="top-bar">
        <div>
            مرحبًا: <?php echo $name; ?>
            |
            الدور: <?php echo $role; ?>
        </div>

        <a href="logout.php" class="logout-btn">تسجيل الخروج</a>
    </div>

    <div class="page-content">
    <h2>الصفحة الرئيسية</h2>

    <?php if ($_SESSION["role"] === "lecturer"): ?>

        <p>
            يمكنك تقديم طلب حجز قاعة من الرابط التالي:
        </p>

        <p>
            <a href="booking.php">الانتقال إلى صفحة تقديم طلب حجز قاعة</a>
        </p>

<?php elseif ($_SESSION["role"] === "admin"): ?>

    <p>
        مرحبًا بك أيها المدير.
    </p>

    <p>
        يمكنك الانتقال إلى:
    </p>

    <ul>
        <li>
            <a href="admin_bookings.php">إدارة طلبات الحجز</a>
        </li>

        <li>
            <a href="admin_users.php">إدارة المستخدمين</a>
        </li>

        <li>
            <a href="admin_rooms.php">إدارة القاعات</a>
        </li>
    </ul>

<?php endif; ?>
</div>

</body>

</html>