<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id = (int)$_SESSION['user_id'];
$teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;

$teachers = $conn->query("SELECT id, name, username FROM users WHERE role = 'teacher'");

$active_teacher = null;
if ($teacher_id > 0) {
    $active_teacher = $conn->query("SELECT id, name FROM users WHERE id = $teacher_id AND role = 'teacher'")->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Teacher Desk & Call - Sana Quran House</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { margin: 0; background: #f2f9f5; color: #1e293b; }
        .navbar { background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 12px 30px; color: white; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 15px rgba(5, 150, 105, 0.15); }
        .btn-back { background: rgba(255,255,255,0.95); color: #059669; text-decoration: none; padding: 6px 14px; border-radius: 20px; font-weight: 700; font-size: 12.5px; }
        
        .chat-layout { max-width: 1100px; margin: 25px auto; display: grid; grid-template-columns: 300px 1fr; gap: 20px; height: 82vh; }
        
        .teachers-sidebar { background: white; border-radius: 14px; border: 1px solid #d1fae5; overflow-y: auto; display: flex; flex-direction: column; }
        .sidebar-header { padding: 15px; border-bottom: 1px solid #ecfdf5; background: #f0fdf4; font-weight: 700; color: #065f46; font-size: 14px; }
        .teacher-item { padding: 12px 15px; border-bottom: 1px solid #f1f5f9; text-decoration: none; color: #334155; display: flex; justify-content: space-between; align-items: center; transition: 0.2s; }
        .teacher-item:hover { background: #f8fafc; }
        .teacher-item.active { background: #ecfdf5; border-left: 4px solid #10b981; font-weight: 700; color: #065f46; }
        
        .chat-main { background: white; border-radius: 14px; border: 1px solid #d1fae5; display: flex; flex-direction: column; overflow: hidden; }
        .chat-header { background: #ecfdf5; padding: 14px 20px; border-bottom: 1px solid #a7f3d0; display: flex; justify-content: space-between; align-items: center; }
        .chat-header h3 { margin: 0; color: #065f46; font-size: 16px; }
        .btn-call-teacher { background: #10b981; color: white; border: none; padding: 7px 16px; border-radius: 20px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; cursor: pointer; }
        .btn-call-teacher:hover { background: #059669; }

        .messages-area { flex: 1; padding: 16px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; background: #fafdfc; }
        .msg-bubble { max-width: 70%; padding: 8px 14px; border-radius: 12px; font-size: 13px; line-height: 1.4; word-wrap: break-word; }
        .msg-admin { align-self: flex-end; background: #d1fae5; color: #065f46; border-bottom-right-radius: 2px; }
        .msg-teacher { align-self: flex-start; background: #f1f5f9; color: #1e293b; border-bottom-left-radius: 2px; }
        .msg-alert { align-self: center; background: #fef3c7; color: #92400e; font-size: 11.5px; padding: 6px 16px; border-radius: 15px; font-weight: 600; text-align: center; }

        .msg-time { display: block; font-size: 10px; color: #64748b; text-align: right; margin-top: 4px; font-weight: 500; }
        .msg-admin .msg-time { color: #059669; }

        .chat-input-bar { padding: 12px 18px; border-top: 1px solid #e2e8f0; display: flex; align-items: center; gap: 10px; background: white; }
        .chat-input-bar input { flex: 1; padding: 10px 16px; border: 1px solid #cbd5e1; border-radius: 25px; outline: none; font-size: 13px; background: #f8fafc; }
        .btn-send { background: #10b981; color: white; border: none; padding: 9px 20px; border-radius: 20px; font-weight: 700; font-size: 12.5px; cursor: pointer; }
    </style>
</head>
<body>

<div class="navbar">
    <h3 style="margin:0; font-size:17px;">✨ Admin Communication Desk</h3>
    <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
</div>

<div class="chat-layout">
    <div class="teachers-sidebar">
        <div class="sidebar-header">👨‍🏫 Registered Teachers</div>
        <?php while($t = $teachers->fetch_assoc()): ?>
            <a href="admin_chat.php?teacher_id=<?php echo $t['id']; ?>" class="teacher-item <?php echo ($teacher_id == $t['id']) ? 'active' : ''; ?>">
                <span><?php echo htmlspecialchars($t['name']); ?></span>
                <span style="font-size:11px; color:#64748b;">Chat →</span>
            </a>
        <?php endwhile; ?>
    </div>

    <div class="chat-main">
        <?php if ($active_teacher): ?>
            <div class="chat-header">
                <div>
                    <h3>Chat with: <?php echo htmlspecialchars($active_teacher['name']); ?></h3>
                    <small style="color:#059669; font-weight:600;">● Online Channel</small>
                </div>
                <button onclick="triggerCallToTeacher()" class="btn-call-teacher">📞 Call <?php echo htmlspecialchars($active_teacher['name']); ?></button>
            </div>

            <div class="messages-area" id="msgArea"></div>

            <div class="chat-input-bar">
                <input type="text" id="adminMsgInput" placeholder="Type reply to teacher..." onkeypress="if(event.key === 'Enter') sendAdminMsg()">
                <button class="btn-send" onclick="sendAdminMsg()">Send</button>
            </div>
        <?php else: ?>
            <div style="flex:1; display:flex; justify-content:center; align-items:center; color:#94a3b8; font-size:14px;">
                👈 Please select a teacher from the left sidebar to start chat or direct call.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Incoming Call Popup Modal -->
<div id="incomingCallModal" style="display:none; position:fixed; top:20px; right:20px; z-index:99999; background:#ffffff; border:2px solid #10b981; padding:20px; border-radius:14px; box-shadow:0 10px 30px rgba(0,0,0,0.25); width:320px; text-align:center;">
    <div style="font-size:32px;">📞</div>
    <h4 style="margin:8px 0 4px 0; color:#065f46;">Incoming Call...</h4>
    <p id="callerNameText" style="margin:0 0 15px 0; font-weight:700; font-size:13.5px; color:#334155;"></p>
    <div style="display:flex; justify-content:center; gap:10px;">
        <button onclick="acceptIncomingCall()" style="background:#10b981; color:white; border:none; padding:8px 16px; border-radius:6px; font-weight:700; cursor:pointer;">Accept (کال اٹھائیں)</button>
        <button onclick="rejectIncomingCall()" style="background:#ef4444; color:white; border:none; padding:8px 16px; border-radius:6px; font-weight:700; cursor:pointer;">Decline (کاٹیں)</button>
    </div>
</div>

<script>
const targetTeacherId = <?php echo $teacher_id; ?>;
let currentCallId = null;
let currentCallerId = null;
let audioCtx = null;
let beepInterval = null;
const modal = document.getElementById('incomingCallModal');

// ایڈمن سے ٹیچر کو کال بھیجنا
function triggerCallToTeacher() {
    if (targetTeacherId <= 0) return;

    const fd = new FormData();
    fd.append('action', 'start_call');
    fd.append('receiver_id', targetTeacherId);

    fetch('call_handler.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(data => {
            window.open(`live_call.php?target_id=${targetTeacherId}&caller=1`, '_blank');
        });
}

function fetchMessages() {
    if (targetTeacherId <= 0) return;
    fetch(`chat_handler.php?action=fetch_messages&other_id=${targetTeacherId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const area = document.getElementById('msgArea');
                let html = '';
                data.messages.forEach(m => {
                    const isMe = (m.sender_id == data.my_id);
                    const timeString = m.msg_time ? m.msg_time : '';
                    const dateString = m.msg_date ? m.msg_date : '';
                    const timeTag = `<span class="msg-time">${dateString ? dateString + ', ' : ''}${timeString}</span>`;
                    
                    if (m.message_type === 'alert') {
                        html += `<div class="msg-bubble msg-alert">${m.message_text} <br><small style="font-size:9.5px; opacity:0.85;">${timeString}</small></div>`;
                    } else if (m.message_type === 'voice') {
                        html += `
                            <div class="msg-bubble ${isMe ? 'msg-admin' : 'msg-teacher'}">
                                <strong>${isMe ? 'You (Voice)' : m.sender_name}</strong><br>
                                <audio controls src="uploads/voice_notes/${m.voice_file}" style="margin-top:5px; max-width:200px;"></audio>
                                ${timeTag}
                            </div>`;
                    } else {
                        html += `
                            <div class="msg-bubble ${isMe ? 'msg-admin' : 'msg-teacher'}">
                                <strong>${isMe ? 'You' : m.sender_name}</strong><br>
                                ${m.message_text}
                                ${timeTag}
                            </div>`;
                    }
                });
                area.innerHTML = html;
                area.scrollTop = area.scrollHeight;
            }
        });
}

if (targetTeacherId > 0) {
    setInterval(fetchMessages, 3000);
    fetchMessages();
}

function sendAdminMsg() {
    const text = document.getElementById('adminMsgInput').value.trim();
    if (!text || targetTeacherId <= 0) return;

    const fd = new FormData();
    fd.append('action', 'send_text');
    fd.append('receiver_id', targetTeacherId);
    fd.append('message', text);
    fd.append('type', 'text');

    fetch('chat_handler.php', { method: 'POST', body: fd }).then(() => {
        document.getElementById('adminMsgInput').value = '';
        fetchMessages();
    });
}

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

function checkIncomingCall() {
    fetch('call_handler.php?action=check_incoming')
        .then(res => res.json())
        .then(data => {
            if (data.status === 'incoming') {
                currentCallId = data.call_id;
                currentCallerId = data.caller_id;
                document.getElementById('callerNameText').innerText = data.caller_name + ' is calling...';
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
        window.open(`live_call.php?target_id=${currentCallerId}&caller=0`, '_blank');
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
</script>

</body>
</html>