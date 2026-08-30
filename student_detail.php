<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

$teacher_id = $_SESSION['user_id'];
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$today = date('Y-m-d');
$msg = '';
$err = '';
$wa_url = '';

// طالب علم کا ڈیٹا لانا
$st_res = $conn->query("SELECT * FROM students WHERE id = $student_id AND teacher_id = $teacher_id");
if ($st_res->num_rows === 0) {
    die("<h2 style='text-align:center; margin-top:50px; font-family:sans-serif;'>Student not found or access denied!</h2>");
}
$student = $st_res->fetch_assoc();

// حاضری کی جانچ
$att_check = $conn->query("SELECT status FROM attendance WHERE student_id = $student_id AND date = '$today'");
$marked_attendance = ($att_check->num_rows > 0) ? $att_check->fetch_assoc()['status'] : null;

// 1. حاضری محفوظ کرنا مع واٹس ایپ الرٹ جنریشن
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'mark_attendance') {
    if ($marked_attendance) {
        $err = "Attendance is already locked for today!";
    } else {
        $status = $_POST['status'];
        $stmt = $conn->prepare("INSERT INTO attendance (student_id, teacher_id, date, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $student_id, $teacher_id, $today, $status);
        if ($stmt->execute()) {
            $msg = "Attendance marked successfully as: " . ucfirst($status);
            $marked_attendance = $status;

            // اگر طالب علم غیر حاضر ہو تو واٹس ایپ لنک تیار کریں
            if ($status === 'unexcused' || $status === 'absent') {
                $phone = preg_replace('/[^0-9]/', '', $student['phone']);
                $text = urlencode("السلام علیکم،\nمحترم والدین، آپ کا بچہ *" . $student['name'] . "* آج مورخہ *" . date('d M, Y') . "* کو قرآن کلاس سے غیر حاضر رہا ہے۔\nبرائے مہربانی غیر حاضری کی وجہ سے مطلع فرمائیں۔ شکریہ۔\n- قران اکیڈمی انتظامیہ");
                $wa_url = "https://api.whatsapp.com/send?phone=" . $phone . "&text=" . $text;
            }
        }
    }
}

// 2. سبق لاگ کرنا
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save_lesson') {
    $course = $_POST['course'];
    
    if ($course === 'Qaida') {
        $page_no = "Page " . trim($_POST['qaida_page']);
    } else {
        $from_p = trim($_POST['from_page']);
        $to_p = trim($_POST['to_page']);
        $page_no = (!empty($from_p) && !empty($to_p)) ? "Pg $from_p to $to_p" : ($from_p ? "Pg $from_p" : "");
    }

    $ayah_no = trim($_POST['ayah_no']);
    $sabaq = trim($_POST['sabaq']);
    $sabaqi = trim($_POST['sabaqi']);
    $manzil = trim($_POST['manzil']);
    $remarks = trim($_POST['remarks']);

    $stmt = $conn->prepare("INSERT INTO daily_lessons (student_id, teacher_id, lesson_date, course, page_no, ayah_no, sabaq, sabaqi, manzil, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissssssss", $student_id, $teacher_id, $today, $course, $page_no, $ayah_no, $sabaq, $sabaqi, $manzil, $remarks);
    if ($stmt->execute()) {
        $msg = "Daily lesson logged successfully!";
    } else {
        $err = "Error saving lesson record.";
    }
}

