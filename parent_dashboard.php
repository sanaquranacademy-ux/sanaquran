<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['parent_phone'])) {
    header("Location: parent_login.php");
    exit;
}

$parent_phone = $_SESSION['parent_phone'];

// اس والد کے تمام بچے نکالیں
$children_query = $conn->query("
    SELECT s.*, u.name as teacher_name 
    FROM students s 
    LEFT JOIN users u ON s.teacher_id = u.id 
    WHERE s.phone LIKE '%$parent_phone%' AND s.status = 'active'
");

if ($children_query->num_rows === 0) {
    die("<h2 style='text-align:center; margin-top:50px;'>No active student records associated with this number.</h2>");
}

// اگر ملٹیپل بچے ہوں تو منتخب بچہ، ورنہ پہلا بچہ
$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$children_list = [];
while ($row = $children_query->fetch_assoc()) {
    $children_list[] = $row;
}

if ($selected_student_id === 0 && !empty($children_list)) {
    $selected_student_id = $children_list[0]['id'];
}

// منتخب بچے کی معلومات حاصل کرنا
$curr_student = null;
foreach ($children_list as $c) {
    if ($c['id'] == $selected_student_id) {
        $curr_student = $c;
        break;
    }
}
if (!$curr_student) $curr_student = $children_list[0];

// حاضری کے اعدادوشمار
$att_stats = $conn->query("
    SELECT 
        COUNT(*) as total_days,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as leave_count,
        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_count,
        SUM(CASE WHEN status = 'unexcused' THEN 1 ELSE 0 END) as unexcused_count
    FROM attendance 
    WHERE student_id = " . $curr_student['id']
)->fetch_assoc();

// سبق کی تاریخ
$lessons = $conn->query("
    SELECT * FROM daily_lessons 
    WHERE student_id = " . $curr_student['id'] . " 
    ORDER BY lesson_date DESC, id DESC LIMIT 20
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($curr_student['name']); ?> - Student Progress (Parent View)</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Poppins', sans-serif; margin: 0; background: #0f1410; color: #f5f5f5; }
        .navbar { background: #18221a; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #283e2c; }
        .navbar a { color: #f5f5f5; text-decoration: none; background: #d9534f; padding: 6px 14px; border-radius: 4px; font-weight: bold; font-size: 13px; }
        .container { max-width: 1200px; margin: 25px auto; padding: 0 20px; }
        
        .children-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 1px solid #283e2c; padding-bottom: 10px; }
        .tab-btn { background: #18221a; color: #a0aab0; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; border: 1px solid #283e2c; }
        .tab-btn.active { background: #5cb85c; color: #000; border-color: #5cb85c; }

        .profile-card { background: #18221a; padding: 20px 25px; border-radius: 10px; border-left: 5px solid #5cb85c; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .grid-stats { display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin-bottom: 25px; }
        
        .stat-card { background: #18221a; padding: 18px 10px; border-radius: 8px; border: 1px solid #283e2c; text-align: center; }
        .stat-card h3 { margin: 0; font-size: 26px; }
        .stat-card p { margin: 5px 0 0 0; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #a0aab0; }
        
        .total-card h3 { color: #fff; }
        .present-card h3 { color: #5cb85c; }
        .leave-card h3 { color: #33b5e5; }
        .excused-card h3 { color: #ffbb33; }
        .unexcused-card h3 { color: #ff4444; }
        
        .card { background: #18221a; padding: 25px; border-radius: 10px; border: 1px solid #283e2c; margin-bottom: 25px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #283e2c; padding-bottom: 10px; }
        .card h3 { margin: 0; color: #5cb85c; font-size: 18px; }
        
        .btn-pdf { background: #5cb85c; color: #000; text-decoration: none; padding: 8px 16px; border-radius: 20px; font-size: 12px; font-weight: bold; display: inline-flex; align-items: center; gap: 6px; }
        .btn-pdf:hover { background: #4cae4c; }

        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        table, th, td { border: 1px solid #283e2c; }
        th, td { padding: 10px; text-align: left; }
        th { background: #121813; color: #5cb85c; }
        tr:hover { background: #131b14; }
    </style>
</head>
<body>

<div class="navbar">
    <div style="display:flex; align-items:center; gap:10px;">
        <span style="font-weight:bold; font-size:16px;">Sana Online Quran House</span>
        <span style="color:#5cb85c;">(Parent Portal)</span>
    </div>
    <div>
        <span style="margin-right:15px; font-size:13px; color:#a0aab0;">Welcome, <strong><?php echo htmlspecialchars($_SESSION['parent_name']); ?></strong></span>
        <a href="parent_logout.php">Logout</a>
    </div>
</div>

<div class="container">

    <!-- اگر والد کے ایک سے زائد بچے ہوں تو ٹیبز دکھائیں -->
    <?php if (count($children_list) > 1): ?>
        <div class="children-tabs">
            <?php foreach ($children_list as $child): ?>
                <a href="parent_dashboard.php?student_id=<?php echo $child['id']; ?>" class="tab-btn <?php echo ($child['id'] == $curr_student['id']) ? 'active' : ''; ?>">
                    👤 <?php echo htmlspecialchars($child['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- بچے کی سمری -->
    <div class="profile-card">
        <div>
            <h2 style="margin: 0; color: #fff;"><?php echo htmlspecialchars($curr_student['name']); ?></h2>
            <p style="margin: 4px 0 0 0; color: #a0aab0; font-size: 13px;">
                Course: <strong style="color:#5cb85c;"><?php echo htmlspecialchars($curr_student['course']); ?></strong> | 
                Timing: <strong><?php echo $curr_student['class_time'] ? date('h:i A', strtotime($curr_student['class_time'])) : 'N/A'; ?></strong>
            </p>
        </div>
        <div>
            <span style="background: #121813; border: 1px solid #283e2c; padding: 6px 14px; border-radius: 20px; font-size: 13px; color: #5cb85c;">
                Assigned Tutor: <strong><?php echo $curr_student['teacher_name'] ? htmlspecialchars($curr_student['teacher_name']) : 'Academy Faculty'; ?></strong>
            </span>
        </div>
    </div>

    <!-- 5 حاضری کارڈز -->
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

    <!-- روزانہ اسباق لاگ + پی ڈی ایف ڈاؤن لوڈ بٹن -->
    <div class="card">
        <div class="card-header">
            <h3>Daily Lesson Progress & Teacher Feedback</h3>
            <a href="generate_pdf_report.php?student_id=<?php echo $curr_student['id']; ?>" target="_blank" class="btn-pdf">
                📄 Download Official PDF Report Card
            </a>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Course</th>
                    <th>Pages / Ayah</th>
                    <th>Sabaq (New Lesson)</th>
                    <th>Sabaqi & Manzil</th>
                    <th>Teacher's Remarks / Tajweed Feedback</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($lessons->num_rows > 0): ?>
                    <?php while($l = $lessons->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo date('d M, Y', strtotime($l['lesson_date'])); ?></strong></td>
                            <td><span style="color:#5cb85c; font-weight:bold;"><?php echo htmlspecialchars($l['course']); ?></span></td>
                            <td>Pg: <?php echo htmlspecialchars($l['page_no']); ?> | Ayah: <?php echo htmlspecialchars($l['ayah_no']); ?></td>
                            <td><strong><?php echo htmlspecialchars($l['sabaq']); ?></strong></td>
                            <td>
                                <small><strong>Sabaqi:</strong> <?php echo htmlspecialchars($l['sabaqi']); ?></small><br>
                                <small><strong>Manzil:</strong> <?php echo htmlspecialchars($l['manzil']); ?></small>
                            </td>
                            <td><em style="color:#a0aab0;"><?php echo htmlspecialchars($l['remarks'] ?: 'MashaAllah Good'); ?></em></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center; color:#a0aab0;">No lesson progress recorded yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>