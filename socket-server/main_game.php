<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Player';
?>

<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>Main Game</title>
  <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="p-6">
  <div id="app" class="max-w-2xl mx-auto">
    <h1 class="text-xl font-bold mb-4">Tarik Tambang - Game</h1>

    <!-- Simple controls -->
    <div class="mb-4">
      <label>Session ID (masukkan session yang dibuat host):</label>
      <input id="sessionId" class="border p-2 rounded w-full" />
      <button id="joinBtn" class="mt-2 bg-blue-600 text-white px-3 py-2 rounded">Join Session</button>
    </div>

    <div id="status" class="mb-4 text-sm text-gray-600"></div>

    <div id="questionArea" class="mb-4"></div>

    <div class="mb-4">
      <div>Posisi tali: <span id="position">50</span></div>
      <div id="tugBar" class="w-full bg-gray-200 h-4 rounded mt-2 relative">
        <div id="tugIndicator" style="position:absolute; left:50%;" class="w-4 h-4 bg-red-600 rounded-full -translate-x-1/2 -translate-y-1/2"></div>
      </div>
    </div>

    <div>
      <h3 class="font-medium">Players</h3>
      <ul id="playersList"></ul>
    </div>
  </div>

<script>
  const userId = "<?php echo $user_id; ?>";
  const userName = "<?php echo htmlspecialchars($username, ENT_QUOTES); ?>";

  const socket = io("http://localhost:3000", {
    transports: ["websocket"]
  });

  document.getElementById('joinBtn').addEventListener('click', () => {
    const sessionId = document.getElementById('sessionId').value.trim();
    if (!sessionId) return alert('Masukkan session ID');

    socket.emit('join_session', {
      sessionId,
      userId,
      deviceName: userName
    });

    document.getElementById('status').innerText = 'Terkoneksi ke session ' + sessionId;
  });

  socket.on('players_update', (data) => {
    const el = document.getElementById('playersList');
    el.innerHTML = '';
    (data.players || []).forEach(p => {
      const li = document.createElement('li');
      li.innerText = `${p.deviceName || p.userId} — Score: ${p.score}`;
      el.appendChild(li);
    });
  });

  socket.on('question', (q) => {
    const area = document.getElementById('questionArea');
    area.innerHTML = `<div class="p-3 border rounded">
      <div class="font-semibold mb-2">${q.question_text}</div>
      ${q.options.map((opt, idx) => `<button data-idx="${idx}" class="answerBtn block w-full text-left mb-2 p-2 border rounded">${opt}</button>`).join('')}
    </div>`;

    // add listener
    document.querySelectorAll('.answerBtn').forEach(btn => {
      btn.addEventListener('click', () => {
        const selectedIdx = btn.getAttribute('data-idx');
        // Sederhana: kirim isCorrect flag jika client tahu;
        // idealnya server yang verifikasi berdasarkan DB.
        socket.emit('submit_answer', {
          sessionId: document.getElementById('sessionId').value.trim(),
          questionId: q.id,
          selected: selectedIdx,
          userId,
          socketId: socket.id,
          isCorrect: q.correctIndex == selectedIdx // only if server supplied correctIndex
        });
      });
    });
  });

  socket.on('update_tug', (data) => {
    const pos = data.position;
    document.getElementById('position').innerText = pos;
    const indicator = document.getElementById('tugIndicator');
    indicator.style.left = pos + '%';
    // update player list scores
    const el = document.getElementById('playersList');
    el.innerHTML = '';
    (data.players || []).forEach(p => {
      const li = document.createElement('li');
      li.innerText = `${p.deviceName || p.userId} — Score: ${p.score}`;
      el.appendChild(li);
    });
  });

  socket.on('game_over', (d) => {
    alert('Game over! Winner side: ' + d.winner);
  });
</script>
</body>
</html>
