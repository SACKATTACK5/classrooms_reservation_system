<?php

session_start();

require_once __DIR__ . "/includes/conn.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "lecturer") {
    header("Location: index.php");
    exit;
}


function validateBookingDateTime($date, $start_time, $end_time)
{
    if (trim($date) === "" || trim($start_time) === "" || trim($end_time) === "") {
        return "يرجى اختيار التاريخ ووقت البداية ووقت النهاية.";
    }

    if (strtotime($date) === false) {
        return "التاريخ غير صالح.";
    }

    if (strtotime($start_time) === false || strtotime($end_time) === false) {
        return "الوقت غير صالح.";
    }

    if (strtotime($start_time) >= strtotime($end_time)) {
        return "وقت النهاية يجب أن يكون بعد وقت البداية.";
    }

    if ($date < date("Y-m-d")) {
        return "لا يمكن اختيار تاريخ سابق.";
    }

    return "";
}

function getAvailableRooms($pdo, $date, $start_time, $end_time)
{
    $sql = "
        SELECT r.room_id, r.room_name, r.capacity
        FROM rooms r
        WHERE NOT EXISTS (
            SELECT 1
            FROM bookings b
            WHERE b.room_id = r.room_id
              AND b.booking_date = ?
              AND b.status = 'approved'
              AND b.start_time < ?
              AND b.end_time > ?
        )
        ORDER BY r.room_name
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date, $end_time, $start_time]);

    return $stmt->fetchAll();
}

$error = "";
$searched = false;
$availableRooms = [];

$date = $_GET["date"] ?? "";
$start_time = $_GET["start_time"] ?? "";
$end_time = $_GET["end_time"] ?? "";

if (isset($_GET["date"], $_GET["start_time"], $_GET["end_time"])) {
    $searched = true;

    $error = validateBookingDateTime($date, $start_time, $end_time);

    if ($error === "") {
        $availableRooms = getAvailableRooms($pdo, $date, $start_time, $end_time);
    }
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $date = trim($_POST["date"] ?? "");
    $start_time = trim($_POST["start_time"] ?? "");
    $end_time = trim($_POST["end_time"] ?? "");
    $room_id = filter_var($_POST["room_id"] ?? "", FILTER_VALIDATE_INT);

    $error = validateBookingDateTime($date, $start_time, $end_time);

    if ($error === "" && !$room_id) {
        $error = "يرجى اختيار قاعة صالحة.";
    }

    if ($error === "") {
        $stmt = $pdo->prepare("SELECT room_id FROM rooms WHERE room_id = ?");
        $stmt->execute([$room_id]);

        if (!$stmt->fetch()) {
            $error = "القاعة غير موجودة.";
        }
    }

    if ($error === "") {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM bookings
            WHERE room_id = ?
              AND booking_date = ?
              AND status = 'approved'
              AND start_time < ?
              AND end_time > ?
        ");

        $stmt->execute([$room_id, $date, $end_time, $start_time]);

        if ($stmt->fetchColumn() > 0) {
            $error = "لا يمكن إتمام الحجز، القاعة محجوزة رسميًا في نفس الفترة.";
        }
    }

    if ($error === "") {
        $stmt = $pdo->prepare("
            INSERT INTO bookings (user_id, room_id, booking_date, start_time, end_time, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");

        $stmt->execute([
            $_SESSION["user_id"],
            $room_id,
            $date,
            $start_time,
            $end_time
        ]);

        header("Location: booking.php?success=1");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقديم طلب حجز</title>
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
        <h2>تقديم طلب حجز قاعة</h2>

        <?php if (isset($_GET["success"]) && $_GET["success"] == 1): ?>
            <div class="alert alert-success">
                تم إرسال طلب الحجز بنجاح، وسيتم مراجعته من قبل المدير.
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <h3>1. حدد التاريخ والفترة الزمنية</h3>

        <form method="GET" action="booking.php">

            <div class="form-group">
                <label for="date">التاريخ</label>
                <input
                    type="date"
                    id="date"
                    name="date"
                    value="<?php echo htmlspecialchars($date); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="start_time">وقت البداية</label>
                <input
                    type="time"
                    id="start_time"
                    name="start_time"
					min="08:00"
					max="17:30"
                    value="<?php echo htmlspecialchars($start_time); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label for="end_time">وقت النهاية</label>
                <input
                    type="time"
                    id="end_time"
                    name="end_time"
					min="08:30"
					max="18:00"
                    value="<?php echo htmlspecialchars($end_time); ?>"
                    required
                >
            </div>

            <button type="submit">عرض القاعات المتاحة</button>

        </form>

        <?php if ($searched && $error === ""): ?>

            <hr>

            <h3>2. اختر قاعة متاحة</h3>

            <?php if (count($availableRooms) > 0): ?>

                <form method="POST" action="booking.php">

                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>">
                    <input type="hidden" name="start_time" value="<?php echo htmlspecialchars($start_time); ?>">
                    <input type="hidden" name="end_time" value="<?php echo htmlspecialchars($end_time); ?>">

                    <div class="form-group">
                        <label for="room_id">القاعات المتاحة</label>

                        <select name="room_id" id="room_id" required>
                            <option value="">اختر قاعة</option>

                            <?php foreach ($availableRooms as $room): ?>
                                <option value="<?php echo (int)$room["room_id"]; ?>">
                                    <?php echo htmlspecialchars($room["room_name"]); ?>
                                    - السعة: <?php echo (int)$room["capacity"]; ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <button type="submit">إرسال طلب الحجز</button>

                </form>

            <?php else: ?>

                <div class="alert alert-error">
                    لا توجد قاعات متاحة في هذه الفترة.
                </div>

            <?php endif; ?>

        <?php endif; ?>

    </div>

</body>

</html>