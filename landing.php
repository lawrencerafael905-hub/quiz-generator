<?php
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();

// If logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: ' . rtrim(getenv('APP_URL'), '/') . '/pages/dashboard.php');
    exit;
}

$baseUrl = rtrim(getenv('APP_URL'), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Quiz Generator — Create and Share Interactive Quizzes</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        /* Landing page specific styles */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg:        #080b14;
            --surface:   rgba(18, 22, 42, 0.88);
            --surface-2: rgba(22, 27, 50, 0.7);
            --border:    rgba(255,255,255,0.08);
            --border-hi: rgba(255,255,255,0.13);
            --accent:    #f5c842;
            --accent-dim: rgba(245,200,66,0.13);
            --blue:      #5b8eff;
            --blue-dim:  rgba(91,142,255,0.12);
            --green:     #3ecf8e;
            --green-dim: rgba(62,207,142,0.12);
            --orange:    #ff9a3c;
            --orange-dim: rgba(255,154,60,0.12);
            --text:      #eceef8;
            --sub:       #8890b8;
            --danger:    #ff5e72;
            --radius:    12px;
            --nav-h:     60px;
        }

        html, body { min-height: 100%; }
        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        /* Background */
        .bg-layer { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        .orb { position: absolute; border-radius: 50%; filter: blur(110px); animation: drift 20s ease-in-out infinite alternate; }
        .orb-1 { width: 700px; height: 700px; background: #1a3aff; opacity: .10; top: -250px; left: -200px; }
        .orb-2 { width: 500px; height: 500px; background: #f5c842; opacity: .08; bottom: -200px; right: -150px; animation-delay: -7s; }
        .orb-3 { width: 360px; height: 360px; background: #8b2fff; opacity: .09; top: 40%; left: 60%; animation-delay: -13s; }
        @keyframes drift { from { transform: translate(0,0) scale(1); } to { transform: translate(55px,40px) scale(1.1); } }
        .grid {
            position: fixed; inset: 0; z-index: 0; pointer-events: none;
            background-image:
                linear-gradient(rgba(255,255,255,0.02) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 40px 40px;
            mask-image: radial-gradient(ellipse 80% 80% at 50% 30%, black 30%, transparent 100%);
        }

        /* Floating quiz chars */
        .floaters { position: fixed; inset: 0; z-index: 0; pointer-events: none; overflow: hidden; }
        .floater {
            position: absolute;
            font-family: 'Syne', sans-serif; font-weight: 800;
            color: rgba(255,255,255,0.035);
            animation: floatUp linear infinite;
            user-select: none;
        }
        @keyframes floatUp {
            from { transform: translateY(105vh) rotate(-12deg); opacity: 0; }
            8%   { opacity: 1; }
            92%  { opacity: 1; }
            to   { transform: translateY(-8vh) rotate(12deg); opacity: 0; }
        }

        @keyframes pulse {
            0%,100% { opacity:1; }
            50%      { opacity:.35; }
        }

        /* Navbar */
        .navbar {
            position: sticky; top: 0; z-index: 100;
            height: var(--nav-h);
            background: rgba(8,11,20,0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 28px;
            gap: 16px;
        }

        .brand {
            display: flex; align-items: center; gap: 10px;
            font-family: 'Syne', sans-serif;
            font-size: 17px; font-weight: 800;
            letter-spacing: -0.02em;
            text-decoration: none;
            color: var(--text);
            flex-shrink: 0;
        }

        .brand-icon {
            width: 32px; height: 32px;
            background: linear-gradient(135deg, #1c2ff0, #5b8eff);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
            font-size: 15px;
            box-shadow: 0 4px 12px rgba(91,142,255,0.3);
        }

        .brand em { font-style: normal; color: var(--accent); }

        .nav-links { display: flex; align-items: center; gap: 4px; flex: 1; justify-content: center; }
        .nav-link {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 13px; font-weight: 500;
            color: var(--sub);
            text-decoration: none;
            transition: all .18s;
        }
        .nav-link:hover { color: var(--text); background: rgba(255,255,255,0.06); }

        .nav-right { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }

        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 7px 14px;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px; font-weight: 500;
            text-decoration: none;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.05);
            color: var(--sub);
            cursor: pointer;
            transition: all .18s;
            white-space: nowrap;
        }

        .btn:hover { background: rgba(255,255,255,0.09); color: var(--text); border-color: var(--border-hi); }

        .btn-primary {
            background: var(--accent);
            color: #12100a;
            border-color: var(--accent);
            font-weight: 600;
        }

        .btn-primary:hover {
            filter: brightness(1.08);
            box-shadow: 0 4px 16px rgba(245,200,66,0.25);
            color: #12100a;
        }

        /* Hero Section */
        .hero {
            position: relative; z-index: 1;
            padding: 120px 24px 100px;
            text-align: center;
            max-width: 900px;
            margin: 0 auto;
        }

        .hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(91,142,255,0.1);
            border: 1px solid rgba(91,142,255,0.25);
            border-radius: 100px;
            padding: 8px 16px;
            font-size: 12px; color: var(--blue);
            margin-bottom: 24px;
            animation: fadeInUp 0.6s ease both;
        }

        .hero-badge::before {
            content: '✨';
            font-size: 14px;
        }

        .hero h1 {
            font-family: 'Syne', sans-serif;
            font-size: 56px; font-weight: 800;
            letter-spacing: -0.02em;
            line-height: 1.1;
            margin-bottom: 18px;
            animation: fadeInUp 0.6s ease both 0.1s backwards;
            background: linear-gradient(135deg, var(--text), rgba(245,200,66,0.8));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero p {
            font-size: 18px;
            color: var(--sub);
            line-height: 1.6;
            margin-bottom: 32px;
            animation: fadeInUp 0.6s ease both 0.2s backwards;
        }

        .hero-actions {
            display: flex; align-items: center; justify-content: center; gap: 16px;
            flex-wrap: wrap;
            animation: fadeInUp 0.6s ease both 0.3s backwards;
        }

        .btn-lg {
            padding: 12px 28px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 10px;
        }

        .btn-secondary {
            background: rgba(255,255,255,0.08);
            border-color: var(--border-hi);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.12);
            border-color: var(--border-hi);
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Features Section */
        .features {
            position: relative; z-index: 1;
            padding: 100px 24px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-header h2 {
            font-family: 'Syne', sans-serif;
            font-size: 36px; font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 12px;
        }

        .section-header p {
            font-size: 16px;
            color: var(--sub);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .feature-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px 24px;
            text-align: center;
            transition: all .3s;
            backdrop-filter: blur(16px);
        }

        .feature-card:hover {
            border-color: var(--border-hi);
            transform: translateY(-4px);
            box-shadow: 0 16px 40px rgba(0,0,0,0.3);
        }

        .feature-icon {
            width: 56px; height: 56px;
            background: var(--blue-dim);
            border: 1px solid rgba(91,142,255,0.25);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px;
            margin: 0 auto 16px;
        }

        .feature-card:nth-child(2) .feature-icon {
            background: var(--green-dim);
            border-color: rgba(62,207,142,0.25);
        }

        .feature-card:nth-child(3) .feature-icon {
            background: var(--orange-dim);
            border-color: rgba(255,154,60,0.25);
        }

        .feature-card h3 {
            font-family: 'Syne', sans-serif;
            font-size: 18px; font-weight: 700;
            margin-bottom: 10px;
        }

        .feature-card p {
            font-size: 14px;
            color: var(--sub);
            line-height: 1.6;
        }

        /* How it works */
        .how-it-works {
            position: relative; z-index: 1;
            padding: 100px 24px;
            max-width: 1100px;
            margin: 0 auto;
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
        }

        .step-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            position: relative;
            backdrop-filter: blur(16px);
        }

        .step-number {
            display: inline-flex; align-items: center; justify-content: center;
            width: 40px; height: 40px;
            background: linear-gradient(135deg, var(--blue), var(--accent));
            border-radius: 10px;
            font-family: 'Syne', sans-serif;
            font-size: 18px; font-weight: 800;
            color: #fff;
            margin-bottom: 16px;
        }

        .step-card h3 {
            font-family: 'Syne', sans-serif;
            font-size: 16px; font-weight: 700;
            margin-bottom: 10px;
        }

        .step-card p {
            font-size: 13.5px;
            color: var(--sub);
            line-height: 1.6;
        }

        /* Stats Section */
        .stats {
            position: relative; z-index: 1;
            padding: 80px 24px;
            max-width: 900px;
            margin: 0 auto;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
        }

        .stat-number {
            font-family: 'Syne', sans-serif;
            font-size: 36px; font-weight: 800;
            background: linear-gradient(135deg, var(--blue), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 6px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--sub);
        }

        /* CTA Section */
        .cta-section {
            position: relative; z-index: 1;
            padding: 80px 24px;
            text-align: center;
            max-width: 700px;
            margin: 0 auto;
        }

        .cta-card {
            background: var(--surface);
            border: 1px solid var(--border-hi);
            border-radius: 20px;
            padding: 60px 40px;
            backdrop-filter: blur(16px);
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
        }

        .cta-card h2 {
            font-family: 'Syne', sans-serif;
            font-size: 32px; font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 16px;
        }

        .cta-card p {
            font-size: 16px;
            color: var(--sub);
            margin-bottom: 32px;
            line-height: 1.6;
        }

        /* Footer */
        .footer {
            position: relative; z-index: 1;
            padding: 40px 24px;
            border-top: 1px solid var(--border);
            background: rgba(8,11,20,0.5);
        }

        .footer-content {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-text {
            font-size: 13px;
            color: var(--sub);
        }

        .footer-links {
            display: flex;
            gap: 20px;
        }

        .footer-link {
            font-size: 13px;
            color: var(--sub);
            text-decoration: none;
            transition: color .2s;
        }

        .footer-link:hover {
            color: var(--text);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 38px;
            }

            .hero p {
                font-size: 16px;
            }

            .section-header h2 {
                font-size: 28px;
            }

            .nav-links {
                display: none;
            }

            .cta-card {
                padding: 40px 24px;
            }

            .cta-card h2 {
                font-size: 24px;
            }

            .footer-content {
                justify-content: center;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Background -->
    <div class="bg-layer">
        <div class="orb orb-1"></div>
        <div class="orb orb-2"></div>
        <div class="orb orb-3"></div>
    </div>
    <div class="grid"></div>
    <div class="floaters" id="floaters"></div>

    <!-- Navigation -->
    <nav class="navbar">
        <a href="landing.php" class="brand">
            <div class="brand-icon">🧠</div>
            Quiz<em>Generator</em>
        </a>

        <div class="nav-links">
            <a href="#features" class="nav-link">Features</a>
            <a href="#how-it-works" class="nav-link">How It Works</a>
            <a href="#about" class="nav-link">About</a>
        </div>

        <div class="nav-right">
            <a href="pages/login.php" class="btn">Sign In</a>
            <a href="pages/register.php" class="btn btn-primary">Get Started</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-badge">Introducing Quiz Generator</div>
        <h1>Create Interactive Quizzes in Minutes</h1>
        <p>A modern platform to create, share, and manage interactive quizzes. Perfect for educators, trainers, and assessment professionals.</p>
        <div class="hero-actions">
            <a href="pages/register.php" class="btn btn-primary btn-lg">Start Creating</a>
            <a href="pages/login.php" class="btn btn-secondary btn-lg">Sign In</a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="section-header">
            <h2>Powerful Features</h2>
            <p>Everything you need to create and manage professional quizzes</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>Easy Creation</h3>
                <p>Intuitive interface makes it simple to create engaging quizzes with multiple question types.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📊</div>
                <h3>Detailed Analytics</h3>
                <p>Track student performance with comprehensive analytics and detailed result reporting.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🔄</div>
                <h3>Real-time Sync</h3>
                <p>Live updates and real-time notifications for quiz submissions and responses.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🛡️</div>
                <h3>Secure & Safe</h3>
                <p>Enterprise-grade security with CSRF protection and secure session management.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">👥</div>
                <h3>Multi-role System</h3>
                <p>Support for students, teachers, and administrators with role-based access control.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">📱</div>
                <h3>Responsive Design</h3>
                <p>Works seamlessly on desktop, tablet, and mobile devices for maximum accessibility.</p>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works" id="how-it-works">
        <div class="section-header">
            <h2>How It Works</h2>
            <p>Getting started is simple — follow these easy steps</p>
        </div>

        <div class="steps-grid">
            <div class="step-card">
                <div class="step-number">1</div>
                <h3>Create an Account</h3>
                <p>Sign up as a teacher or student and set up your profile in seconds.</p>
            </div>

            <div class="step-card">
                <div class="step-number">2</div>
                <h3>Create a Quiz</h3>
                <p>Use our intuitive editor to add questions, set point values, and configure settings.</p>
            </div>

            <div class="step-card">
                <div class="step-number">3</div>
                <h3>Publish & Share</h3>
                <p>Publish your quiz and share it with students or colleagues.</p>
            </div>

            <div class="step-card">
                <div class="step-number">4</div>
                <h3>Monitor Results</h3>
                <p>Track submissions in real-time and analyze detailed performance metrics.</p>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number">10K+</div>
                <div class="stat-label">Quizzes Created</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">50K+</div>
                <div class="stat-label">Active Users</div>
            </div>
            <div class="stat-item">
                <div class="stat-number">100K+</div>
                <div class="stat-label">Responses Recorded</div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="cta-card">
            <h2>Ready to Get Started?</h2>
            <p>Join thousands of educators and trainers who use Quiz Generator to create engaging assessments.</p>
            <div class="hero-actions">
                <a href="pages/register.php" class="btn btn-primary btn-lg">Create Your Account</a>
                <a href="pages/login.php" class="btn btn-secondary btn-lg">Sign In</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <div class="footer-text">
                &copy; <?= date('Y') ?> Quiz Generator. All rights reserved.
            </div>
            <div class="footer-links">
                <a href="#" class="footer-link">Privacy</a>
                <a href="#" class="footer-link">Terms</a>
                <a href="#" class="footer-link">Contact</a>
            </div>
        </div>
    </footer>

    <script>
        // Floating chars
        const chars = ['?','?','?','A','B','C','D','✓','✗','?'];
        const fc = document.getElementById('floaters');
        function spawn() {
            const el = document.createElement('div');
            el.className = 'floater';
            el.textContent = chars[Math.floor(Math.random() * chars.length)];
            el.style.cssText = `left:${Math.random()*100}vw;font-size:${Math.random()*90+36}px`;
            const d = Math.random() * 16 + 10;
            el.style.animationDuration = d + 's';
            el.style.animationDelay    = (Math.random() * -d) + 's';
            fc.appendChild(el);
            setTimeout(() => el.remove(), (d + 2) * 1000);
        }
        for (let i = 0; i < 16; i++) spawn();
        setInterval(spawn, 2200);
    </script>
</body>
</html>
