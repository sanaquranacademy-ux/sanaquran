<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$msg = '';
$selected_month = $_GET['month'] ?? date('Y-m');
$selected_teacher = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

// مینوئل رقم اور اسٹیٹس محفوظ کرنا
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_billing') {
    $t_id = (int)$_POST['teacher_id'];
    $s_id = (int)$_POST['student_id'];
    $present_count = (int)$_POST['present_classes'];
    $amount = (float)$_POST['amount_paid'];
    $status = $_POST['status'];
    $b_month = $_POST['billing_month'];

    $stmt = $conn->prepare("
        INSERT INTO teacher_billing (teacher_id, student_id, billing_month, present_classes, amount_paid, status)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            present_classes = VALUES(present_classes),
            amount_paid = VALUES(amount_paid),
            status = VALUES(status)
    ");
    $stmt->bind_param("iisids", $t_id, $s_id, $b_month, $present_count, $amount, $status);
    if ($stmt->execute()) {
        $msg = "Billing record updated successfully!";
    }
}

// تمام اساتذہ کی فہرست
$teachers = $conn->query("SELECT id, name FROM users WHERE role = 'teacher' ORDER BY name ASC");

// منتخب استاد یا تمام اساتذہ کا منتخب مہینے کا ڈیٹا نکالنا
$query_str = "
    SELECT 
        u.id as teacher_id,
        u.name as teacher_name,
        s.id as student_id,
        s.name as student_name,
        s.course,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as calculated_presents,
        tb.amount_paid,
        tb.status as payment_status
    FROM students s
    JOIN users u ON s.teacher_id = u.id
    LEFT JOIN attendance a ON s.id = a.student_id 
        AND a.teacher_id = u.id 
        AND DATE_FORMAT(a.date, '%Y-%m') = '$selected_month'
    LEFT JOIN teacher_billing tb ON tb.teacher_id = u.id 
        AND tb.student_id = s.id 
        AND tb.billing_month = '$selected_month'
    WHERE u.role = 'teacher'
";

if ($selected_teacher > 0) {
    $query_str .= " AND u.id = $selected_teacher";
}

$query_str .= " GROUP BY u.id, s.id ORDER BY u.name ASC, s.name ASC";
$billing_records = $conn->query($query_str);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Monthly Classes & Billing - Quran Academy</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: #f0f2f5; }
        .navbar { background: #1a4d2e; padding: 15px 30px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: #ffffff; text-decoration: none; font-weight: bold; background: #2e7d32; padding: 8px 15px; border-radius: 4px; }
        .container { max-width: 1250px; margin: 25px auto; padding: 0 20px; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .card h3 { margin-top: 0; color: #1a4d2e; border-bottom: 2px solid #e8f5e9; padding-bottom: 10px; }
        
        .filter-bar { display: flex; gap: 15px; align-items: center; background: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .filter-bar label { font-weight: 600; font-size: 13px; }
        .filter-bar input, .filter-bar select { padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        .btn-filter { background: #1a4d2e; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; vertical-align: middle; }
        th { background: #e8f5e9; color: #1a4d2e; }
        
        .btn-save { background: #2e7d32; color: white; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .alert-success { background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="navbar">
    <h2>Teacher Monthly Classes & Payroll (Admin Only)</h2>
    <a href="admin_dashboard.php">← Back to Dashboard</a>
</div>

<div class="container">
    <?php if ($msg): ?><div class="alert-success"><?php echo $msg; ?></div><?php endif; ?>

    <!-- فلٹر بار (مہینہ اور استاد منتخب کریں) -->
    <form method="GET" class="filter-bar">
        <label>Select Month:</label>
        <input type="month" name="month" value="<?php echo htmlspecialchars($selected_month); ?>" required>

        <label>Filter by Teacher:</label>
        <select name="teacher_id">
            <option value="0">-- All Teachers --</option>
            <?php 
            $teachers->data_seek(0);
            while($t = $teachers->fetch_assoc()): ?>
                <option value="<?php echo $t['id']; ?>" <?php echo ($selected_teacher == $t['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($t['name']); ?>
                </option>
            <?php endwhile; ?>
        </select>

        <button type="submit" class="btn-filter">Load Records</button>
    </form>

    <!-- کلاسز اور رقم کا تفصیلی ٹیبل -->
    <div class="card">
        <h3>Month: <span style="color:#2e7d32;"><?php echo date('F Y', strtotime($selected_month . '-01')); ?></span></h3>
        <table>
            <thead>
                <tr>
                    <th>Teacher</th>
                    <th>Student Name</th>
                    <th>Course</th>
                    <th>Total Present Classes (This Month)</th>
                    <th>Manual Amount Payable</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($billing_records->num_rows > 0): ?>
                    <?php while($row = $billing_records->fetch_assoc()): ?>
                        <tr>
                            <form method="POST">
                                <input type="hidden" name="action" value="save_billing">
                                <input type="hidden" name="teacher_id" value="<?php echo $row['teacher_id']; ?>">
                                <input type="hidden" name="student_id" value="<?php echo $row['student_id']; ?>">
                                <input type="hidden" name="billing_month" value="<?php echo $selected_month; ?>">
                                <input type="hidden" name="present_classes" value="<?php echo $row['calculated_presents']; ?>">

                                <td><strong><?php echo htmlspecialchars($row['teacher_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['student_name']); ?></td>
                                <td><span style="background:#e8f5e9; padding:2px 6px; border-radius:3px; font-weight:bold;"><?php echo htmlspecialchars($row['course']); ?></span></td>
                                <td>
                                    <span style="font-size:16px; font-weight:bold; color:#2e7d32; background:#e8f5e9; padding:4px 10px; border-radius:15px;">
                                        <?php echo (int)$row['calculated_presents']; ?> Classes
                                    </span>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="amount_paid" value="<?php echo htmlspecialchars($row['amount_paid'] ?? '0.00'); ?>" style="padding:6px; width:100px; border:1px solid #ccc; border-radius:4px;" placeholder="Amount">
                                </td>
                                <td>
                                    <select name="status" style="padding:6px; border-radius:4px; border:1px solid #ccc;">
                                        <option value="pending" <?php echo ($row['payment_status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="paid" <?php echo ($row['payment_status'] == 'paid') ? 'selected' : ''; ?>>Paid</option>
                                    </select>
                                </td>
                                <td>
                                    <button type="submit" class="btn-save">Save</button>
                                </td>
                            </form>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center;">No student or class data found for this month.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>