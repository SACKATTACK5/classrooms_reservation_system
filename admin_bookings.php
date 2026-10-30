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

function formatTime($time)
{
    if ($time === null || $time === "") {
        return "";
    }

    return substr($time, 0, 5);
}

function statusBadge($status)
{
    $labels = [
        "pending" => "معلق",
        "approved" => "مقبول",
        "rejected" => "مرفوض"
    ];

    $classes = [
        "pending" => "badge-pending",
        "approved" => "badge-approved",
        "rejected" => "badge-rejected"
    ];

    $label = $labels[$status] ?? $status;
    $class = $classes[$status] ?? "";

    return '<span class="badge ' . $class . '">' . htmlspecialchars($label) . '</span>';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $booking_id = filter_input(INPUT_POST, "booking_id", FILTER_VALIDATE_INT);
    $action = $_POST["action"] ?? "";

    if (!$booking_id || !in_array($action, ["approve", "reject"], true)) {
        header("Location: admin_bookings.php?error=invalid");
        exit;
    }

    
    $stmt = $pdo->prepare("
        SELECT booking_id, room_id, booking_date, start_time, end_time, status
        FROM bookings
        WHERE booking_id = ?
    ");

    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        header("Location: admin_bookings.php?error=notfound");
        exit;
    }

    if ($booking["status"] !== "pending") {
        header("Location: admin_bookings.php?error=notpending");
        exit;
    }

    if ($action === "reject") {

        $stmt = $pdo->prepare("
            UPDATE bookings
            SET status = 'rejected'
            WHERE booking_id = ? AND status = 'pending'
        ");

        $stmt->execute([$booking_id]);

        header("Location: admin_bookings.php?success=rejected");
        exit;
    }

    
    if ($action === "approve") {

     
        $autoDeclineStatus = "rejected";

        try {

     
            $pdo->beginTransaction();

     
            $stmt = $pdo->prepare("
                SELECT booking_id, room_id, booking_date, start_time, end_time, status
                FROM bookings
                WHERE booking_id = ?
                FOR UPDATE
            ");

            $stmt->execute([$booking_id]);
            $currentBooking = $stmt->fetch();

            if (!$currentBooking || $currentBooking["status"] !== "pending") {
                $pdo->rollBack();

                header("Location: admin_bookings.php?error=notpending");
                exit;
            }

            
            $stmt = $pdo->prepare("
                SELECT booking_id
                FROM bookings
                WHERE room_id = ?
                  AND booking_date = ?
                  AND booking_id <> ?
                  AND status IN ('pending', 'approved')
                  AND start_time < ?
                  AND end_time > ?
                FOR UPDATE
            ");

            $stmt->execute([
                $currentBooking["room_id"],
                $currentBooking["booking_date"],
                $booking_id,
                $currentBooking["end_time"],
                $currentBooking["start_time"]
            ]);
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM bookings
                WHERE room_id = ?
                  AND booking_date = ?
                  AND status = 'approved'
                  AND booking_id <> ?
                  AND start_time < ?
                  AND end_time > ?
            ");

            $stmt->execute([
                $currentBooking["room_id"],
                $currentBooking["booking_date"],
                $booking_id,
                $currentBooking["end_time"],
                $currentBooking["start_time"]
            ]);

            if ($stmt->fetchColumn() > 0) {
                $pdo->rollBack();

                header("Location: admin_bookings.php?error=conflict");
                exit;
            }


            $stmt = $pdo->prepare("
                UPDATE bookings
                SET status = 'approved'
                WHERE booking_id = ?
                  AND status = 'pending'
            ");

            $stmt->execute([$booking_id]);

            $stmt = $pdo->prepare("
                UPDATE bookings
                SET status = ?
                WHERE room_id = ?
                  AND booking_date = ?
                  AND booking_id <> ?
                  AND status = 'pending'
                  AND start_time < ?
                  AND end_time > ?
            ");

            $stmt->execute([
                $autoDeclineStatus,
                $currentBooking["room_id"],
                $currentBooking["booking_date"],
                $booking_id,
                $currentBooking["end_time"],
                $currentBooking["start_time"]
            ]);

            $pdo->commit();

            header("Location: admin_bookings.php?success=approved");
            exit;

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            header("Location: admin_bookings.php?error=conflict");
            exit;
        }
    }}

$success = "";
$error = "";

if (isset($_GET["success"])) {
    if ($_GET["success"] === "approved") {
        $success = "تم قبول الطلب بنجاح.";
    } elseif ($_GET["success"] === "rejected") {
        $success = "تم رفض الطلب بنجاح.";
    }
}

if (isset($_GET["error"])) {
    if ($_GET["error"] === "invalid") {
        $error = "الطلب غير صالح.";
    } elseif ($_GET["error"] === "notfound") {
        $error = "الطلب غير موجود.";
    } elseif ($_GET["error"] === "notpending") {
        $error = "لا يمكن معالجة هذا الطلب لأنه ليس معلقًا.";
    } elseif ($_GET["error"] === "conflict") {
        $error = "لا يمكن قبول الطلب، يوجد حجز مقبول متعارض في نفس القاعة والوقت.";
    }
}

$stmt = $pdo->query("
    SELECT
        b.booking_id,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.status,
        u.name AS lecturer_name,
        u.email AS lecturer_email,
        r.room_name
    FROM bookings b
    INNER JOIN users u ON b.user_id = u.user_id
    INNER JOIN rooms r ON b.room_id = r.room_id
    WHERE b.status = 'pending'
    ORDER BY b.booking_id DESC
");

$pendingRequests = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT
        b.booking_id,
        b.booking_date,
        b.start_time,
        b.end_time,
        b.status,
        u.name AS lecturer_name,
        r.room_name
    FROM bookings b
    INNER JOIN users u ON b.user_id = u.user_id
    INNER JOIN rooms r ON b.room_id = r.room_id
    ORDER BY b.booking_id DESC
    LIMIT 10
");

$latestRequests = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة طلبات الحجز</title>
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
            <a href="logout.php" class="logout-btn">تسجيل الخروج</a>
        </div>
    </div>

    <div class="page-content">

        <h2>إدارة طلبات الحجز</h2>

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

        <h3>الطلبات المعلقة</h3>

        <?php if (count($pendingRequests) > 0): ?>

            <table>
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>المحاضر</th>
                        <th>القاعة</th>
                        <th>التاريخ</th>
                        <th>من</th>
                        <th>إلى</th>
                        <th>الإجراء</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($pendingRequests as $request): ?>
                        <tr>
                            <td><?php echo (int)$request["booking_id"]; ?></td>

                            <td>
                                <?php echo htmlspecialchars($request["lecturer_name"]); ?>
                                <br>
                                <small><?php echo htmlspecialchars($request["lecturer_email"]); ?></small>
                            </td>

                            <td><?php echo htmlspecialchars($request["room_name"]); ?></td>

                            <td><?php echo htmlspecialchars($request["booking_date"]); ?></td>

                            <td><?php echo htmlspecialchars(formatTime($request["start_time"])); ?></td>

                            <td><?php echo htmlspecialchars(formatTime($request["end_time"])); ?></td>

                            <td>
                                <form
                                    method="POST"
                                    action="admin_bookings.php"
                                    class="inline-form"
                                    onsubmit="return confirm('هل أنت متأكد أنك تريد قبول هذا الطلب؟');"
                                >
                                    <input type="hidden" name="booking_id" value="<?php echo (int)$request["booking_id"]; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn-success">قبول</button>
                                </form>

                                <form
                                    method="POST"
                                    action="admin_bookings.php"
                                    class="inline-form"
                                    onsubmit="return confirm('هل أنت متأكد أنك تريد رفض هذا الطلب؟');"
                                >
                                    <input type="hidden" name="booking_id" value="<?php echo (int)$request["booking_id"]; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn-danger">رفض</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php else: ?>

            <div class="alert alert-success">
                لا توجد طلبات معلقة حاليًا.
            </div>

        <?php endif; ?>

        <hr>

        <h3>آخر الطلبات</h3>

        <?php if (count($latestRequests) > 0): ?>

            <table>
                <thead>
                    <tr>
                        <th>رقم الطلب</th>
                        <th>المحاضر</th>
                        <th>القاعة</th>
                        <th>التاريخ</th>
                        <th>من</th>
                        <th>إلى</th>
                        <th>الحالة</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($latestRequests as $request): ?>
                        <tr>
                            <td><?php echo (int)$request["booking_id"]; ?></td>
                            <td><?php echo htmlspecialchars($request["lecturer_name"]); ?></td>
                            <td><?php echo htmlspecialchars($request["room_name"]); ?></td>
                            <td><?php echo htmlspecialchars($request["booking_date"]); ?></td>
                            <td><?php echo htmlspecialchars(formatTime($request["start_time"])); ?></td>
                            <td><?php echo htmlspecialchars(formatTime($request["end_time"])); ?></td>
                            <td><?php echo statusBadge($request["status"]); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php else: ?>

            <p>لا توجد طلبات بعد.</p>

        <?php endif; ?>

    </div>

</body>

</html>