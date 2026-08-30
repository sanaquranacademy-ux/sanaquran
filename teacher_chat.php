<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit;
}

$admin = $conn->query("SELECT id, name FROM users WHERE role = 'admin' LIMIT 1")->fetch_assoc();
$admin_id = $admin['id'] ?? 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher to Admin Hotline & Chat</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { background: #0f1410; color: #fff; margin: 0; padding: 20px; }
        .chat-container { max-width: 800px; margin: 0 auto; background: #18221a; border: 1px solid #283e2c; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; height: 90vh; }
        
        .chat-header { background: #121813; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #283e2c; }
        .chat-header h3 { margin: 0; color: #5cb85c; font-size: 16px; }
        .call-buttons { display: flex; gap: 10px; align-items: center; }
        
        /* ان-ویب سائٹ لائیو وائس کال بٹن */
        .btn-live-call { background: #28a745; color: white; padding: 7px 15px; border-radius: 20px; text-decoration: none; font-size: 12px; font-weight: bold; display: inline-flex; align-items: center; gap: 5px; animation: pulse 2s infinite; }
        .btn-live-call:hover { background: #218838; }
        
        .quick-alerts { background: #141c16; padding: 10px 15px; border-bottom: 1px solid #233325; display: flex; gap: 8px; overflow-x: auto; }
        .alert-btn { background: #233325; border: 1px solid #355239; color: #d4edda; font-size: 11px; padding: 5px 10px; border-radius: 15px; cursor: pointer; white-space: nowrap; }
        .alert-btn:hover { background: #5cb85c; color: black; }

        .messages-area { flex: 1; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; }
        .msg-bubble { max-width: 70%; padding: 10px 14px; border-radius: 10px; font-size: 13px; line-height: 1.4; word-wrap: break-word; }
        .msg-sent { align-self: flex-end; background: #2e7d32; color: #fff; }
        .msg-received { align-self: flex-start; background: #253328; color: #f5f5f5; }
        .msg-alert { background: #856404; color: #fff; align-self: center; text-align: center; border-radius: 20px; padding: 6px 16px; font-size: 12px; }

        .chat-input-bar { padding: 12px 15px; background: #121813; border-top: 1px solid #283e2c; display: flex; align-items: center; gap: 10px; }
        .chat-input-bar input { flex: 1; padding: 10px 15px; background: #0c120e; border: 1px solid #283e2c; border-radius: 20px; color: white; outline: none; font-size: 13px; }
        .btn-send { background: #5cb85c; color: black; border: none; padding: 8px 18px; border-radius: 20px; font-weight: bold; cursor: pointer; }
        .btn-mic { background: #d9534f; color: white; border: none; width: 38px; height: 38px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; }
        .recording { animation: pulse 1s infinite; background: #ff0000; }
        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.05); } 100% { transform: scale(1); } }
    </style>
</head>
<body>

<div class="chat-container">
    <div class="chat-header">
        <div>
            <h3>Admin Desk (<?php echo htmlspecialchars($admin['name'] ?? 'Admin'); ?>)</h3>
            <small style="color:#a0aab0;">Internal Hotline</small>
        </div>
        <div class="call-buttons">
            <!-- ویب سائٹ کے اندر لائیو کال بٹن -->
            <a href="live_call.php" target="_blank" class="btn-live-call" onclick="sendQuickAlert('📞 Teacher initiated an In-Portal Live Audio Call. Please join!')">📞 Start Live Voice Call</a>
            <a href="teacher_dashboard.php" style="color:#aaa; text-decoration:none; margin-left:10px; font-size:13px;">✕ Exit</a>
        </div>
    </div>

    <!-- Quick Alerts -->
    <div class="quick-alerts">
        <span style="font-size:11px; color:#888; align-self:center;">Quick Alerts:</span>
        <button class="alert-btn" onclick="sendQuickAlert('⚠️ Urgent: Student is Offline / Not joining class')">🔴 Student Offline</button>
        <button class="alert-btn" onclick="sendQuickAlert('⏳ Notice: Student joined class late')">⏰ Student Late</button>
        <button class="alert-btn" onclick="sendQuickAlert('🔊 Issue: Student having Mic/Audio problem')">🎤 Audio Issue</button>
        <button class="alert-btn" onclick="sendQuickAlert('✅ Lesson completed for today')">✅ Class Completed</button>
    </div>

    <div class="messages-area" id="msgArea"></div>

    <div class="chat-input-bar">
        <button class="btn-mic" id="recordBtn" onclick="toggleVoiceRecording()" title="Record Voice Note">🎙️</button>
        <input type="text" id="msgText" placeholder="Type message to Admin..." onkeypress="if(event.key === 'Enter') sendTextMsg()">
        <button class="btn-send" onclick="sendTextMsg()">Send</button>
    </div>
</div>

<script>
const adminId = <?php echo $admin_id; ?>;
let mediaRecorder;
let audioChunks = [];
let isRecording = false;

function fetchMessages() {
    fetch(`chat_handler.php?action=fetch_messages&other_id=${adminId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                const area = document.getElementById('msgArea');
                let html = '';
                data.messages.forEach(m => {
                    const isMe = (m.sender_id == data.my_id);
                    if (m.message_type === 'alert') {
                        html += `<div class="msg-bubble msg-alert">${m.message_text}</div>`;
                    } else if (m.message_type === 'voice') {
                        html += `
                            <div class="msg-bubble ${isMe ? 'msg-sent' : 'msg-received'}">
                                <strong>${isMe ? 'You (Voice Note)' : m.sender_name}</strong><br>
                                <audio controls src="uploads/voice_notes/${m.voice_file}" style="margin-top:5px; max-width:220px;"></audio>
                            </div>`;
                    } else {
                        html += `
                            <div class="msg-bubble ${isMe ? 'msg-sent' : 'msg-received'}">
                                <strong>${isMe ? 'You' : m.sender_name}</strong><br>
                                ${m.message_text}
                            </div>`;
                    }
                });
                area.innerHTML = html;
            }
        });
}

setInterval(fetchMessages, 3000);
fetchMessages();

function sendTextMsg() {
    const text = document.getElementById('msgText').value.trim();
    if (!text) return;

    const fd = new FormData();
    fd.append('action', 'send_text');
    fd.append('receiver_id', adminId);
    fd.append('message', text);
    fd.append('type', 'text');

    fetch('chat_handler.php', { method: 'POST', body: fd }).then(() => {
        document.getElementById('msgText').value = '';
        fetchMessages();
    });
}

function sendQuickAlert(alertMsg) {
    const fd = new FormData();
    fd.append('action', 'send_text');
    fd.append('receiver_id', adminId);
    fd.append('message', alertMsg);
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
            btn.classList.add('recording');
            btn.innerText = '⏹️';
        } catch (err) {
            alert('Microphone permission required for voice notes!');
        }
    } else {
        mediaRecorder.stop();
        isRecording = false;
        btn.classList.remove('recording');
        btn.innerText = '🎙️';
    }
}
</script>

</body>
</html>