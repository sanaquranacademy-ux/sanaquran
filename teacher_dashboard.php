<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

$teacher_id = (int)$_SESSION['user_id'];
$today = date('Y-m-d');
$current_time = date('H:i:s');

// ایڈمن کی معلومات
$admin = $conn->query("SELECT id, name FROM users WHERE role = 'admin' LIMIT 1")->fetch_assoc();
$admin_id = (int)($admin['id'] ?? 1);

// طلبہ اور حاضری
$students = $conn->query("
    SELECT s.*, a.status as today_status 
    FROM students s 
    LEFT JOIN attendance a ON s.id = a.student_id AND a.date = '$today' 
    WHERE s.teacher_id = $teacher_id AND s.status = 'active' 
    ORDER BY s.class_time ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Portal - Sana Quran House</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { margin: 0; background: #f2f9f5; color: #1e293b; }
        .navbar { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 14px 35px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(5, 150, 105, 0.15); }
        .nav-brand h2 { margin: 0; font-size: 18px; font-weight: 700; }
        .nav-right { display: flex; align-items: center; gap: 10px; }
        .nav-pill { display: inline-flex; align-items: center; gap: 6px; text-decoration: none; padding: 7px 15px; border-radius: 50px; font-size: 12.5px; font-weight: 600; }
        .btn-website { background: #ffffff; color: #059669; }
        .user-tag { background: rgba(255, 255, 255, 0.18); color: #ffffff; padding: 6px 14px; border-radius: 50px; font-size: 12.5px; border: 1px solid rgba(255, 255, 255, 0.25); }
        .btn-logout { background: #ef4444; color: white; }

        .container { max-width: 1150px; margin: 35px auto; padding: 0 20px; }
        .card { background: #ffffff; padding: 28px; border-radius: 16px; box-shadow: 0 4px 20px rgba(16, 185, 129, 0.05); border: 1px solid #d1fae5; }
        .card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ecfdf5; padding-bottom: 14px; margin-bottom: 20px; }
        .card-header h3 { margin: 0; color: #065f46; font-size: 19px; font-weight: 700; }
        .date-badge { background: #ecfdf5; color: #059669; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; border: 1px solid #a7f3d0; }

        table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13.5px; }
        th { background: #f0fdf4; color: #047857; font-weight: 600; padding: 12px 16px; border-bottom: 1.5px solid #d1fae5; text-align: left; }
        td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; color: #334155; }
        .btn-classroom { background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; text-decoration: none; padding: 7px 16px; border-radius: 8px; font-weight: 600; font-size: 12.5px; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 11.5px; }
        .badge-present { background: #dcfce7; color: #15803d; }
        .badge-leave { background: #e0f2fe; color: #0369a1; }
        .badge-excused { background: #fef3c7; color: #b45309; }
        .badge-unexcused { background: #fee2e2; color: #b91c1c; }
        .badge-pending { background: #f1f5f9; color: #64748b; }
        .live-tag { background: #ef4444; color: white; padding: 2px 7px; border-radius: 12px; font-size: 10.5px; font-weight: 700; margin-left: 6px; }

        /* FB Chat Widget */
        .fb-chat-btn { position: fixed; bottom: 25px; right: 25px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border: none; border-radius: 50px; padding: 12px 22px; font-weight: 700; font-size: 13.5px; box-shadow: 0 8px 25px rgba(5, 150, 105, 0.35); cursor: pointer; display: flex; align-items: center; gap: 8px; z-index: 9999; }
        .fb-chat-box { position: fixed; bottom: 25px; right: 25px; width: 360px; height: 490px; background: #ffffff; border-radius: 16px; box-shadow: 0 12px 35px rgba(0,0,0,0.12); border: 1px solid #d1fae5; display: none; flex-direction: column; z-index: 10000; overflow: hidden; }
        .fb-chat-header { background: #ecfdf5; padding: 12px 18px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #a7f3d0; }
        .btn-mini-call { background: #10b981; color: white; border: none; border-radius: 20px; padding: 6px 14px; font-size: 11.5px; font-weight: 700; cursor: pointer; }
        .btn-mini-call:hover { background: #059669; }
        .fb-quick-bar { background: #f0fdf4; padding: 7px 12px; border-bottom: 1px solid #d1fae5; display: flex; gap: 6px; overflow-x: auto; }
        .fb-quick-btn { background: #ffffff; border: 1px solid #a7f3d0; color: #047857; font-size: 10.5px; padding: 4px 9px; border-radius: 12px; cursor: pointer; white-space: nowrap; font-weight: 600; }
        .fb-messages { flex: 1; padding: 14px; overflow-y: auto; display: flex; flex-direction: column; gap: 9px; background: #fafdfc; }
        .fb-msg { max-width: 75%; padding: 9px 13px; border-radius: 14px; font-size: 12.5px; line-height: 1.4; word-wrap: break-word; }
        .fb-msg-me { align-self: flex-end; background: #d1fae5; color: #065f46; border-bottom-right-radius: 2px; }
        .fb-msg-other { align-self: flex-start; background: #f1f5f9; color: #334155; border-bottom-left-radius: 2px; }
        .fb-msg-alert { align-self: center; background: #fef3c7; color: #92400e; font-size: 11px; padding: 4px 12px; border-radius: 12px; text-align: center; }
        .fb-msg-time { display: block; font-size: 9.5px; color: #64748b; text-align: right; margin-top: 3px; }
        .fb-msg-me .fb-msg-time { color: #047857; }

        .fb-input-area { padding: 10px 14px; background: #ffffff; border-top: 1px solid #e2e8f0; display: flex; align-items: center; gap: 8px; }
        .fb-input-area input { flex: 1; padding: 9px 14px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 20px; outline: none; font-size: 12.5px; }
        .fb-btn-send { background: #10b981; color: white; border: none; padding: 8px 16px; border-radius: 20px; font-weight: 700; font-size: 12px; cursor: pointer; }
        .fb-btn-mic { background: #ef4444; color: white; border: none; width: 34px; height: 34px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; }
    </style>
</head>
<body>

<div class="navbar">
    <div class="nav-brand"><h2>✨ Sana Quran House — Teacher Portal</h2></div>
    <div class="nav-right">
        <a href="index.php" class="nav-pill btn-website">🌐 Main Website</a>
        <div class="user-tag">👨‍🏫 <?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
        <a href="logout.php" class="nav-pill btn-logout">Logout</a>
    </div>
</div>

<div class="container">
    <div class="card">
        <div class="card-header">
            <h3>Assigned Students</h3>
            <span class="date-badge">📅 <?php echo date('l, d F Y'); ?></span>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Course</th>
                    <th>Class Timing</th>
                    <th>Today's Attendance</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($students->num_rows > 0): ?>
                    <?php while($s = $students->fetch_assoc()): 
                        $class_start = strtotime($s['class_time']);
                        $class_end = strtotime($s['class_time'] . ' +45 minutes');
                        $now = strtotime($current_time);
                        $is_live = ($now >= $class_start && $now <= $class_end);
                    ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($s['name']); ?></strong><?php if ($is_live): ?><span class="live-tag">● LIVE</span><?php endif; ?></td>
                            <td><?php echo htmlspecialchars($s['course']); ?></td>
                            <td><strong><?php echo date('h:i A', strtotime($s['class_time'])); ?></strong></td>
                            <td><span class="badge badge-<?php echo $s['today_status'] ? $s['today_status'] : 'pending'; ?>"><?php echo strtoupper($s['today_status'] ? $s['today_status'] : 'PENDING'); ?></span></td>
                            <td><a href="student_detail.php?id=<?php echo $s['id']; ?>" class="btn-classroom">Enter Classroom →</a></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:30px;">No students assigned to you yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Incoming Call Popup Modal (ٹیچر سکرین پر پاپ اپ) -->
<div id="incomingCallModal" style="display:none; position:fixed; top:20px; right:20px; z-index:999999; background:#ffffff; border:2px solid #10b981; padding:22px; border-radius:16px; box-shadow:0 10px 35px rgba(0,0,0,0.25); width:320px; text-align:center;">
    <div style="font-size:35px; animation: pulse 1s infinite;">📞</div>
    <h4 style="margin:8px 0 4px 0; color:#065f46; font-size:16px;">Incoming Call from Admin...</h4>
    <p id="callerNameText" style="margin:0 0 15px 0; font-weight:700; font-size:13.5px; color:#334155;">Admin Office is calling</p>
    <div style="display:flex; justify-content:center; gap:10px;">
        <button onclick="acceptIncomingCall()" style="background:#10b981; color:white; border:none; padding:9px 18px; border-radius:8px; font-weight:700; cursor:pointer;">Accept (کال اٹھائیں)</button>
        <button onclick="rejectIncomingCall()" style="background:#ef4444; color:white; border:none; padding:9px 18px; border-radius:8px; font-weight:700; cursor:pointer;">Decline (کاٹیں)</button>
    </div>
</div>

<!-- FB Style Chat Popup -->
<button class="fb-chat-btn" id="fbOpenBtn" onclick="toggleFbChat()">💬 Admin Chat</button>

<div class="fb-chat-box" id="fbChatBox">
    <div class="fb-chat-header">
        <div>
            <h4 style="margin:0; color:#065f46;">Admin Desk</h4>
            <small style="font-size:11px; color:#10b981; font-weight:600;">● Online Support</small>
        </div>
        <div style="display:flex; gap:8px; align-items:center;">
            <button onclick="triggerCallToAdmin()" class="btn-mini-call">📞 Call Admin</button>
            <button onclick="toggleFbChat()" style="background:none; border:none; font-size:18px; cursor:pointer; color:#64748b;">✕</button>
        </div>
    </div>

    <div class="fb-quick-bar">
        <button class="fb-quick-btn" onclick="sendQuickAlert('⚠️ Urgent: Student is Offline')">🔴 Offline</button>
        <button class="fb-quick-btn" onclick="sendQuickAlert('⏳ Student joined late')">⏰ Late</button>
        <button class="fb-quick-btn" onclick="sendQuickAlert('🔊 Mic/Audio Issue')">🎤 Mic Issue</button>
    </div>

    <div class="fb-messages" id="fbMsgArea"></div>

    <div class="fb-input-area">
        <button class="fb-btn-mic" id="recordBtn" onclick="toggleVoiceRecording()" title="Record Voice">🎙️</button>
        <input type="text" id="fbMsgInput" placeholder="Type a message..." onkeypress="if(event.key === 'Enter') sendTextMsg()">
        <button class="fb-btn-send" onclick="sendTextMsg()">Send</button>
    </div>
</div>

<script>
const adminId = <?php echo $admin_id; ?>;
let mediaRecorder, audioChunks = [], isRecording = false;
let currentCallId = null;
let currentCallerId = null;
let audioCtx = null;
let beepInterval = null;
const modal = document.getElementById('incomingCallModal');

function toggleFbChat() {
    const box = document.getElementById('fbChatBox');
    const btn = document.getElementById('fbOpenBtn');
    if (box.style.display === 'flex') {
        box.style.display = 'none';
        btn.style.display = 'flex';
    } else {
        box.style.display = 'flex';
        btn.style.display = 'none';
        fetchMessages();
    }
}

// کال شروع کرنا
function triggerCallToAdmin() {
    sendQuickAlert('📞 Teacher initiated an In-Portal Live Audio Call. Ringing Admin...');
    
    const fd = new FormData();
    fd.append('action', 'start_call');
    fd.append('receiver_id', adminId);

    fetch('call_handler.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            window.open(`live_call.php?target_id=${adminId}&caller=1`, '_blank');
        });
}

// بیپ رنگ ٹون سسٹم
function playWebRingtone() {
    try {
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') audioCtx.resume();

        if (beepInterval) return;
        beepInterval = setInterval(() => {
            try {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(800, audioCtx.currentTime);
                gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.4);
            } catch(e){}
        }, 1000);
    } catch(err){}
}

function stopWebRingtone() {
    if (beepInterval) {
        clearInterval(beepInterval);
        beepInterval = null;
    }
}

// انکمنگ کال لائیو چیک کرنا (ٹیچر پینل کے لیے)
function checkIncomingCall() {
    fetch('call_handler.php?action=check_incoming')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'incoming') {
                currentCallId = data.call_id;
                currentCallerId = data.caller_id;
                document.getElementById('callerNameText').innerText = (data.caller_name || 'Admin') + ' is calling you...';
                modal.style.display = 'block';
                playWebRingtone();
            } else if (modal.style.display === 'block' && data.status === 'idle') {
                stopWebRingtone();
                modal.style.display = 'none';
            }
        });
}

function acceptIncomingCall() {
    stopWebRingtone();
    modal.style.display = 'none';
    const fd = new FormData();
    fd.append('action', 'respond_call');
    fd.append('call_id', currentCallId);
    fd.append('response', 'accepted');

    fetch('call_handler.php', { method: 'POST', body: fd }).then(() => {
        window.open(`live_call.php?target_id=${currentCallerId}`, '_blank');
    });
}

function rejectIncomingCall() {
    stopWebRingtone();
    modal.style.display = 'none';
    const fd = new FormData();
    fd.append('action', 'respond_call');
    fd.append('call_id', currentCallId);
    fd.append('response', 'rejected');
    fetch('call_handler.php', { method: 'POST', body: fd });
}

setInterval(checkIncomingCall, 2000);

// چیٹ میسجز سنک
function fetchMessages() {
    fetch(`chat_handler.php?action=fetch_messages&other_id=${adminId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const area = document.getElementById('fbMsgArea');
                let html = '';
                data.messages.forEach(m => {
                    const isMe = (m.sender_id == data.my_id);
                    const timeTag = `<span class="fb-msg-time">${m.msg_time || ''}</span>`;
                    
                    if (m.message_type === 'alert') {
                        html += `<div class="fb-msg-alert">${m.message_text} <br><small style="font-size:9px; opacity:0.8;">${m.msg_time || ''}</small></div>`;
                    } else if (m.message_type === 'voice') {
                        html += `<div class="fb-msg ${isMe ? 'fb-msg-me' : 'fb-msg-other'}"><strong>${isMe ? 'You' : m.sender_name}</strong><br><audio controls src="uploads/voice_notes/${m.voice_file}" style="margin-top:4px; max-width:180px;"></audio>${timeTag}</div>`;
                    } else {
                        html += `<div class="fb-msg ${isMe ? 'fb-msg-me' : 'fb-msg-other'}"><strong>${isMe ? 'You' : m.sender_name}</strong><br>${m.message_text}${timeTag}</div>`;
                    }
                });
                area.innerHTML = html;
                area.scrollTop = area.scrollHeight;
            }
        });
}

setInterval(() => {
    if (document.getElementById('fbChatBox').style.display === 'flex') fetchMessages();
}, 3000);

function sendTextMsg() {
    const text = document.getElementById('fbMsgInput').value.trim();
    if (!text) return;
    const fd = new FormData();
    fd.append('action', 'send_text');
    fd.append('receiver_id', adminId);
    fd.append('message', text);
    fetch('chat_handler.php', { method: 'POST', body: fd }).then(() => {
        document.getElementById('fbMsgInput').value = '';
        fetchMessages();
    });
}

function sendQuickAlert(msg) {
    const fd = new FormData();
    fd.append('action', 'send_text');
    fd.append('receiver_id', adminId);
    fd.append('message', msg);
    fd.append('type', 'alert');
    fetch('chat_handler.php', { method: 'POST', body: fd }).then(() => fetchMessages());
}

async function toggleVoiceRecording() {
    const btn = document.getElementById('recordBtn');
    if (!isRecording) {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            mediaRecorder = new MediaRecorder(stream);
            audioChunks = [];
            mediaRecorder.ondataavailable = e => audioChunks.push(e.data);
            mediaRecorder.onstop = () => {
                const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                const fd = new FormData();
                fd.append('action', 'send_voice');
                fd.append('receiver_id', adminId);
                fd.append('voice_data', audioBlob);
                fetch('chat_handler.php', { method: 'POST', body: fd }).then(() => fetchMessages());
            };
            mediaRecorder.start();
            isRecording = true;
            btn.innerText = '⏹️';
        } catch (err) { alert('Mic permission blocked!'); }
    } else {
        mediaRecorder.stop();
        isRecording = false;
        btn.innerText = '🎙️';
    }
}
</script>

</body>
</html>