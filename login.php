<?php session_start(); if(isset($_SESSION["user_id"])) { header("Location: index.php"); exit; } ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login | Laundry SaaS Pro</title>
    <link rel="icon" type="image/png" href="assets/logo.png">
    <link rel="stylesheet" href="style.css">
    <link rel="manifest" href="manifest.json">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #6366f1; --accent: #f59e0b; }
        body { margin: 0; padding: 0; height: 100vh; display: flex; align-items: center; justify-content: center; background: #0f172a; font-family: 'Plus Jakarta Sans', sans-serif; overflow: hidden; }
        .bubbles { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 0; overflow: hidden; }
        .bubble { position: absolute; bottom: -100px; width: 40px; height: 40px; background: rgba(255, 255, 255, 0.1); border-radius: 50%; opacity: 0.5; animation: rise 10s infinite ease-in; }
        @keyframes rise { 0% { bottom: -100px; transform: translateX(0); } 50% { transform: translateX(100px); } 100% { bottom: 1080px; transform: translateX(-200px); } }
        .login-card { position: relative; z-index: 10; background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); padding: 40px; border-radius: 32px; width: 90%; max-width: 400px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 1px solid rgba(255, 255, 255, 0.1); text-align: center; animation: cardEntrance 0.8s ease-out; }
        @keyframes cardEntrance { from { opacity: 0; transform: translateY(30px) scale(0.9); } to { opacity: 1; transform: translateY(0) scale(1); } }
        .login-logo img { width: 180px; height: auto; margin-bottom: 40px; animation: floating 3s ease-in-out infinite; }
        @keyframes floating { 0%, 100% { transform: translateY(0px); } 50% { transform: translateY(-15px); } }
        .input-group { text-align: left; margin-bottom: 20px; }
        .input-group label { display: block; margin-bottom: 8px; color: #cbd5e1; font-weight: 500; font-size: 0.8rem; padding-left: 5px; }
        .input-group input { width: 100%; padding: 14px 20px; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 16px; color: white; outline: none; transition: all 0.3s; font-size: 1rem; box-sizing: border-box; }
        .input-group input:focus { border-color: var(--primary); background: rgba(15, 23, 42, 0.8); box-shadow: 0 0 20px rgba(99, 102, 241, 0.2); }
        .btn-login { width: 100%; padding: 16px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; border: none; border-radius: 16px; font-weight: 700; font-size: 1rem; cursor: pointer; transition: all 0.3s; margin-top: 10px; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3); }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 15px 30px rgba(67, 56, 202, 0.4); }
        .alert-box { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); color: #f87171; padding: 14px; border-radius: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; font-size: 0.85rem; text-align: left; }
        
        /* Brimo Style Bio Button */
        .btn-bio { margin-top: 25px; padding: 12px; background: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; color: #818cf8; font-size: 0.85rem; cursor: pointer; display: none; width: 100%; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-bio:hover { background: rgba(255, 255, 255, 0.1); border-color: var(--primary); }
        .btn-bio i { font-size: 1.2rem; }
    </style>
</head>
<body>
    <div class="bubbles" id="bubbleContainer"></div>
    <div class="login-card">
        <div class="login-logo"><img src="assets/logo.png" alt="Logo"></div>

        <?php if(isset($_GET['error']) && $_GET['error'] == 'expired'): ?>
        <div class="alert-box" style="background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); color: #f59e0b;">
            <i class="fas fa-clock"></i>
            <div>
                <strong>Masa Berlaku Habis</strong>
                <p style="margin:0; font-size: 0.75rem;">Akun toko ini sudah expired. Silakan hubungi admin.</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if(isset($_GET['error']) && $_GET['error'] == 'invalid'): ?>
        <div class="alert-box">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <strong>Gagal Masuk</strong>
                <p style="margin:0; font-size: 0.75rem;">Username atau password salah.</p>
            </div>
        </div>
        <?php endif; ?>
        
        <form action="auth.php" method="POST">
            <div class="input-group">
                <label>Username</label>
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" class="btn-login">Masuk Sekarang</button>
        </form>


        <div style="margin-top: 30px; font-size: 0.7rem; color: #475569;">
            &copy; 2026 Laundry SaaS Central
        </div>
    </div>

    <script>
        // Generate bubbles
        const container = document.getElementById('bubbleContainer');
        for (let i = 0; i < 15; i++) {
            const bubble = document.createElement('div');
            bubble.className = 'bubble';
            const size = Math.random() * 40 + 20 + 'px';
            bubble.style.width = size; bubble.style.height = size;
            bubble.style.left = Math.random() * 100 + '%';
            bubble.style.animationDuration = Math.random() * 8 + 7 + 's';
            bubble.style.animationDelay = Math.random() * 5 + 's';
            container.appendChild(bubble);
        }

        if ("serviceWorker" in navigator) { window.addEventListener("load", () => { navigator.serviceWorker.register("sw.js"); }); }
    </script>
</body>
</html>
