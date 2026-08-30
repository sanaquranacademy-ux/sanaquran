<?php
session_start();
require_once 'db.php';

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$st_res = $conn->query("
    SELECT s.*, u.name as teacher_name 
    FROM students s 
    LEFT JOIN users u ON s.teacher_id = u.id 
    WHERE s.id = $student_id
");

if ($st_res->num_rows === 0) {
    die("Student record not found!");
}
$student = $st_res->fetch_assoc();

// حاضری
$att = $conn->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as presents,
        SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leaves,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused,
        SUM(CASE WHEN status = 'unexcused' THEN 1 ELSE 0 END) as unexcused
    FROM attendance WHERE student_id = $student_id
")->fetch_assoc();

// اسباق
$lessons = $conn->query("SELECT * FROM daily_lessons WHERE student_id = $student_id ORDER BY lesson_date DESC LIMIT 30");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Progress Report - <?php echo htmlspecialchars($student['name']); ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 30px; color: #333; background: #fff; }
        .report-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #1a4d2e; padding-bottom: 15px; margin-bottom: 20px; }
        .logo-title { display: flex; align-items: center; gap: 15px; }
        .logo-title img { height: 65px; }
        .logo-title h1 { margin: 0; color: #1a4d2e; font-size: 22px; }
        .logo-title p { margin: 0; color: #666; font-size: 12px; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; background: #f9f9f9; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; }
        .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 20px; text-align: center; }
        .stat-box { border: 1px solid #ddd; padding: 10px; border-radius: 6px; }
        .stat-box h3 { margin: 0; font-size: 20px; color: #1a4d2e; }
        .stat-box p { margin: 2px 0 0 0; font-size: 10px; text-transform: uppercase; color: #666; font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
        table, th, td { border: 1px solid #ccc; }
        th, td { padding: 8px; text-align: left; }
        th { background: #e8f5e9; color: #1a4d2e; }
        
        .footer-sign { display: flex; justify-content: space-between; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; }
        .print-btn { background: #1a4d2e; color: white; border: none; padding: 8px 18px; border-radius: 4px; cursor: pointer; font-weight: bold; margin-bottom: 15px; }
        @media print { .print-btn { display: none; } }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>

<div class="report-header">
    <div class="logo-title">
        <img src="logo.jpg" alt="Logo" onerror="this.src='logo.png'">
        <div>
            <h1>SANA ONLINE QURAN HOUSE</h1>
            <p>Official Student Monthly Progress & Performance Report</p>
        </div>
    </div>
    <div style="text-align:right; font-size:12px;">
        <strong>Date:</strong> <?php echo date('d M, Y'); ?><br>
        <strong>WhatsApp:</strong> +971 52 719 4855
    </div>
</div>

<div class="info-grid">
    <div>
        <strong>Student Name:</strong> <?php echo htmlspecialchars($student['name']); ?><br>
        <strong>Guardian Name:</strong> <?php echo htmlspecialchars($student['guardian_name']); ?><br>
        <strong>Contact:</strong> <?php echo htmlspecialchars($student['phone']); ?>
    </div>
    <div>
        <strong>Enrolled Course:</strong> <?php echo htmlspecialchars($student['course']); ?><br>
        <strong>Assigned Tutor:</strong> <?php echo htmlspecialchars($student['teacher_name'] ?: 'Academy Faculty'); ?><br>
        <strong>Class Timing:</strong> <?php echo $student['class_time'] ? date('h:i A', strtotime($student['class_time'])) : 'N/A'; ?>
    </div>
</div>

<h4 style="margin:0 0 8px 0; color:#1a4d2e;">Attendance Summary</h4>
<div class="stats-grid">
    <div class="stat-box"><h3><?php echo (int)$att['total']; ?></h3><p>Total Classes</p></div>
    <div class="stat-box"><h3><?php echo (int)$att['presents']; ?></h3><p>Present</p></div>
    <div class="stat-box"><h3><?php echo (int)$att['leaves']; ?></h3><p>Leaves</p></div>
    <div class="stat-box"><h3><?php echo (int)$att['excused']; ?></h3><p>Excused</p></div>
    <div class="stat-box"><h3><?php echo (int)$att['unexcused']; ?></h3><p>Unexcused</p></div>
</div>

<h4 style="margin:0 0 8px 0; color:#1a4d2e;">Lesson Progress Logs</h4>
<table>
    <thead>
        <tr>
            <th>Date</th>
            <th>Course</th>
            <th>Pages / Ayah</th>
            <th>Sabaq Details</th>
            <th>Sabaqi & Manzil</th>
            <th>Teacher Tajweed Remarks</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($lessons->num_rows > 0): ?>
            <?php while($l = $lessons->fetch_assoc()): ?>
                <tr>
                    <td><?php echo date('d M, Y', strtotime($l['lesson_date'])); ?></td>
                    <td><?php echo htmlspecialchars($l['course']); ?></td>
                    <td>Pg: <?php echo htmlspecialchars($l['page_no']); ?> | Ayah: <?php echo htmlspecialchars($l['ayah_no']); ?></td>
                    <td><?php echo htmlspecialchars($l['sabaq']); ?></td>
                    <td>Sabaqi: <?php echo htmlspecialchars($l['sabaqi']); ?> | Manzil: <?php echo htmlspecialchars($l['manzil']); ?></td>
                    <td><?php echo htmlspecialchars($l['remarks']); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="6" style="text-align:center;">No lesson logs found.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="footer-sign">
    <div><strong>Teacher Signature:</strong> _____________________</div>
    <div><strong>Admin / Principal Signature:</strong> _____________________</div>
</div>

</body>
</html>