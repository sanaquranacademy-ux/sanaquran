<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$msg = '';
$err = '';
$view = $_GET['view'] ?? 'overview';
$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

// 1. نیا ٹیچر / اسٹاف رجسٹر کرنا
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $name = trim($_POST['name']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    if (!empty($name) && !empty($username) && !empty($password)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $username, $hashed, $role);
        if ($stmt->execute()) {
            $msg = ucfirst($role) . " added successfully!";
        } else {
            $err = "Username already exists!";
        }
    }
}

// 2. نیا اسٹوڈنٹ شامل کرنا
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_student') {
    $name = trim($_POST['student_name']);
    $guardian = trim($_POST['guardian_name']);
    $phone = trim($_POST['phone']);
    $country = trim($_POST['country']);
    $course = $_POST['course'];
    $fee = !empty($_POST['fee_amount']) ? (float)$_POST['fee_amount'] : 0.00;
    $joining_date = !empty($_POST['joining_date']) ? $_POST['joining_date'] : date('Y-m-d');
    $p_pass = !empty($_POST['parent_password']) ? trim($_POST['parent_password']) : '123456';
    $class_time = $_POST['class_time'];
    $t_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;

    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT INTO students (name, guardian_name, phone, country, course, fee_amount, joining_date, last_fee_date, parent_password, class_time, teacher_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssdssssi", $name, $guardian, $phone, $country, $course, $fee, $joining_date, $joining_date, $p_pass, $class_time, $t_id);
        if ($stmt->execute()) {
            $new_st_id = $stmt->insert_id;
            if ($t_id) {
                $conn->query("INSERT INTO student_teacher_history (student_id, teacher_id) VALUES ($new_st_id, $t_id)");
            }
            $msg = "Student registered successfully!";
        }
    }
}

// 3. فیس وصولی لاگ کرنا
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'pay_fee') {
    $st_id = (int)$_POST['student_id'];
    $p_amount = (float)$_POST['amount'];
    $p_date = $_POST['payment_date'];

    $conn->query("INSERT INTO fee_payments (student_id, amount, payment_date) VALUES ($st_id, $p_amount, '$p_date')");
    $conn->query("UPDATE students SET last_fee_date = '$p_date' WHERE id = $st_id");
    $msg = "Fee payment marked successfully!";
}

$total_teachers = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'teacher'")->fetch_assoc()['total'];
$total_students = $conn->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'")->fetch_assoc()['total'];
$total_staff = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'staff'")->fetch_assoc()['total'];

