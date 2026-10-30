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

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $role = $_POST["role"] ?? "lecturer";


    if ($name === "" || $email === "" || $password === "") {
        header("Location: admin_users.php?error=empty");
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: admin_users.php?error=email");
        exit;
    }

        if (strlen($password) < 8) {
        header("Location: admin_users.php?error=password");
        exit;
    }

 
    if (!in_array($role, ["admin", "lecturer"], true)) {
        header("Location: admin_users.php?error=role");
        exit;
    }

    try {

         $stmt = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE email = ?
        ");

        $stmt->execute([$email]);

        if ($stmt->fetch()) {
            header("Location: admin_users.php?error=exists");
            exit;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // إدخال المستخدم الجديد
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, password, role)
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $name,
            $email,
            $hashedPassword,
            $role
        ]);

        header("Location: admin_users.php?success=added");
        exit;

    } catch (Exception $e) {
        header("Location: admin_users.php?error=database");
        exit;
    }
}

$success = "";
$error = "";

if (isset($_GET["success"])) {
    if ($_GET["success"] === "added") {
        $success = "تمت إضافة المستخدم بنجاح.";
    }
}

if (isset($_GET["error"])) {
    if ($_GET["error"] === "empty") {
        $error = "يرجى تعبئة جميع الحقول.";
    } elseif ($_GET["error"] === "email") {
        $error = "البريد الإلكتروني غير صالح.";
    } elseif ($_GET["error"] === "password") {
        $error = "كلمة المرور يجب أن تكون 8 أحرف على الأقل.";
    } elseif ($_GET["error"] === "role") {
        $error = "الدور المختار غير صالح.";
    } elseif ($_GET["error"] === "exists") {
        $error = "البريد الإلكتروني مستخدم مسبقًا.";
    } elseif ($_GET["error"] === "database") {
        $error = "حدث خطأ أثناء حفظ البيانات.";
    }
}

$stmt = $pdo->query("
    SELECT user_id, name, email, role
    FROM users
    ORDER BY role,user_id 
");

$users = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين</title>
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
            <a href="logout.php" class="logout-btn">تسجيل الخروج</a>
        </div>
    </div>

    <div class="page-content">

        <h2>إدارة المستخدمين</h2>

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

        <h3>إضافة مستخدم جديد</h3>

        <form method="POST" action="admin_users.php">

            <div class="form-group">
                <label for="name">اسم المستخدم</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="form-group">
                <label for="email">البريد الإلكتروني</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">كلمة المرور</label>
                <input type="password" id="password" name="password" required>
                <small>يجب أن تكون كلمة المرور 8 أحرف على الأقل.</small>
            </div>

            <div class="form-group">
                <label for="role">الدور</label>

                <select id="role" name="role" required>
                    <option value="lecturer">محاضر - Lecturer</option>
                    <option value="admin">مدير - Admin</option>
                </select>
            </div>

            <button type="submit">إضافة المستخدم</button>

        </form>

        <hr>

        <h3>قائمة المستخدمين</h3>

        <?php if (count($users) > 0): ?>

            <table>
                <thead>
                    <tr>
                        <th>رقم المستخدم</th>
                        <th>الاسم</th>
                        <th>البريد الإلكتروني</th>
                        <th>الدور</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo (int)$user["user_id"]; ?></td>
                            <td><?php echo htmlspecialchars($user["name"]); ?></td>
                            <td><?php echo htmlspecialchars($user["email"]); ?></td>

                            <td>
                                <?php if ($user["role"] === "admin"): ?>
                                    مدير
                                <?php else: ?>
                                    محاضر
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php else: ?>

            <p>لا يوجد مستخدمون حاليًا.</p>

        <?php endif; ?>

    </div>

</body>

</html>