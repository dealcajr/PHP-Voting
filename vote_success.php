<?php
require_once 'includes/config.php';

// This page is only accessible right after a vote is cast.
// We use a one-time session flag set by vote.php.
if (empty($_SESSION['vote_success'])) {
    header('Location: vote_login.php');
    exit();
}

// Consume the flag so it can't be revisited by refreshing
unset($_SESSION['vote_success']);
$voter_name = $_SESSION['voter_display_name'] ?? '';

// Destroy the voter session so the next voter can log in fresh
session_unset();
session_destroy();

// Get school info & theme (need a fresh DB connection after session destroy)
require_once 'includes/config.php'; // re-include to restart session if needed
$db = getDBConnection();
$school_info = $db->query("SELECT school_name, logo_path FROM school_info LIMIT 1")->fetch();
$school_name = $school_info['school_name'] ?? APP_NAME;
$school_logo = $school_info['logo_path'] ?? '';
$theme_row   = $db->query("SELECT theme_color FROM election_settings ORDER BY id DESC LIMIT 1")->fetch();
$theme_color = $theme_row['theme_color'] ?? '#007a1b';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($school_name); ?> – Vote Cast Successfully</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --theme: <?php echo htmlspecialchars($theme_color); ?>; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            display: flex; flex-direction: column;
            align-items: center; justify-content: center;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding: 2rem 1rem;
        }

        .success-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.10);
            padding: 3rem 2.5rem;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }

        /* ── Animated checkmark ── */
        .check-circle {
            width: 110px; height: 110px;
            border-radius: 50%;
            background: var(--theme, #007a1b);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.75rem;
            animation: pop 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275) both;
        }
        @keyframes pop {
            0%   { transform: scale(0); opacity: 0; }
            80%  { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }

        .check-svg {
            width: 56px; height: 56px;
        }
        .check-svg .check-path {
            stroke: #fff;
            stroke-width: 5;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
            stroke-dasharray: 80;
            stroke-dashoffset: 80;
            animation: draw-check 0.5s 0.4s ease forwards;
        }
        @keyframes draw-check {
            to { stroke-dashoffset: 0; }
        }

        .success-title {
            font-size: 1.75rem; font-weight: 800;
            color: #166534; margin-bottom: 0.5rem;
            animation: fade-up 0.5s 0.6s both;
        }
        .success-sub {
            color: #4b5563; font-size: 1rem; margin-bottom: 0.5rem;
            animation: fade-up 0.5s 0.75s both;
        }
        .voter-name {
            font-weight: 600; color: var(--theme, #007a1b);
            animation: fade-up 0.5s 0.85s both;
            margin-bottom: 1.75rem;
            font-size: 1.05rem;
        }

        @keyframes fade-up {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .btn-next {
            background: var(--theme, #007a1b);
            color: #fff; border: none;
            padding: 0.85rem 2.5rem;
            border-radius: 50px;
            font-size: 1.05rem; font-weight: 700;
            cursor: pointer; text-decoration: none;
            display: inline-block;
            transition: opacity 0.2s, transform 0.2s;
            animation: fade-up 0.5s 1s both;
            box-shadow: 0 4px 16px rgba(0,122,27,0.25);
        }
        .btn-next:hover {
            opacity: 0.9; transform: translateY(-2px); color: #fff;
        }

        .school-logo {
            height: 52px; object-fit: contain; margin-bottom: 1.5rem;
            animation: fade-up 0.4s 0.1s both;
        }
        .school-name {
            font-size: 0.85rem; color: #9ca3af; margin-top: 1.5rem;
            animation: fade-up 0.4s 1.1s both;
        }
    </style>
</head>
<body>
    <div class="success-card">

        <?php if ($school_logo): ?>
            <img src="<?php echo htmlspecialchars($school_logo); ?>" alt="School Logo" class="school-logo">
        <?php endif; ?>

        <!-- Animated check circle -->
        <div class="check-circle">
            <svg class="check-svg" viewBox="0 0 56 56">
                <polyline class="check-path" points="12,28 24,40 44,18"/>
            </svg>
        </div>

        <h1 class="success-title">Vote Cast!</h1>
        <p class="success-sub">Your vote has been recorded successfully.</p>

        <?php if ($voter_name): ?>
            <p class="voter-name">Thank you, <?php echo htmlspecialchars($voter_name); ?>!</p>
        <?php else: ?>
            <p class="voter-name">Thank you for participating!</p>
        <?php endif; ?>

        <a href="vote_login.php" class="btn-next">
            Login Next Voter
        </a>

        <p class="school-name"><?php echo htmlspecialchars($school_name); ?> &mdash; SSLG Voting System</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
