<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'teacher';

$target_id = isset($_GET['target_id']) ? (int)$_GET['target_id'] : 0;
if ($target_id <= 0) {
    if ($role === 'teacher') {
        $adm = $conn->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch_assoc();
        $target_id = (int)($adm['id'] ?? 1);
    }
}

$is_caller = isset($_GET['caller']) && $_GET['caller'] === '1';

$target_user = $conn->query("SELECT name FROM users WHERE id = $target_id")->fetch_assoc();
$target_name = $target_user['name'] ?? 'Office / Desk';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Audio Call - Sana Quran House</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background: #f0fdf4; color: #1e293b; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .call-card { background: #ffffff; border: 1px solid #d1fae5; width: 100%; max-width: 360px; padding: 40px 25px; border-radius: 20px; text-align: center; box-shadow: 0 10px 30px rgba(16, 185, 129, 0.12); display: flex; flex-direction: column; align-items: center; }
        .avatar-box { width: 95px; height: 95px; border-radius: 50%; background: #ecfdf5; border: 3px solid #10b981; display: flex; align-items: center; justify-content: center; font-size: 38px; margin-bottom: 18px; }
        .pulsing { animation: ripple 1.8s infinite; }
        @keyframes ripple { 0% { box-shadow: 0 0 0 0 rgba(16,185,129,0.5); } 70% { box-shadow: 0 0 0 20px rgba(16,185,129,0); } 100% { box-shadow: 0 0 0 0 rgba(16,185,129,0); } }
        .target-name { font-size: 19px; font-weight: 700; color: #065f46; margin-bottom: 5px; }
        .call-status { font-size: 13.5px; color: #10b981; font-weight: 600; margin-bottom: 25px; }
        .timer { font-size: 24px; font-weight: 700; color: #1e293b; margin-bottom: 30px; display: none; }
        .call-controls { display: flex; gap: 20px; justify-content: center; width: 100%; }
        .ctrl-btn { width: 55px; height: 55px; border-radius: 50%; border: none; cursor: pointer; display: flex; justify-content: center; align-items: center; font-size: 18px; transition: 0.2s; }
        .btn-mute { background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; }
        .btn-mute.muted { background: #fef3c7; color: #b45309; }
        .btn-end { background: #ef4444; color: white; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35); }
        .btn-end:hover { background: #dc2626; transform: scale(1.06); }
        .btn-unmute-notice { background: #059669; color: white; border: none; padding: 6px 14px; border-radius: 15px; font-size: 12px; margin-bottom: 15px; cursor: pointer; display: none; }
    </style>
</head>
<body>

<div class="call-card">
    <div class="avatar-box pulsing" id="avatarBox">🎙️</div>
    <div class="target-name"><?php echo htmlspecialchars($target_name); ?></div>
    <div class="call-status" id="callStatus"><?php echo $is_caller ? 'Calling...' : 'Connecting Audio...'; ?></div>
    <button id="tapToHearBtn" class="btn-unmute-notice" onclick="resumeAudio()">🔊 Click to Enable Sound</button>
    <div class="timer" id="callTimer">00:00</div>

    <div class="call-controls">
        <button class="ctrl-btn btn-mute" id="muteBtn" onclick="toggleMute()" title="Mute Mic">🎤</button>
        <button class="ctrl-btn btn-end" onclick="terminateCall()" title="End Call">📞</button>
    </div>

    <!-- Hidden Audio Player -->
    <audio id="remoteAudio" autoplay playsinline></audio>
</div>

<script>
const myUserId = <?php echo $user_id; ?>;
const targetId = <?php echo $target_id; ?>;
const isCaller = <?php echo $is_caller ? 'true' : 'false'; ?>;

// متوازن اور مستقل Peer ID
const myPeerId = "sqh_audio_" + myUserId;
const targetPeerId = "sqh_audio_" + targetId;

let localStream = null;
let currentCall = null;
let seconds = 0;
let timerInterval = null;
let callSyncTimer = null;
let isConnected = false;

// PeerJS کنکشن مع پبلک STUN سرورز (بہترین کنکٹیویٹی کے لیے)
const peer = new Peer(myPeerId, {
    debug: 1,
    config: {
        iceServers: [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' }
        ]
    }
});

// 1. مائیکروفون ایکسس حاصل کرنا
navigator.mediaDevices.getUserMedia({ audio: true, video: false })
    .then(stream => {
        localStream = stream;

        // PeerJS ریڈی ہونے پر
        peer.on('open', (id) => {
            console.log('Peer connected with ID:', id);
            
            if (isCaller) {
                document.getElementById('callStatus').innerText = "Ringing... Waiting for answer";
                startCallingLoop();
            } else {
                document.getElementById('callStatus').innerText = "Ready. Connecting stream...";
            }
        });

        // آنے والی کال کا جواب دینا
        peer.on('call', call => {
            console.log('Receiving call from peer...');
            call.answer(localStream);
            setupStream(call);
        });
    })
    .catch(err => {
        document.getElementById('callStatus').innerText = "Microphone access blocked!";
        document.getElementById('callStatus').style.color = "#ef4444";
    });

// 2. بار بار کنیکٹ کرنے کی خودکار کوشش
function startCallingLoop() {
    let attempts = 0;
    const callInterval = setInterval(() => {
        if (isConnected || attempts > 25) {
            clearInterval(callInterval);
            return;
        }
        attempts++;
        console.log(`Call attempt #${attempts}`);
        const call = peer.call(targetPeerId, localStream);
        if (call) {
            setupStream(call);
        }
    }, 1500);
}

// 3. آڈیو اسٹریم سیٹ اپ
function setupStream(call) {
    currentCall = call;
    call.on('stream', remoteStream => {
        if (isConnected) return;
        isConnected = true;

        const audio = document.getElementById('remoteAudio');
        audio.srcObject = remoteStream;
        
        audio.play().catch(e => {
            console.log("Autoplay blocked by browser. Showing Tap Button.");
            document.getElementById('tapToHearBtn').style.display = "inline-block";
        });

        document.getElementById('callStatus').innerText = "Connected (In Call)";
        document.getElementById('callStatus').style.color = "#059669";
        document.getElementById('callTimer').style.display = "block";
        document.getElementById('avatarBox').classList.remove('pulsing');
        startTimer();
    });

    call.on('close', () => {
        closeCallLocally();
    });

    call.on('error', (err) => {
        console.error("Call error:", err);
    });
}

function resumeAudio() {
    const audio = document.getElementById('remoteAudio');
    audio.play();
    document.getElementById('tapToHearBtn').style.display = "none";
}

// 4. ٹائمر
function startTimer() {
    if (timerInterval) return;
    timerInterval = setInterval(() => {
        seconds++;
        const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
        const secs = (seconds % 60).toString().padStart(2, '0');
        document.getElementById('callTimer').innerText = `${mins}:${secs}`;
    }, 1000);
}

function toggleMute() {
    if (!localStream) return;
    const track = localStream.getAudioTracks()[0];
    const btn = document.getElementById('muteBtn');
    track.enabled = !track.enabled;
    btn.classList.toggle('muted', !track.enabled);
    btn.innerText = track.enabled ? '🎤' : '🔇';
}

// 5. کال منقطع کرنا
function terminateCall() {
    const fd = new FormData();
    fd.append('action', 'end_active_call');
    fd.append('target_id', targetId);
    fd.append('duration', seconds);

    fetch('call_handler.php', { method: 'POST', body: fd }).then(() => {
        closeCallLocally();
    });
}

function closeCallLocally() {
    if (currentCall) currentCall.close();
    if (localStream) localStream.getTracks().forEach(t => t.stop());
    if (timerInterval) clearInterval(timerInterval);
    if (callSyncTimer) clearInterval(callSyncTimer);
    document.getElementById('callStatus').innerText = "Call Ended";
    document.getElementById('callStatus').style.color = "#ef4444";
    setTimeout(() => window.close(), 1000);
}

// دوسری طرف سے کال کٹنے کا لائیو چیک
callSyncTimer = setInterval(() => {
    fetch(`call_handler.php?action=check_call_status&target_id=${targetId}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ended' || data.status === 'rejected') {
                closeCallLocally();
            }
        });
}, 1500);
</script>

</body>
</html>