<?php
session_start();
require_once 'db.php';

// ایڈمن سیکیورٹی چیک
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg = '';

// اگر ایڈمن ریمارکس سیو کرے
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_admin_remark') {
    $lesson_id = (int)$_POST['lesson_id'];
    $admin_remark = trim($_POST['admin_remark']);
    
    $stmt = $conn->prepare("UPDATE daily_lessons SET admin_remarks = ? WHERE id = ?");
    $stmt->bind_param("si", $admin_remark, $lesson_id);
    if ($stmt->execute()) {
        $msg = "Admin remarks updated successfully!";
    }
}

// طالب علم اور استاد کی معلومات
$st_query = $conn->query("
    SELECT s.*, u.name as teacher_name 
    FROM students s 
    LEFT JOIN users u ON s.teacher_id = u.id 
    WHERE s.id = $student_id
");

if ($st_query->num_rows === 0) {
    die("<h2 style='text-align:center; margin-top:50px; font-family:sans-serif;'>Student record not found!</h2>");
}
$student = $st_query->fetch_assoc();

// حاضری کے اعدادوشمار (Attendance Stats)
$att_stats = $conn->query("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_count,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_count,
        SUM(CASE WHEN status = 'unexcused' THEN 1 ELSE 0 END) as unexcused_count
    FROM attendance 
    WHERE student_id = $student_id
")->fetch_assoc();

// سبق کی تاریخ
$lessons = $conn->query("
    SELECT dl.*, u.name as logged_by 
    FROM daily_lessons dl 
    LEFT JOIN users u ON dl.teacher_id = u.id 
    WHERE dl.student_id = $student_id 
    ORDER BY dl.lesson_date DESC, dl.id DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['name']); ?> - Student Progress (Admin)</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: #f0f2f5; }
        .navbar { background: #1a4d2e; padding: 15px 30px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: #ffffff; text-decoration: none; font-weight: bold; background: #2e7d32; padding: 8px 15px; border-radius: 4px; }
        .container { max-width: 1250px; margin: 25px auto; padding: 0 20px; }
        
        .profile-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.06); margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; border-left: 6px solid #1a4d2e; }
        
        /* 5 کارڈز کے لیے گرڈ لے آؤٹ */
        .grid-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 25px; }
        
        .stat-card { background: white; padding: 20px 10px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.05); text-align: center; }
        .stat-card h3 { margin: 0; font-size: 28px; }
        .stat-card p { margin: 6px 0 0 0; font-weight: bold; font-size: 11px; text-transform: uppercase; color: #555; letter-spacing: 0.5px; }
        
        .total-card { border-top: 4px solid #37474f; } .total-card h3 { color: #37474f; }
        .present-card { border-top: 4px solid #2e7d32; } .present-card h3 { color: #2e7d32; }
        .leave-card { border-top: 4px solid #0288d1; } .leave-card h3 { color: #0288d1; }
        .excused-card { border-top: 4px solid #f57c00; } .excused-card h3 { color: #f57c00; }
        .unexcused-card { border-top: 4px solid #d32f2f; } .unexcused-card h3 { color: #d32f2f; }
        
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .card h3 { margin-top: 0; color: #1a4d2e; border-bottom: 2px solid #e8f5e9; padding-bottom: 10px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; vertical-align: top; }
        th { background: #e8f5e9; color: #1a4d2e; font-weight: 600; }
        
        .remark-form input[type="text"] { width: 75%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; }
        .remark-form button { padding: 6px 10px; background: #1a4d2e; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; }
        .alert-success { background: #d4edda; color: #155724; padding: 12px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="navbar">
    <h2>Admin Portal - Student Progress View</h2>
    <a href="admin_dashboard.php">← Back to Dashboard</a>
</div>

<div class="container">

    <?php if ($msg): ?><div class="alert-success"><?php echo $msg; ?></div><?php endif; ?>

    <!-- طالب علم کی پروفائل سمری -->
    <div class="profile-card">
        <div>
            <h2 style="margin: 0; color: #1a4d2e;"><?php echo htmlspecialchars($student['name']); ?></h2>
            <p style="margin: 5px 0 0 0; color: #666;">
                Guardian: <strong><?php echo htmlspecialchars($student['guardian_name']); ?></strong> | 
                Phone: <strong><?php echo htmlspecialchars($student['phone']); ?></strong> | 
                Timing: <strong><?php echo $student['class_time'] ? date('h:i A', strtotime($student['class_time'])) : 'N/A'; ?></strong>
            </p>
        </div>
        <div>
            <span style="background: #e8f5e9; padding: 8px 15px; border-radius: 20px; font-weight: bold; color: #1a4d2e; border: 1px solid #c8e6c9;">
                Teacher: <?php echo $student['teacher_name'] ? htmlspecialchars($student['teacher_name']) : 'Unassigned'; ?>
            </span>
        </div>
    </div>

    <!-- 5 کارڈز کا مکمل حاضری مانیٹرنگ سسٹم -->
    <div class="grid-stats">
        <div class="stat-card total-card">
            <h3><?php echo (int)$att_stats['total_days']; ?></h3>
            <p>Total Classes</p>
        </div>
        <div class="stat-card present-card">
            <h3><?php echo (int)$att_stats['present_count']; ?></h3>
            <p>Present Classes</p>
        </div>
        <div class="stat-card leave-card">
            <h3><?php echo (int)$att_stats['leave_count']; ?></h3>
            <p>Leave Classes</p>
        </div>
        <div class="stat-card excused-card">
            <h3><?php echo (int)$att_stats['excused_count']; ?></h3>
            <p>Excused Classes</p>
        </div>
        <div class="stat-card unexcused-card">
            <h3><?php echo (int)$att_stats['unexcused_count']; ?></h3>
            <p>Unexcused (Absent)</p>
        </div>
    </div>

    <!-- تفصیلی روزانہ اسباق کی تاریخ -->
    <div class="card">
        <h3>Complete Lesson & Progress History</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Course</th>
                    <th>Page / Ayah</th>
                    <th>Memorization / Sabaq</th>
                    <th>Sabaqi & Manzil</th>
                    <th>Teacher Remarks</th>
                    <th style="width: 25%;">Admin Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($lessons->num_rows > 0): ?>
                    <?php while($l = $lessons->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo date('d M, Y', strtotime($l['lesson_date'])); ?></strong></td>
                            <td><span style="background:#e8f5e9; padding:3px 6px; border-radius:4px; font-weight:bold;"><?php echo htmlspecialchars($l['course']); ?></span></td>
                            <td>Pg: <?php echo htmlspecialchars($l['page_no']); ?><br>Ayah: <?php echo htmlspecialchars($l['ayah_no']); ?></td>
                            <td><strong><?php echo htmlspecialchars($l['sabaq']); ?></strong></td>
                            <td>
                                <small><strong>Sabaqi:</strong> <?php echo htmlspecialchars($l['sabaqi']); ?></small><br>
                                <small><strong>Manzil:</strong> <?php echo htmlspecialchars($l['manzil']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($l['remarks']); ?></td>
                            <td>
                                <form method="POST" class="remark-form" style="display:flex; gap:5px;">
                                    <input type="hidden" name="action" value="save_admin_remark">
                                    <input type="hidden" name="lesson_id" value="<?php echo $l['id']; ?>">
                                    <input type="text" name="admin_remark" value="<?php echo htmlspecialchars($l['admin_remarks'] ?? ''); ?>" placeholder="Add note...">
                                    <button type="submit">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center;">No lesson logs found for this student.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ٹرانسفر ہسٹری کارڈ -->
    <div class="card">
        <h3>Teacher Assignment & Transfer History</h3>
        <table>
            <thead>
                <tr>
                    <th>Teacher Name</th>
                    <th>Assigned Date</th>
                    <th>Unassigned Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $t_hist = $conn->query("
                    SELECT th.*, u.name as teacher_name 
                    FROM student_teacher_history th 
                    JOIN users u ON th.teacher_id = u.id 
                    WHERE th.student_id = $student_id 
                    ORDER BY th.id DESC
                ");
                if ($t_hist->num_rows > 0):
                    while($th = $t_hist->fetch_assoc()): 
                ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($th['teacher_name']); ?></strong></td>
                        <td><?php echo date('d M, Y (h:i A)', strtotime($th['assigned_date'])); ?></td>
                        <td><?php echo $th['unassigned_date'] ? date('d M, Y (h:i A)', strtotime($th['unassigned_date'])) : '<em>Still Active</em>'; ?></td>
                        <td>
                            <?php if ($th['unassigned_date']): ?>
                                <span style="color:#d32f2f; font-weight:bold;">Previous Teacher</span>
                            <?php else: ?>
                                <span style="color:#2e7d32; font-weight:bold;">Current Active Teacher</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php 
                    endwhile;
                else: ?>
                    <tr><td colspan="4" style="text-align:center;">No previous transfer history recorded.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>