$due_students = $conn->query("
    SELECT * FROM students 
    WHERE status = 'active' 
    AND (last_fee_date <= DATE_SUB(CURDATE(), INTERVAL 30 DAY) OR last_fee_date IS NULL)
");
$due_count = $due_students->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Portal - Sana Online Quran House</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { margin: 0; background: #f2f9f5; color: #1e293b; }
        
        .navbar { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 14px 35px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(5, 150, 105, 0.15); }
        .nav-right { display: flex; align-items: center; gap: 10px; }
        .nav-pill { display: inline-flex; align-items: center; gap: 6px; text-decoration: none; padding: 7px 15px; border-radius: 50px; font-size: 12.5px; font-weight: 600; }
        .btn-website { background: #ffffff; color: #059669; }
        .btn-chat-desk { background: #fef3c7; color: #92400e; }
        .btn-logout { background: #ef4444; color: white; }
        
        .container { max-width: 1250px; margin: 30px auto; padding: 0 20px; }
        
        .fee-alert-box { background: #fffbeb; border-left: 5px solid #f59e0b; padding: 16px 20px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #fde68a; }
        .due-list { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
        .due-tag { background: #fff; border: 1px solid #fef3c7; padding: 10px 14px; border-radius: 8px; font-size: 13px; display: flex; justify-content: space-between; align-items: center; }
        .btn-wa-fee { background: #10b981; color: white; text-decoration: none; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; }

        .nav-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 25px; }
        .nav-card { background: white; padding: 22px; border-radius: 14px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.05); text-decoration: none; color: inherit; display: block; border: 1px solid #d1fae5; text-align: center; transition: 0.2s; }
        .nav-card:hover { transform: translateY(-2px); border-color: #10b981; }
        .nav-card.active { background: #ecfdf5; border-color: #10b981; }
        .nav-card h2 { margin: 0; font-size: 28px; color: #065f46; }
        .nav-card p { margin: 6px 0 0 0; color: #64748b; font-weight: 700; font-size: 12.5px; }

        .card { background: white; padding: 28px; border-radius: 14px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.05); border: 1px solid #d1fae5; margin-bottom: 25px; }
        .card h3 { margin-top: 0; color: #065f46; border-bottom: 2px solid #ecfdf5; padding-bottom: 12px; }
        
        .form-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px; }
        .form-grid .form-group { display: flex; flex-direction: column; }
        .form-grid label { font-size: 12px; font-weight: 600; margin-bottom: 4px; color: #334155; }
        .form-grid input, .form-grid select { padding: 9px; border: 1px solid #cbd5e1; border-radius: 6px; background: #f8fafc; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13.5px; }
        th, td { padding: 12px; border-bottom: 1px solid #f1f5f9; text-align: left; }
        th { background: #f0fdf4; color: #047857; font-weight: 600; }
        
        .btn-action { display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; background: #10b981; color: white; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 600; }
        .btn-call-ind { background: #059669; }
        .btn-chat-ind { background: #0284c7; }
        .alert-success { background: #dcfce7; color: #15803d; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-weight: 600; }
    </style>
</head>
<body>

<div class="navbar">
    <h2>✨ Sana Quran House — Admin Portal</h2>
    <div class="nav-right">
        <!-- ٹیچر چیٹ و کال سینٹر -->
        <a href="admin_chat.php" class="nav-pill btn-chat-desk">💬 Open Teacher Chat Desk</a>
        <a href="index.php" class="nav-pill btn-website">🌐 Main Website</a>
        <span style="font-size:13px; margin-left:5px;">Admin: <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></span>
        <a href="logout.php" class="nav-pill btn-logout">Logout</a>
    </div>
</div>

<div class="container">
    <?php if ($msg): ?><div class="alert-success"><?php echo $msg; ?></div><?php endif; ?>

    <?php if ($due_count > 0): ?>
        <div class="fee-alert-box">
            <h4 style="margin:0; color:#b45309;">🔔 Fee Due Alerts (<?php echo $due_count; ?> Pending)</h4>
            <div class="due-list">
                <?php 
                while($due = $due_students->fetch_assoc()): 
                    $clean_phone = preg_replace('/[^0-9]/', '', $due['phone']);
                    $fee_msg = urlencode("السلام علیکم،\nمحترم والدین، طالب علم *" . $due['name'] . "* کی ماہانہ فیس واجب الادا ہے۔ برائے مہربانی فیس جمع کروائیں۔ شکریہ۔");
                ?>
                    <div class="due-tag">
                        <div><strong>👤 <?php echo htmlspecialchars($due['name']); ?></strong> | Amount: <strong>$<?php echo htmlspecialchars($due['fee_amount']); ?></strong></div>
                        <div style="display:flex; gap:8px;">
                            <a href="https://api.whatsapp.com/send?phone=<?php echo $clean_phone; ?>&text=<?php echo $fee_msg; ?>" target="_blank" class="btn-wa-fee">💬 WhatsApp Reminder</a>
                            <form method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="pay_fee">
                                <input type="hidden" name="student_id" value="<?php echo $due['id']; ?>">
                                <input type="hidden" name="amount" value="<?php echo $due['fee_amount']; ?>">
                                <input type="hidden" name="payment_date" value="<?php echo date('Y-m-d'); ?>">
                                <button type="submit" style="background:#059669; color:white; border:none; padding:6px 12px; border-radius:6px; font-weight:600; cursor:pointer;">Mark Paid</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="nav-cards">
        <a href="admin_dashboard.php?view=teachers" class="nav-card <?php echo ($view == 'teachers' || $view == 'teacher_students') ? 'active' : ''; ?>">
            <h2><?php echo $total_teachers; ?></h2>
            <p>👨‍🏫 TEACHERS LIST</p>
        </a>
        <a href="admin_dashboard.php?view=students" class="nav-card <?php echo ($view == 'students') ? 'active' : ''; ?>">
            <h2><?php echo $total_students; ?></h2>
            <p>🎓 ALL STUDENTS LIST</p>
        </a>
        <a href="admin_dashboard.php?view=staff" class="nav-card <?php echo ($view == 'staff') ? 'active' : ''; ?>">
            <h2><?php echo $total_staff; ?></h2>
            <p>👥 OTHER STAFF</p>
        </a>
        <a href="admin_teacher_billing.php" class="nav-card">
            <h2>💰</h2>
            <p>TEACHER BILLING & CLASSES</p>
        </a>
    </div>

    <?php if ($view == 'teachers'): ?>
        <div class="card">
            <h3>Registered Teachers</h3>
            <form method="POST" style="display:flex; gap:10px; margin-bottom:20px;">
                <input type="hidden" name="action" value="add_user">
                <input type="hidden" name="role" value="teacher">
                <input type="text" name="name" placeholder="Teacher Name" required style="padding:9px; border:1px solid #cbd5e1; border-radius:6px;">
                <input type="text" name="username" placeholder="Login Username" required style="padding:9px; border:1px solid #cbd5e1; border-radius:6px;">
                <input type="password" name="password" placeholder="Password" required style="padding:9px; border:1px solid #cbd5e1; border-radius:6px;">
                <button type="submit" class="btn-action" style="border:none; cursor:pointer;">+ Add Teacher</button>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>Teacher Name</th>
                        <th>Username</th>
                        <th>Assigned Students</th>
                        <th>Actions (Direct Call / Chat)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $t_list = $conn->query("SELECT u.*, COUNT(s.id) as st_count FROM users u LEFT JOIN students s ON u.id = s.teacher_id AND s.status = 'active' WHERE u.role = 'teacher' GROUP BY u.id");
                    while($t = $t_list->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($t['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($t['username']); ?></td>
                            <td><span style="background:#e0f2fe; color:#0369a1; padding:3px 8px; border-radius:4px; font-weight:bold;"><?php echo $t['st_count']; ?> Students</span></td>
                            <td>
                                <!-- مخصوص ٹیچر کے لیے انفرادی کال، چیٹ اور اسٹوڈنٹس دیکھنے کا بٹن -->
                                <a href="live_call.php?target_id=<?php echo $t['id']; ?>" target="_blank" class="btn-action btn-call-ind">📞 Call</a>
                                <a href="admin_chat.php?teacher_id=<?php echo $t['id']; ?>" class="btn-action btn-chat-ind">💬 Chat</a>
                                <a href="admin_dashboard.php?view=teacher_students&teacher_id=<?php echo $t['id']; ?>" class="btn-action" style="background:#475569;">Students →</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($view == 'teacher_students' && $teacher_id > 0): 
        $curr_t = $conn->query("SELECT name FROM users WHERE id = $teacher_id")->fetch_assoc();
        $t_students = $conn->query("SELECT * FROM students WHERE teacher_id = $teacher_id AND status = 'active' ORDER BY class_time ASC");
    ?>
        <div class="card">
            <h3>Students Assigned to: <span style="color:#059669;"><?php echo htmlspecialchars($curr_t['name']); ?></span></h3>
            <p><a href="admin_dashboard.php?view=teachers" style="color:#059669; font-weight:600;">← Back to All Teachers</a></p>
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Country</th>
                        <th>Course</th>
                        <th>Class Timing</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($t_students->num_rows > 0): ?>
                        <?php while($ts = $t_students->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($ts['name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($ts['country']); ?></td>
                                <td><?php echo htmlspecialchars($ts['course']); ?></td>
                                <td><?php echo $ts['class_time'] ? date('h:i A', strtotime($ts['class_time'])) : 'N/A'; ?></td>
                                <td><a href="admin_student_view.php?id=<?php echo $ts['id']; ?>" class="btn-action">📊 Complete Report</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:20px;">No students assigned yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($view == 'students'): ?>
        <div class="card">
            <h3>Register New Student</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_student">
                <div class="form-grid">
                    <div class="form-group"><label>Student Name</label><input type="text" name="student_name" required></div>
                    <div class="form-group"><label>Father / Guardian Name</label><input type="text" name="guardian_name"></div>
                    <div class="form-group"><label>WhatsApp Phone</label><input type="text" name="phone" placeholder="971527194855" required></div>
                    <div class="form-group"><label>Parent PIN</label><input type="text" name="parent_password" placeholder="123456" required></div>
                    <div class="form-group"><label>Country</label><input type="text" name="country" placeholder="UAE, UK, USA"></div>
                    <div class="form-group">
                        <label>Course</label>
                        <select name="course">
                            <option value="Qaida">Qaida</option>
                            <option value="Nazra">Nazra</option>
                            <option value="Memorization Quran">Memorization Quran</option>
                            <option value="Tafseer">Tafseer</option>
                            <option value="Islamic Studies">Islamic Studies</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Monthly Fee Amount ($)</label><input type="number" step="0.01" name="fee_amount" placeholder="50"></div>
                    <div class="form-group"><label>Joining Date</label><input type="date" name="joining_date" value="<?php echo date('Y-m-d'); ?>"></div>
                    <div class="form-group"><label>Class Timing</label><input type="time" name="class_time" required></div>
                    <div class="form-group">
                        <label>Assign Teacher</label>
                        <select name="teacher_id">
                            <option value="">-- Choose Teacher --</option>
                            <?php 
                            $teachers_drop = $conn->query("SELECT id, name FROM users WHERE role = 'teacher'");
                            while($tr = $teachers_drop->fetch_assoc()): ?>
                                <option value="<?php echo $tr['id']; ?>"><?php echo htmlspecialchars($tr['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-action" style="border:none; cursor:pointer; padding:10px 22px;">+ Enroll Student</button>
            </form>
        </div>

        <div class="card">
            <h3>All Registered Students</h3>
            <table>
                <thead>
                    <tr>
                        <th>Student Name</th>
                        <th>Parent WhatsApp</th>
                        <th>PIN</th>
                        <th>Course</th>
                        <th>Fee</th>
                        <th>Current Teacher</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $all_st = $conn->query("SELECT s.*, u.name as teacher_name FROM students s LEFT JOIN users u ON s.teacher_id = u.id ORDER BY s.id DESC");
                    while($as = $all_st->fetch_assoc()): 
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($as['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($as['phone']); ?></td>
                            <td><code><?php echo htmlspecialchars($as['parent_password'] ?? '123456'); ?></code></td>
                            <td><?php echo htmlspecialchars($as['course']); ?></td>
                            <td><strong>$<?php echo htmlspecialchars($as['fee_amount']); ?></strong></td>
                            <td><span style="background:#ecfdf5; color:#065f46; padding:3px 8px; border-radius:4px; font-weight:600;"><?php echo $as['teacher_name'] ? htmlspecialchars($as['teacher_name']) : 'Unassigned'; ?></span></td>
                            <td><a href="admin_student_view.php?id=<?php echo $as['id']; ?>" class="btn-action">📊 Report</a></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($view == 'staff'): ?>
        <div class="card">
            <h3>Staff Management</h3>
            <form method="POST" style="display:flex; gap:10px; margin-bottom:15px;">
                <input type="hidden" name="action" value="add_user">
                <input type="hidden" name="role" value="staff">
                <input type="text" name="name" placeholder="Staff Name" required style="padding:9px; border:1px solid #cbd5e1; border-radius:6px;">
                <input type="text" name="username" placeholder="Username" required style="padding:9px; border:1px solid #cbd5e1; border-radius:6px;">
                <input type="password" name="password" placeholder="Password" required style="padding:9px; border:1px solid #cbd5e1; border-radius:6px;">
                <button type="submit" class="btn-action" style="border:none; cursor:pointer;">+ Add Staff</button>
            </form>
        </div>

    <?php else: ?>
        <div class="card">
            <h3>Welcome to Academy Administration</h3>
            <p>Select Teachers, Students, Staff, or Teacher Billing from the top navigation cards to manage operations.</p>
        </div>
    <?php endif; ?>

</div>

<!-- Incoming Call Ringtone & Popup Modal -->
<audio id="ringtoneSound" loop src="https://assets.mixkit.co/active_storage/sfx/1359/1359-preview.mp3" preload="auto"></audio>

<div id="incomingCallModal" style="display:none; position:fixed; top:20px; right:20px; z-index:99999; background:#ffffff; border:2px solid #10b981; padding:20px; border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,0.15); width:320px; text-align:center;">
    <div style="font-size:32px;">📞</div>
    <h4 style="margin:8px 0 4px 0; color:#065f46;">Incoming Call...</h4>
    <p id="callerNameText" style="margin:0 0 15px 0; font-weight:700; font-size:13.5px; color:#334155;"></p>
    <div style="display:flex; justify-content:center; gap:10px;">
        <button onclick="acceptIncomingCall()" style="background:#10b981; color:white; border:none; padding:8px 16px; border-radius:6px; font-weight:700; cursor:pointer;">Accept</button>
        <button onclick="rejectIncomingCall()" style="background:#ef4444; color:white; border:none; padding:8px 16px; border-radius:6px; font-weight:700; cursor:pointer;">Decline</button>
    </div>
</div>

<script>
let currentCallId = null;
let currentCallerId = null;
const ringtone = document.getElementById('ringtoneSound');
const modal = document.getElementById('incomingCallModal');

function checkIncomingCall() {
    fetch('call_handler.php?action=check_incoming')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'incoming') {
                currentCallId = data.call_id;
                currentCallerId = data.caller_id;
                document.getElementById('callerNameText').innerText = data.caller_name + ' is calling...';
                modal.style.display = 'block';
                ringtone.play().catch(e => console.log('Audio waiting for interaction'));
            } else if (modal.style.display === 'block' && data.status === 'idle') {
                stopRingtone();
            }
        });
}

function stopRingtone() {
    ringtone.pause();
    ringtone.currentTime = 0;
    modal.style.display = 'none';
}

function acceptIncomingCall() {
    stopRingtone();
    const fd = new FormData();
    fd.append('action', 'respond_call');
    fd.append('call_id', currentCallId);
    fd.append('response', 'accepted');

    fetch('call_handler.php', { method: 'POST', body: fd }).then(() => {
        window.open(`live_call.php?target_id=${currentCallerId}`, '_blank');
    });
}

function rejectIncomingCall() {
    stopRingtone();
    const fd = new FormData();
    fd.append('action', 'respond_call');
    fd.append('call_id', currentCallId);
    fd.append('response', 'rejected');
    fetch('call_handler.php', { method: 'POST', body: fd });
}

setInterval(checkIncomingCall, 2000);
</script>

</body>
</html>