$history = $conn->query("SELECT * FROM daily_lessons WHERE student_id = $student_id ORDER BY lesson_date DESC, id DESC LIMIT 15");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($student['name']); ?> - Student Classroom</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background: #f0f4f3; }
        .navbar { background: #004d40; padding: 15px 30px; color: white; display: flex; justify-content: space-between; align-items: center; }
        .navbar a { color: #ffffff; text-decoration: none; font-weight: bold; background: #00796b; padding: 8px 15px; border-radius: 4px; }
        .container { max-width: 1100px; margin: 25px auto; padding: 0 20px; }
        .profile-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; border-left: 5px solid #004d40; }
        .grid { display: grid; grid-template-columns: 1fr 1.6fr; gap: 20px; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .card h3 { margin-top: 0; color: #004d40; border-bottom: 2px solid #e0f2f1; padding-bottom: 8px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; margin-bottom: 4px; font-weight: 600; font-size: 13px; color: #333; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 5px; box-sizing: border-box; font-size: 13px; }
        .btn-submit { background: #004d40; color: white; border: none; padding: 10px; border-radius: 5px; cursor: pointer; font-weight: bold; width: 100%; font-size: 14px; }
        .btn-submit:hover { background: #00332c; }
        .att-locked-box { padding: 15px; background: #e8f5e9; border: 1px solid #c8e6c9; color: #2e7d32; border-radius: 5px; font-weight: bold; text-align: center; }
        .alert-success { background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .alert-danger { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        
        .wa-box { background: #e8f5e9; border: 1px solid #c8e6c9; padding: 15px; border-radius: 8px; margin-bottom: 15px; text-align: center; }
        .btn-wa { display: inline-block; background: #25d366; color: white; text-decoration: none; padding: 8px 18px; border-radius: 20px; font-weight: bold; font-size: 13px; box-shadow: 0 2px 5px rgba(0,0,0,0.15); }
        .btn-wa:hover { background: #1ebd56; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 8px; text-align: left; }
        th { background: #e0f2f1; color: #004d40; }
    </style>
</head>
<body>

<div class="navbar">
    <h2>Student Classroom View</h2>
    <a href="teacher_dashboard.php">← Back to Dashboard</a>
</div>

<div class="container">
    <?php if ($msg): ?><div class="alert-success"><?php echo $msg; ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert-danger"><?php echo $err; ?></div><?php endif; ?>

    <!-- واٹس ایپ الرٹ بٹن اگر غیر حاضر ہو -->
    <?php if ($wa_url): ?>
        <div class="wa-box">
            <h4 style="margin:0 0 8px 0; color:#1b5e20;">📲 Student Marked Absent! Notify Parent on WhatsApp:</h4>
            <a href="<?php echo $wa_url; ?>" target="_blank" class="btn-wa">💬 Send Absent Notice on WhatsApp</a>
        </div>
        <script>
            // خودکار طور پر نئی ٹیب میں واٹس ایپ اوپن کرنا
            window.open('<?php echo $wa_url; ?>', '_blank');
        </script>
    <?php endif; ?>

    <div class="profile-card">
        <div>
            <h2 style="margin:0; color:#004d40;"><?php echo htmlspecialchars($student['name']); ?></h2>
            <p style="margin:5px 0 0 0; color:#666;">Enrolled Course: <strong><?php echo htmlspecialchars($student['course']); ?></strong> | Timing: <strong><?php echo date('h:i A', strtotime($student['class_time'])); ?></strong></p>
        </div>
        <div>
            <span style="background:#e0f2f1; padding:6px 12px; border-radius:20px; font-weight:bold; color:#004d40;">WhatsApp: <?php echo htmlspecialchars($student['phone']); ?></span>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h3>Today's Attendance (<?php echo date('d M, Y'); ?>)</h3>
            <?php if ($marked_attendance): ?>
                <div class="att-locked-box">
                    ✓ Attendance Locked: <span style="text-transform: uppercase;"><?php echo htmlspecialchars($marked_attendance); ?></span>
                    <p style="font-size: 11px; margin-top: 5px; color: #555;">(Status finalized and cannot be modified)</p>
                </div>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="mark_attendance">
                    <div class="form-group">
                        <label>Select Status:</label>
                        <select name="status" required>
                            <option value="present">Present</option>
                            <option value="leave">Leave</option>
                            <option value="excused">Excused</option>
                            <option value="unexcused">Unexcused (Absent)</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-submit" onclick="return confirm('Confirm attendance? Once submitted, it cannot be changed.');">Lock & Submit Attendance</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Record Lesson Progress</h3>
            <form method="POST">
                <input type="hidden" name="action" value="save_lesson">
                
                <div class="form-group">
                    <label>Select Course / Book</label>
                    <select name="course" id="courseSelect" onchange="togglePageInput()" required>
                        <option value="Qaida" <?php echo ($student['course'] == 'Norani Qaida' || $student['course'] == 'Qaida') ? 'selected' : ''; ?>>Qaida</option>
                        <option value="Nazra" <?php echo ($student['course'] == 'Nazra Quran' || $student['course'] == 'Nazra') ? 'selected' : ''; ?>>Nazra</option>
                        <option value="Memorization Quran" <?php echo ($student['course'] == 'Hifz-ul-Quran' || $student['course'] == 'Memorization Quran') ? 'selected' : ''; ?>>Memorization Quran</option>
                        <option value="Tafseer">Tafseer</option>
                        <option value="Islamic Studies">Islamic Studies</option>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns: 1.2fr 1fr; gap:10px;">
                    <div class="form-group" id="qaidaPageBox">
                        <label>Qaida Page No (1 to 35)</label>
                        <select name="qaida_page">
                            <?php for($i = 1; $i <= 35; $i++): ?>
                                <option value="<?php echo $i; ?>">Page <?php echo $i; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group" id="nazraPageBox" style="display:none;">
                        <label>Pages (From - To)</label>
                        <div style="display:flex; gap:5px; align-items:center;">
                            <input type="number" name="from_page" placeholder="From" style="width:50%;">
                            <span>-</span>
                            <input type="number" name="to_page" placeholder="To" style="width:50%;">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Ayah / Lesson No</label>
                        <input type="text" name="ayah_no" placeholder="e.g. Ayah 10-25">
                    </div>
                </div>

                <div class="form-group">
                    <label>Memorization Lesson (Hifz Sabaq)</label>
                    <input type="text" name="sabaq" placeholder="e.g. Surah Al-Mulk / Para 1">
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                    <div class="form-group">
                        <label>Sabaqi</label>
                        <input type="text" name="sabaqi" placeholder="e.g. Last 5 Pages">
                    </div>
                    <div class="form-group">
                        <label>Manzil</label>
                        <input type="text" name="manzil" placeholder="e.g. Para 29">
                    </div>
                </div>

                <div class="form-group">
                    <label>Teacher Remarks / Feedback</label>
                    <textarea name="remarks" rows="2" placeholder="Write feedback..."></textarea>
                </div>

                <button type="submit" class="btn-submit">Save Daily Progress</button>
            </form>
        </div>
    </div>

    <div class="card">
        <h3>Previous Lessons History</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Course</th>
                    <th>Pages / Ayah</th>
                    <th>Memorization / Sabaq</th>
                    <th>Teacher Remarks</th>
                    <th>Admin Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($history->num_rows > 0): ?>
                    <?php while($h = $history->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo date('d M, Y', strtotime($h['lesson_date'])); ?></td>
                            <td><span style="background:#e0f2f1; padding:2px 6px; border-radius:3px; font-weight:bold;"><?php echo htmlspecialchars($h['course']); ?></span></td>
                            <td><?php echo htmlspecialchars($h['page_no']); ?> | Ayah: <?php echo htmlspecialchars($h['ayah_no']); ?></td>
                            <td><?php echo htmlspecialchars($h['sabaq']); ?></td>
                            <td><?php echo htmlspecialchars($h['remarks']); ?></td>
                            <td><em><?php echo $h['admin_remarks'] ? htmlspecialchars($h['admin_remarks']) : '-'; ?></em></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center;">No lesson history available.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function togglePageInput() {
    var course = document.getElementById("courseSelect").value;
    var qaidaBox = document.getElementById("qaidaPageBox");
    var nazraBox = document.getElementById("nazraPageBox");

    if (course === "Qaida") {
        qaidaBox.style.display = "block";
        nazraBox.style.display = "none";
    } else {
        qaidaBox.style.display = "none";
        nazraBox.style.display = "block";
    }
}
window.onload = togglePageInput;
</script>

</body>
</html>