<?php
session_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once (__DIR__) . '/config/database.php';
require_once (__DIR__) . '/config/config.php';

$error = '';

// ── Logout ────────────────────────────────────────────────────────────────
if (isset($_POST['logout']) || isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// ── Already logged in? Redirect immediately ───────────────────────────────
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'superadmin': header('Location: modules/index.php'); break;
        case 'manager':    header('Location: roles/manager/index.php');   break;
        case 'staff':      header('Location: roles/staff/index.php');     break;
        case 'officer':    header('Location: roles/officer/index.php');   break;
        default:           header('Location: modules/index.php'); break;
    }
    exit();
}

// ── Handle login form submission ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['logout'])) {

    $identifier = cleanInput($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {

        $user = fetchSingle(
            "SELECT * FROM `user`
             WHERE (username = ? OR email = ?)
               AND status = 'active'
             LIMIT 1",
            [$identifier, $identifier]
        );

        if ($user && password_verify($password, $user['password'])) {

            $_SESSION['admin_id']      = $user['admin_id'];
            $_SESSION['username']      = $user['username'];
            $_SESSION['full_name']     = $user['full_name'];
            $_SESSION['email']         = $user['email'];
            $_SESSION['role']          = $user['role'] ?? '';
            $_SESSION['branch_id']     = $user['branch_id'] ?? null;
            $_SESSION['last_activity'] = time();

            if (!empty($user['branch_id'])) {
                $branch = fetchSingle(
                    "SELECT name FROM `branches` WHERE branch_id = ? LIMIT 1",
                    [(int)$user['branch_id']]
                );
                $_SESSION['branch_name'] = $branch['name'] ?? 'Unknown Branch';
            } else {
                $_SESSION['branch_name'] = 'No Branch Assigned';
            }

            logActivity('Auth', 'Login', (int)$user['admin_id'], 'Admin logged in successfully');
            executeQuery("UPDATE `user` SET last_login = NOW() WHERE admin_id = ?", [(int)$user['admin_id']]);

            switch ($_SESSION['role']) {
                case 'superadmin': header('Location: modules/index.php'); break;
                case 'manager':    header('Location: roles/manager/index.php');   break;
                case 'staff':      header('Location: roles/staff/index.php');     break;
                case 'officer':    header('Location: roles/officer/index.php');   break;
                default:           header('Location: modules/index.php'); break;
            }
            exit();

        } else {
            $error = 'Invalid credentials. Access denied.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sig-In Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Nunito:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            /* ─── Core brand greens — deeper, richer, more authoritative ─── */
            --primary:       #1A6B2F;
            --primary-dark:  #0F3D1C;
            --primary-dim:   #071409;
            --accent:        #2D8A47;
            --accent-bright: #3DAF5E;
            --light:         #6DBF85;
            --light-muted:   #9FCFAA;

            /* ─── Gold / amber ─── */
            --gold:          #B8832A;
            --gold-mid:      #D4A040;
            --gold-lt:       #F0C96A;
            --gold-pale:     #FBF0D5;

            /* ─── Background & surfaces ─── */
            --bg:            #F2F7F3;
            --surface:       #FFFFFF;
            --surface-tint:  #F4FAF5;          /* slightly greener tint */
            --frost:         #EBF5EE;

            /* ─── Borders ─── */
            --border:        rgba(26,107,47,0.18);
            --border-focus:  rgba(26,107,47,0.55);
            --border-gold:   rgba(184,131,42,0.45);

            /* ─── Text ─── */
            --text:          #0C1E10;
            --text-mid:      #1E3D26;
            --muted:         #4A6652;

            /* ─── Status / error ─── */
            --error:         #B83232;
            --error-bg:      #FDF4F4;
            --error-border:  #EFBFBF;

            /* ─── Left panel dark layers ─── */
            --panel-deep:    #041108;
            --panel-mid:     #0C2714;
            --panel-base:    #163B20;

            --trans: all 0.28s cubic-bezier(0.4,0,0.2,1);
        }

        html, body { height: 100%; font-family: 'Nunito', sans-serif; -webkit-font-smoothing: antialiased; }

        body {
            min-height: 100vh;
            background: var(--bg);
            display: flex;
            align-items: stretch;
            overflow: hidden;
        }

        /* ══════════════════════════════════════
           LEFT PANEL — UNCHANGED
        ══════════════════════════════════════ */
        .left {
            position: relative;
            width: 50%;
            flex-shrink: 0;
            background: var(--surface);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chev-main {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        .chev-main svg {
            width: 100%;
            height: 100%;
            display: block;
            preserveAspectRatio: none;
        }

        .chev-dots {
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle, rgba(255,255,255,0.055) 1px, transparent 1px);
            background-size: 20px 20px;
            pointer-events: none;
            z-index: 2;
            clip-path: polygon(0 0, 78% 0, 100% 50%, 78% 100%, 0 100%);
        }

        .chev-ring {
            position: absolute;
            top: 50%; left: 38%;
            transform: translate(-50%, -50%);
            width: 500px; height: 500px;
            border: 1px solid rgba(109,191,133,0.08);
            border-radius: 50%;
            pointer-events: none;
            z-index: 2;
            animation: spinSlow 60s linear infinite;
        }
        .chev-ring-2 {
            width: 340px; height: 340px;
            border-color: rgba(184,131,42,0.06);
            animation-duration: 40s;
            animation-direction: reverse;
        }
        @keyframes spinSlow { to { transform: translate(-50%,-50%) rotate(360deg); } }

        .chev-glow {
            position: absolute;
            top: 40%; left: 28%;
            transform: translate(-50%, -50%);
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(45,138,71,0.22) 0%, rgba(184,131,42,0.08) 55%, transparent 75%);
            pointer-events: none;
            z-index: 1;
            animation: breathe 6s ease-in-out infinite;
        }
        @keyframes breathe {
            0%,100% { transform: translate(-50%,-50%) scale(1); opacity: 0.7; }
            50%      { transform: translate(-50%,-50%) scale(1.15); opacity: 1; }
        }

        .logo-wrap {
            position: absolute;
            top: 0; left: 0; bottom: 0;
            width: 76%;
            z-index: 5;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 16px;
            padding: 2rem 1rem 2rem 1.5rem;
            animation: logoIn 0.9s 0.1s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes logoIn {
            from { opacity: 0; transform: translateY(20px) scale(0.92); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .shield-wrap {
            position: relative;
            width: 300px;
            height: 300px;
            filter: drop-shadow(0 14px 40px rgba(0,0,0,0.55)) drop-shadow(0 0 30px rgba(45,138,71,0.15));
        }
        .shield-wrap svg { width: 100%; height: 100%; }
        .shield-body {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding-bottom: 8px;
        }
        .shield-logo {
            width: 220px;
            height: auto;
            object-fit: contain;
            filter: drop-shadow(0 0 22px rgba(61,175,94,0.45));
        }

        .brand { text-align: center; }
        .brand-name {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1.65rem;
            font-weight: 800;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            line-height: 1.05;
            text-shadow: 0 2px 20px rgba(0,0,0,0.5);
        }
        .brand-name em {
            color: var(--gold-lt);
            font-style: normal;
            display: block;
        }
        .brand-sub {
            font-size: 10px;
            font-weight: 600;
            color: rgba(255,255,255,0.32);
            text-transform: uppercase;
            letter-spacing: 0.18em;
            margin-top: 7px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            color: var(--gold-lt);
            background: rgba(184,131,42,0.14);
            border: 1px solid rgba(184,131,42,0.28);
            padding: 5px 13px;
            border-radius: 20px;
            margin-top: 6px;
            backdrop-filter: blur(4px);
        }
        .pulse {
            width: 6px; height: 6px;
            background: var(--gold-lt);
            border-radius: 50%;
            box-shadow: 0 0 0 0 rgba(240,201,106,0.5);
            animation: ripple 2.2s ease-in-out infinite;
        }
        @keyframes ripple {
            0%   { box-shadow: 0 0 0 0 rgba(240,201,106,0.55); }
            70%  { box-shadow: 0 0 0 6px rgba(240,201,106,0); }
            100% { box-shadow: 0 0 0 0 rgba(240,201,106,0); }
        }

        /* ══════════════════════════════════════
           RIGHT PANEL — white bg preserved, palette updated
        ══════════════════════════════════════ */
        .right {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 2.5rem 3.5rem;
            overflow-y: auto;
            position: relative;
            background: var(--surface);   /* white — unchanged */
        }

        /* Gold-to-green gradient bar — mirrors left panel colors */
        .right::after {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            background: linear-gradient(90deg, var(--gold) 0%, var(--gold-lt) 35%, var(--accent-bright) 65%, var(--primary) 100%);
        }

        /* Faint dot pattern — same green as left panel dots */
        .right::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image: radial-gradient(circle, rgba(26,107,47,0.04) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
        }

        .right-inner {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 500px;
            animation: slideUp 0.7s 0.2s cubic-bezier(0.16,1,0.3,1) both;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        /* ── Header ── */
        .form-hd {
            margin-bottom: 1.6rem;
            padding-bottom: 1.1rem;
            /* Gold accent line — matches left panel gold sliver */
            border-bottom: 1.5px solid transparent;
            border-image: linear-gradient(90deg, var(--gold), var(--gold-lt), rgba(26,107,47,0.25)) 1;
        }
        .form-hd-eyebrow {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            /* Amber-gold matching left panel gold tones */
            color: var(--gold);
            margin-bottom: 5px;
        }
        .form-hd h1 {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: clamp(1.9rem, 2.8vw, 2.5rem);
            font-weight: 800;
            color: var(--text);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            line-height: 1;
        }
        /* "Sign In" accent — deep forest green from left panel */
        .form-hd h1 span { color: var(--primary); }

        /* ── Role pills — forest green palette ── */
        .role-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 1.5rem;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: var(--primary-dark);
            background: linear-gradient(135deg, #EBF5EE 0%, #F4FAF5 100%);
            border: 1px solid rgba(26,107,47,0.22);
            padding: 4px 11px;
            border-radius: 6px;
            transition: var(--trans);
        }
        .pill:hover {
            background: linear-gradient(135deg, rgba(26,107,47,0.12) 0%, rgba(45,138,71,0.06) 100%);
            border-color: var(--primary);
            color: var(--primary);
            box-shadow: 0 2px 8px rgba(26,107,47,0.12);
        }
        /* Gold dot — matches left panel gold accent */
        .pill-dot {
            width: 5px; height: 5px;
            background: var(--gold-mid);
            border-radius: 50%;
            opacity: 0.9;
        }

        /* ── Error alert ── */
        .error-box {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            background: var(--error-bg);
            border: 1px solid var(--error-border);
            border-left: 3px solid var(--error);
            border-radius: 10px;
            padding: 0.85rem 1rem;
            margin-bottom: 1.2rem;
            animation: shake 0.45s ease;
        }
        @keyframes shake {
            0%,100% { transform: translateX(0); }
            20%,60% { transform: translateX(-5px); }
            40%,80% { transform: translateX(5px); }
        }
        .error-box i { color: var(--error); font-size: 13px; margin-top: 1px; }
        .error-box span { font-size: 12.5px; font-weight: 500; color: #7d1f1f; line-height: 1.5; }

        /* ── Fields ── */
        .field { display: flex; flex-direction: column; gap: 4px; margin-bottom: 1rem; }
        .field:last-of-type { margin-bottom: 0; }

        /* Label — deep forest green with gold icon accent */
        label.flabel {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--primary-dark);
            user-select: none;
        }
        /* Gold icon — matches left panel gold decorations */
        label.flabel i { font-size: 8px; color: var(--gold-mid); }

        .input-wrap { position: relative; }

        /* Input — green-tinted bg with forest green border on focus */
        .finput {
            width: 100%;
            padding: 0.8rem 1rem 0.8rem 2.4rem;
            background: var(--surface-tint);
            border: 1.5px solid var(--border);
            border-radius: 9px;
            font-family: 'Nunito', sans-serif;
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text);
            outline: none;
            transition: var(--trans);
            -webkit-appearance: none;
        }
        /* Placeholder — soft sage matching left panel light tones */
        .finput::placeholder { color: var(--light-muted); font-weight: 400; }
        .finput:hover:not(:focus) {
            border-color: rgba(26,107,47,0.32);
            background: #EFF7F1;
        }
        .finput:focus {
            border-color: var(--primary);
            background: #fff;
            /* Forest green glow — same hue as left panel chevron */
            box-shadow: 0 0 0 3.5px rgba(26,107,47,0.10), 0 2px 8px rgba(26,107,47,0.06);
        }
        .finput.err {
            border-color: #e0a0a0;
            background: #FFF8F8;
        }

        /* Input icon — sage green, glows to forest green on focus */
        .ficon {
            position: absolute;
            left: 0.7rem;
            top: 50%;
            transform: translateY(-50%);
            font-size: 10px;
            color: var(--light-muted);
            pointer-events: none;
            transition: color 0.2s;
        }
        .finput:focus ~ .ficon { color: var(--primary); }

        /* Eye button — matches muted green */
        .eye-btn {
            position: absolute;
            right: 0.6rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 11px;
            color: var(--light-muted);
            padding: 5px;
            line-height: 1;
            transition: color 0.2s;
        }
        .eye-btn:hover { color: var(--primary); }

        /* ── Forgot link — forest green ── */
        .forgot {
    font-size: 12px;
    color: var(--muted);
    margin-top: 0.85rem;
    text-align: center;
}
        .forgot a {
    color: #2563EB;
    font-weight: 700;
    text-decoration: none;
    border-bottom: 1.5px solid #2563EB;
    padding-bottom: 1px;
    transition: color 0.2s, border-color 0.2s;
}
.forgot a:hover {
    color: #1D4ED8;
    border-bottom-color: #1D4ED8;
}

        /* ── Submit button — deep forest gradient + gold shimmer (mirrors left panel chevron + gold sliver) ── */
        .submit-btn {
            display: block;
            width: 100%;
            margin-top: 1.2rem;
            padding: 0.88rem 1rem;
            /* Same gradient progression as left panel: panel-deep → panel-base → primary */
            background: linear-gradient(135deg, var(--primary-dim) 0%, var(--panel-base) 40%, var(--primary) 100%);
            color: #fff;
            border: none;
            border-radius: 9px;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.13em;
            text-transform: uppercase;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            transition: var(--trans);
            /* Gold-tinted shadow — matches left panel gold glow */
            box-shadow: 0 4px 18px rgba(15,61,28,0.30), 0 1px 0 rgba(184,131,42,0.20) inset, inset 0 1px 0 rgba(255,255,255,0.07);
        }
        /* Gold shimmer — exactly like left panel's sliverBlend sweep */
        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0; left: -110%;
            width: 110%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(240,201,106,0.18), rgba(212,160,64,0.10), transparent);
            transition: left 0.55s ease;
        }
        /* Gold bottom border — mirrors left panel gold edge line */
        .submit-btn::after {
            content: '';
            position: absolute;
            bottom: 0; left: 15%; right: 15%;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold-mid), var(--gold-lt), var(--gold-mid), transparent);
            opacity: 0.6;
        }
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 28px rgba(15,61,28,0.36), 0 2px 8px rgba(184,131,42,0.18), inset 0 1px 0 rgba(255,255,255,0.09);
            background: linear-gradient(135deg, #071409 0%, #1A3D22 40%, var(--accent) 100%);
        }
        .submit-btn:hover::before { left: 110%; }
        .submit-btn:active { transform: translateY(0); box-shadow: 0 2px 8px rgba(15,61,28,0.22); }
        .submit-btn:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
        .btn-inner { display: flex; align-items: center; justify-content: center; gap: 9px; }

        /* ── Security notice — forest green palette ── */
        .notice {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            background: linear-gradient(135deg, #EBF5EE 0%, #F4FAF5 100%);
            border: 1px solid rgba(26,107,47,0.16);
            /* Left accent line in gold — mirrors left panel sliver */
            border-left: 3px solid var(--gold-mid);
            border-radius: 10px;
            padding: 0.85rem 1rem;
            margin-top: 1.1rem;
        }
        /* Notice icon — same dark green gradient as left panel chevron */
        .notice-icon {
            width: 30px; height: 30px;
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 10px rgba(15,61,28,0.28);
        }
        /* Icon — gold-lt matching left panel gold accent text */
        .notice-icon i { font-size: 11px; color: var(--gold-lt); }
        .notice-text { font-size: 11px; color: var(--muted); line-height: 1.55; }
        .notice-text strong {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 2px;
        }

        /* ── Footer ── */
        .page-foot {
            text-align: center;
            font-size: 10.5px;
            color: var(--light-muted);
            margin-top: 1.2rem;
        }
        .page-foot a { color: var(--accent); text-decoration: none; font-weight: 600; }
        .page-foot a:hover { color: var(--primary); text-decoration: underline; }
        .page-foot .sep { margin: 0 5px; opacity: 0.5; }

        /* ══════════════════════════════════════
           RESPONSIVE
        ══════════════════════════════════════ */
        @media (max-width: 720px) {
            body { overflow-y: auto; flex-direction: column; }
            .left { width: 100%; height: 220px; flex-shrink: 0; }
            .chev-main svg { preserveAspectRatio: xMidYMid slice; }
            .chev-dots {
                clip-path: polygon(0 0, 100% 0, 100% 80%, 50% 100%, 0 80%);
            }
            .chev-ring { display: none; }
            .logo-wrap {
                width: 100%;
                flex-direction: row;
                padding: 1.5rem 2rem;
                gap: 1.25rem;
            }
            .brand { text-align: left; }
            .shield-wrap { width: 82px; height: 94px; }
            .right { padding: 1.75rem 1.4rem; align-items: stretch; }
            .right-inner { max-width: 100%; }
        }

        @media (max-width: 420px) {
            .right { padding: 1.4rem 1rem; }
        }
    </style>
</head>
<body>

<div class="left">
    <!-- Single clean main chevron -->
    <div class="chev-main">
        <svg viewBox="0 0 500 600" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="chevGrad" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0%"   stop-color="#1A6B2F"/>
                    <stop offset="100%" stop-color="#2D8A47"/>
                </linearGradient>
                <linearGradient id="sliverBlend" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%"   stop-color="#FFF0A0"/>
                    <stop offset="35%"  stop-color="#D4A040"/>
                    <stop offset="50%"  stop-color="#8B6500"/>
                    <stop offset="65%"  stop-color="#D4A040"/>
                    <stop offset="100%" stop-color="#FFF0A0"/>
                </linearGradient>
                <linearGradient id="sliverGrad" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0%"   stop-color="#7A5010"/>
                    <stop offset="60%"  stop-color="#B8832A"/>
                    <stop offset="100%" stop-color="#D4A040"/>
                </linearGradient>
                <linearGradient id="chevEdge" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%"   stop-color="rgba(184,131,42,0)"/>
                    <stop offset="30%"  stop-color="rgba(212,160,64,0.7)"/>
                    <stop offset="50%"  stop-color="rgba(240,201,106,0.95)"/>
                    <stop offset="70%"  stop-color="rgba(212,160,64,0.7)"/>
                    <stop offset="100%" stop-color="rgba(184,131,42,0)"/>
                </linearGradient>
                <filter id="chevGlow">
                    <feGaussianBlur in="SourceGraphic" stdDeviation="4" result="blur"/>
                    <feComposite in="SourceGraphic" in2="blur" operator="over"/>
                </filter>
            </defs>

            <!-- Gold sliver — metallic shield lining style -->
            <polygon
                points="0,0 370,0 450,300 370,600 0,600"
                fill="url(#sliverBlend)"
            />

            <!-- Main green chevron — balanced triangle point -->
            <polygon
                points="0,0 350,0 430,300 350,600 0,600"
                fill="url(#chevGrad)"
            />

            <!-- Metallic gold edge line -->
            <polyline
                points="350,0 430,300 350,600"
                fill="none"
                stroke="url(#sliverBlend)"
                stroke-width="2"
                filter="url(#chevGlow)"
            />
        </svg>
    </div>
    <div class="chev-dots"></div>
    <div class="chev-ring"></div>
    <div class="chev-ring chev-ring-2"></div>
    <div class="chev-glow"></div>

    <div class="logo-wrap">
        <div class="shield-wrap">
            <svg viewBox="0 0 120 136" fill="none" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="shg" x1="60" y1="4" x2="60" y2="132" gradientUnits="userSpaceOnUse">
                        <stop offset="0%" stop-color="#2D8A47"/>
                        <stop offset="60%" stop-color="#1A6B2F"/>
                        <stop offset="100%" stop-color="#071409"/>
                    </linearGradient>
                    <linearGradient id="shborder" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="rgba(212,160,64,0.80)"/>
                        <stop offset="50%" stop-color="rgba(240,201,106,0.50)"/>
                        <stop offset="100%" stop-color="rgba(184,131,42,0)"/>
                    </linearGradient>
                </defs>
                <path d="M60 4 L114 24 L114 80 C114 110 60 132 60 132 C60 132 6 110 6 80 L6 24 Z"
                      fill="url(#shg)"/>
                <path d="M60 4 L114 24 L114 80 C114 110 60 132 60 132 C60 132 6 110 6 80 L6 24 Z"
                      fill="none" stroke="url(#shborder)" stroke-width="2.5"/>
                <path d="M60 14 L104 32 L104 80 C104 106 60 124 60 124 C60 124 16 106 16 80 L16 32 Z"
                      fill="none" stroke="rgba(255,255,255,0.06)" stroke-width="1"/>
            </svg>
            <div class="shield-body">
                <img src="assets/images/logo-bpm.png" alt="BPM Logo" class="shield-logo">
            </div>
        </div>

        <div class="brand">
            <div class="brand-name">Prosperity<em>Microfinance</em></div>
            <div class="brand-sub">Operations Portal</div>
            <div class="status-badge" style="margin-top:10px">
                <span class="pulse"></span>
                System Online
            </div>
        </div>
    </div>
</div>

<div class="right">
<div class="right-inner">

    <div class="form-hd">
        <div class="form-hd-eyebrow">Prosperity Microfinance</div>
        <h1>Portal <span>Sign In</span></h1>
    </div>

    <div class="role-pills">
        <span class="pill"><span class="pill-dot"></span> Super Admin</span>
        <span class="pill"><span class="pill-dot"></span> Branch Manager</span>
        <span class="pill"><span class="pill-dot"></span> Operations Officer</span>
        <span class="pill"><span class="pill-dot"></span> Staff</span>
    </div>

    <?php if ($error): ?>
    <div class="error-box">
        <i class="fas fa-triangle-exclamation"></i>
        <span><?= htmlspecialchars($error) ?></span>
    </div>
    <?php endif; ?>

    <form method="POST" action="" id="loginForm" novalidate>
        <?php $csrf = generateCSRFToken(); ?>
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">

        <div class="field">
            <label class="flabel" for="identifier">
                Username or Email
                <span style="color:var(--gold-mid);margin-left:2px">*</span>
            </label>
            <div class="input-wrap">
                <input
                    type="text"
                    class="finput <?= $error ? 'err' : '' ?>"
                    name="identifier"
                    id="identifier"
                    placeholder="username or email address"
                    value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                    required
                    autocomplete="username"
                    spellcheck="false"
                >
                <i class="fas fa-user ficon"></i>
            </div>
        </div>

        <div class="field">
            <label class="flabel" for="pw1">
                Password
                <span style="color:var(--gold-mid);margin-left:2px">*</span>
            </label>
            <div class="input-wrap">
                <input
                    type="password"
                    class="finput <?= $error ? 'err' : '' ?>"
                    name="password"
                    id="pw1"
                    placeholder="enter your password"
                    required
                    autocomplete="current-password"
                    style="padding-right:2.4rem"
                >
                <i class="fas fa-lock ficon"></i>
                <button type="button" class="eye-btn" id="eyeToggle" aria-label="Toggle password">
                    <i class="fas fa-eye" id="eyeIcon"></i>
                </button>
            </div>
        </div>

        <button type="submit" class="submit-btn" id="submitBtn">
            <span class="btn-inner" id="btnInner">
                <i class="fas fa-arrow-right-to-bracket"></i>
                Sign In to Portal
            </span>
        </button>
         <div class="forgot">
            Forgot your password? <a href="/bpm/auth/forgot_password.php">Reset here</a>
        </div>
    </form>

    <div class="notice">
        <div class="notice-icon"><i class="fas fa-shield-halved"></i></div>
        <div class="notice-text">
            <strong>Authorized Personnel Only</strong>
            This system is restricted to Prosperity Microfinance staff. All sessions are logged and monitored. Unauthorized access may result in disciplinary or legal action.
        </div>
    </div>

    <div class="page-foot">
        &copy; <?= date('Y') ?> Prosperity Microfinance
        <span class="sep">&middot;</span>
        <a href="#">Privacy Policy</a>
        <span class="sep">&middot;</span>
        <a href="#">IT Support</a>
    </div>

</div>
</div>

<script>
    // Password toggle
    const eyeToggle = document.getElementById('eyeToggle');
    const pw1       = document.getElementById('pw1');
    const eyeIcon   = document.getElementById('eyeIcon');

    if (eyeToggle && pw1) {
        eyeToggle.addEventListener('click', () => {
            const show = pw1.type === 'password';
            pw1.type = show ? 'text' : 'password';
            eyeIcon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
    }

    // Auto-focus
    const idField = document.getElementById('identifier');
    if (idField) idField.focus();

    // Clear error state on input
    document.querySelectorAll('.finput').forEach(inp => {
        inp.addEventListener('input', function () { this.classList.remove('err'); });
    });

    // Submit guard + loading state
    const form      = document.getElementById('loginForm');
    const submitBtn = document.getElementById('submitBtn');
    const btnInner  = document.getElementById('btnInner');

    if (form && submitBtn) {
        form.addEventListener('submit', function (e) {
            const id = (document.getElementById('identifier').value || '').trim();
            const pw = (document.getElementById('pw1').value || '');
            if (!id || !pw) {
                e.preventDefault();
                document.querySelectorAll('.finput').forEach(inp => {
                    if (!inp.value.trim()) inp.classList.add('err');
                });
                return;
            }
            submitBtn.disabled = true;
            btnInner.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Authenticating…';
            setTimeout(() => {
                submitBtn.disabled = false;
                btnInner.innerHTML = '<i class="fas fa-arrow-right-to-bracket"></i> Sign In to Portal';
            }, 6000);
        });
    }
</script>
</body>
</html>