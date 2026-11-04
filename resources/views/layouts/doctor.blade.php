<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — Naumah Clinic Doctor</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sidebar-w:  260px;
            --topbar-h:   60px;

            /* Palette */
            --ink:        #1c2b2a;   /* dark forest - sidebar bg */
            --ink-md:     #263635;   /* sidebar hover */
            --ink-lt:     #2f4241;   /* sidebar active bg */
            --leaf:       #3d7a6e;   /* mid-green accent */
            --sage:       #7ab5a8;   /* light sage */
            --parch:      #f7f3ee;   /* parchment - page bg */
            --cream:      #ffffff;
            --warm-bd:    #e8e2da;   /* warm border */
            --coral:      #e8724a;   /* alert / today accent */
            --coral-lt:   #fdf0eb;
            --amber:      #d4a853;   /* revenue / money */
            --amber-lt:   #fdf6e6;
            --txt:        #2c3a38;
            --txt-md:     #5a6e6c;
            --txt-lt:     #8fa09e;

            /* Sidebar text */
            --s-txt:      rgba(255,255,255,0.75);
            --s-txt-dim:  rgba(255,255,255,0.38);
            --s-active:   #ffffff;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background: var(--parch);
            color: var(--txt);
            font-size: 14px;
            line-height: 1.5;
            overflow-x: hidden;
        }
        .font-display { font-family: 'Cormorant Garamond', serif; }

        /* ── Sidebar ───────────────────────────────────────────────── */
        #sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sidebar-w); height: 100vh;
            background: var(--ink);
            display: flex; flex-direction: column;
            z-index: 50;
            transition: transform .3s cubic-bezier(.4,0,.2,1);
        }

        .sidebar-brand {
            padding: 24px 22px 20px;
            border-bottom: 1px solid rgba(255,255,255,.07);
        }
        .brand-logo {
            display: flex; align-items: center; gap: 10px; margin-bottom: 18px;
        }
        .brand-icon {
            width: 34px; height: 34px; border-radius: 9px;
            background: var(--leaf);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        .brand-name {
            font-size: 1.1rem; font-weight: 600; color: #fff; letter-spacing: -.02em;
        }

        .doctor-chip {
            background: rgba(255,255,255,.06);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 10px;
            padding: 10px 12px;
            display: flex; align-items: center; gap: 10px;
        }
        .doctor-avatar {
            width: 36px; height: 36px; border-radius: 9px;
            background: var(--leaf);
            display: flex; align-items: center; justify-content: center;
            font-size: 1rem; color: white; font-weight: 600; flex-shrink: 0;
        }
        .doctor-chip-name {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1rem; font-weight: 500; color: #fff;
            line-height: 1.2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .doctor-chip-spec {
            font-size: .7rem; color: var(--s-txt-dim); font-weight: 400;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        /* Nav */
        .nav-section {
            padding: 0 12px; margin-top: 8px;
        }
        .nav-label {
            font-size: .65rem; font-weight: 600; letter-spacing: .1em;
            text-transform: uppercase; color: var(--s-txt-dim);
            padding: 14px 10px 6px;
        }
        .nav-item {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 10px; border-radius: 8px;
            color: var(--s-txt); text-decoration: none;
            font-size: .875rem; font-weight: 400;
            transition: all .15s; position: relative;
            cursor: pointer; border: none; background: none; width: 100%; text-align: left;
        }
        .nav-item:hover { background: var(--ink-md); color: #fff; }
        .nav-item.active {
            background: var(--ink-lt); color: #fff; font-weight: 500;
        }
        .nav-item.active::before {
            content: ''; position: absolute; left: 0; top: 50%; transform: translateY(-50%);
            width: 3px; height: 60%; background: var(--sage); border-radius: 0 2px 2px 0;
        }
        .nav-icon { width: 17px; height: 17px; flex-shrink: 0; opacity: .8; }
        .nav-item.active .nav-icon, .nav-item:hover .nav-icon { opacity: 1; }
        .nav-badge {
            margin-left: auto; background: var(--coral);
            color: white; font-size: .65rem; font-weight: 700;
            padding: 1px 6px; border-radius: 20px; line-height: 1.6;
        }
        .nav-badge.green { background: var(--leaf); }

        /* Premium lock */
        .nav-item.locked { opacity: .45; cursor: not-allowed; }
        .nav-item.locked:hover { background: transparent; color: var(--s-txt); }
        .lock-icon { margin-left: auto; width: 12px; height: 12px; opacity: .5; }

        /* Sidebar footer */
        .sidebar-footer {
            margin-top: auto;
            padding: 12px;
            border-top: 1px solid rgba(255,255,255,.07);
        }

        /* ── Topbar ─────────────────────────────────────────────────── */
        #topbar {
            position: fixed; top: 0; left: var(--sidebar-w); right: 0;
            height: var(--topbar-h);
            background: var(--cream);
            border-bottom: 1px solid var(--warm-bd);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 28px; z-index: 40;
            gap: 16px;
        }
        .topbar-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.25rem; font-weight: 500; color: var(--txt);
            letter-spacing: -.01em;
            min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
        }
        .topbar-right { display: flex; align-items: center; gap: 12px; }

        .topbar-btn {
            display: flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 9px;
            border: 1.5px solid var(--warm-bd); background: var(--cream);
            color: var(--txt-md); cursor: pointer; transition: all .15s;
            position: relative; text-decoration: none;
        }
        .topbar-btn:hover { background: var(--parch); border-color: var(--txt-lt); color: var(--txt); }
        .notif-dot {
            position: absolute; top: 5px; right: 5px;
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--coral); border: 1.5px solid white;
        }

        .today-badge {
            display: flex; align-items: center; gap: 7px;
            background: var(--coral-lt); border: 1px solid #f0b99e;
            border-radius: 8px; padding: 5px 12px;
            font-size: .8rem; font-weight: 500; color: var(--coral);
        }
        .today-dot {
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--coral); animation: pulse-ring 2s infinite;
        }
        @keyframes pulse-ring {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.4); opacity: .6; }
        }

        /* ── Main content ───────────────────────────────────────────── */
        #main {
            margin-left: var(--sidebar-w);
            margin-top: var(--topbar-h);
            min-height: calc(100vh - var(--topbar-h));
            padding: 28px;
        }

        /* ── Stat cards ─────────────────────────────────────────────── */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--cream);
            border: 1px solid var(--warm-bd);
            border-radius: 14px;
            padding: 18px 20px;
            position: relative; overflow: hidden;
            transition: transform .15s, box-shadow .15s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 24px rgba(0,0,0,.06); }
        .stat-label {
            font-size: .72rem; font-weight: 600; letter-spacing: .06em;
            text-transform: uppercase; color: var(--txt-lt); margin-bottom: 8px;
        }
        .stat-value {
            font-family: 'Cormorant Garamond', serif;
            font-size: 2.1rem; font-weight: 500; color: var(--txt);
            line-height: 1; margin-bottom: 6px;
        }
        .stat-sub {
            font-size: .75rem; color: var(--txt-md);
            display: flex; align-items: center; gap: 4px;
        }
        .stat-icon {
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            width: 44px; height: 44px; border-radius: 11px;
            display: flex; align-items: center; justify-content: center;
            opacity: .9;
        }
        .stat-trend { font-size: .7rem; font-weight: 600; }
        .stat-trend.up   { color: #10b981; }
        .stat-trend.down { color: var(--coral); }

        /* ── Panels ─────────────────────────────────────────────────── */
        .panel {
            background: var(--cream);
            border: 1px solid var(--warm-bd);
            border-radius: 14px;
            overflow: hidden;
        }
        .panel-head {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--warm-bd);
        }
        .panel-title {
            font-family: 'Cormorant Garamond', serif;
            font-size: 1.125rem; font-weight: 500; color: var(--txt);
            display: flex; align-items: center; gap: 9px;
        }
        .panel-action {
            font-size: .8rem; font-weight: 500; color: var(--leaf);
            text-decoration: none; padding: 5px 10px; border-radius: 7px;
            border: 1px solid transparent; transition: all .15s;
        }
        .panel-action:hover { background: #edf6f4; border-color: #c2ddd8; }

        /* ── Appointment rows ───────────────────────────────────────── */
        .apt-row {
            display: flex; align-items: center; gap: 14px;
            padding: 13px 20px;
            border-bottom: 1px solid var(--warm-bd);
            transition: background .12s;
        }
        .apt-row:last-child { border-bottom: none; }
        .apt-row:hover { background: #faf8f5; }

        .apt-time {
            min-width: 56px; text-align: center;
        }
        .apt-time-val {
            font-size: .875rem; font-weight: 600; color: var(--txt);
            line-height: 1.2;
        }
        .apt-time-period {
            font-size: .65rem; font-weight: 500; color: var(--txt-lt);
            text-transform: uppercase;
        }

        .apt-divider {
            width: 1px; height: 36px; background: var(--warm-bd); flex-shrink: 0;
        }

        .apt-avatar {
            width: 36px; height: 36px; border-radius: 10px;
            background: var(--parch); border: 1px solid var(--warm-bd);
            display: flex; align-items: center; justify-content: center;
            font-size: .875rem; font-weight: 600; color: var(--leaf);
            flex-shrink: 0; text-transform: uppercase;
        }

        .apt-info { flex: 1; min-width: 0; }
        .apt-name {
            font-size: .9rem; font-weight: 500; color: var(--txt);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .apt-meta {
            font-size: .75rem; color: var(--txt-lt);
            display: flex; gap: 8px; align-items: center; margin-top: 2px;
        }
        .apt-meta-dot { width: 3px; height: 3px; border-radius: 50%; background: var(--warm-bd); }

        .apt-status {
            font-size: .7rem; font-weight: 600; padding: 3px 9px;
            border-radius: 20px; white-space: nowrap; flex-shrink: 0;
        }
        .s-confirmed { background: #e8f5f3; color: #1a7a6a; }
        .s-completed { background: #f0f4ff; color: #4361c8; }
        .s-cancelled { background: #fef2f2; color: #dc2626; }
        .s-no_show   { background: #fdf6e6; color: #b07d1a; }
        .s-pending   { background: var(--parch); color: var(--txt-md); }

        .apt-actions {
            display: flex; gap: 5px; flex-shrink: 0;
        }
        .apt-details { display: contents; }
        .apt-btn {
            display: flex; align-items: center; justify-content: center;
            width: 30px; height: 30px; border-radius: 7px; border: 1px solid var(--warm-bd);
            background: var(--cream); color: var(--txt-md); cursor: pointer;
            transition: all .12s; text-decoration: none; font-size: .7rem;
        }
        .apt-btn:hover { background: var(--parch); color: var(--txt); }
        .apt-btn.green:hover { background: #edf6f4; border-color: #b8dbd7; color: var(--leaf); }
        .apt-btn.red:hover   { background: var(--coral-lt); border-color: #f0b99e; color: var(--coral); }

        /* ── Revenue mini chart ─────────────────────────────────────── */
        .bar-chart {
            display: flex; align-items: flex-end;
            gap: 6px; height: 56px; padding: 0 20px 14px;
        }
        .bar-wrap { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: 1; }
        .bar {
            width: 100%; border-radius: 4px 4px 0 0;
            background: var(--sage); opacity: .6;
            transition: opacity .2s, background .2s;
            min-height: 4px;
        }
        .bar.current { background: var(--leaf); opacity: 1; }
        .bar:hover { opacity: 1; }
        .bar-lbl { font-size: .6rem; color: var(--txt-lt); font-weight: 500; }

        /* ── Empty states ───────────────────────────────────────────── */
        .empty-state {
            padding: 36px 20px; text-align: center; color: var(--txt-lt);
        }
        .empty-icon {
            width: 44px; height: 44px; border-radius: 12px;
            background: var(--parch); display: flex; align-items: center;
            justify-content: center; margin: 0 auto 12px;
        }
        .empty-title { font-size: .9rem; font-weight: 500; color: var(--txt-md); margin-bottom: 4px; }
        .empty-sub   { font-size: .8rem; }

        /* ── Prescription pill ──────────────────────────────────────── */
        .rx-row {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 20px; border-bottom: 1px solid var(--warm-bd);
        }
        .rx-row:last-child { border-bottom: none; }
        .rx-icon {
            width: 32px; height: 32px; border-radius: 8px;
            background: var(--parch); display: flex; align-items: center;
            justify-content: center; flex-shrink: 0; color: var(--leaf);
        }
        .rx-info { flex: 1; min-width: 0; }
        .rx-name { font-size: .875rem; font-weight: 500; color: var(--txt); }
        .rx-meta { font-size: .72rem; color: var(--txt-lt); margin-top: 1px; }
        .rx-no-whatsapp {
            width: 7px; height: 7px; border-radius: 50%; background: var(--coral); flex-shrink: 0;
        }

        /* ── Mobile hamburger ───────────────────────────────────────── */
        #mob-toggle {
            display: none; background: none; border: none; cursor: pointer;
            color: var(--txt); padding: 4px;
        }

        /* ── Animations ─────────────────────────────────────────────── */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-in { animation: fadeIn .35s ease forwards; }
        .fade-in-d1 { animation: fadeIn .35s ease .05s forwards; opacity: 0; }
        .fade-in-d2 { animation: fadeIn .35s ease .10s forwards; opacity: 0; }
        .fade-in-d3 { animation: fadeIn .35s ease .15s forwards; opacity: 0; }
        .fade-in-d4 { animation: fadeIn .35s ease .20s forwards; opacity: 0; }

        /* ── Responsive ─────────────────────────────────────────────── */
        @media (max-width: 1024px) {
            .stat-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            :root { --sidebar-w: 260px; --topbar-h: 76px; }
            #sidebar { transform: translateX(-100%); }
            #sidebar.open { transform: translateX(0); box-shadow: 0 0 60px rgba(0,0,0,.4); }
            #main, #topbar { margin-left: 0; left: 0; }
            #topbar { padding: 0 16px; gap: 10px; }
            #topbar > div:first-child { min-width: 0; flex: 1; }
            #mob-toggle { display: flex; }
            .topbar-title { font-size: 1rem; white-space: normal; overflow-wrap: anywhere; line-height: 1.15; }
            .topbar-right { gap: 8px; flex-shrink: 0; }
            .stat-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            #main { padding: 16px; }
            .today-badge { display: none; }
            .apt-row { display:grid; grid-template-columns:56px 1px 36px minmax(0,1fr) auto; column-gap:10px; row-gap:6px; align-items:center; }
            .apt-row .apt-time { grid-column:1; grid-row:1 / span 2; }
            .apt-row .apt-divider { grid-column:2; grid-row:1 / span 2; }
            .apt-row .apt-avatar { grid-column:3; grid-row:1 / span 2; }
            .apt-row .apt-details { display:flex; flex-direction:column; gap:6px; grid-column:4; grid-row:1 / span 3; min-width:0; }
            .apt-row .apt-info { min-width:0; }
            .apt-row .apt-status { align-self:flex-start; }
            .apt-row .apt-actions { justify-content:flex-start; margin-left:0; }
            .apt-row .apt-meta { flex-wrap:wrap; }
            .apt-name { overflow-wrap:anywhere; }
            .dr-row-wrap { flex-wrap: wrap; row-gap: 8px; }
            .dr-row-wrap > *:last-child { margin-left: auto; }
        }
        @media (max-width: 480px) {
            #topbar { padding: 0 14px; gap: 8px; }
            .topbar-right { gap: 8px; }
            .stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .stat-card { min-width: 0; min-height: 132px; padding: 14px 12px; }
            .stat-label { max-width: 78%; line-height: 1.35; }
            .stat-icon { right: 10px; width: 38px; height: 38px; }
            .stat-value { font-size: 1.8rem; }
            .stat-sub { max-width: 82%; line-height: 1.3; }
            .stat-card .stat-label { font-size:.64rem; }
            .stat-card .stat-sub { font-size:.7rem; }
            .panel-head { align-items:flex-start; gap:10px; }
            .panel-title { min-width:0; line-height:1.2; }
            .panel-action { flex-shrink:0; }
            .panel-head .panel-title { flex:1 1 auto; }
            .apt-row { padding:13px 14px; grid-template-columns:70px 36px minmax(0,1fr); grid-template-rows:auto auto auto; grid-template-areas:"time avatar info" "time avatar status" "time avatar actions"; column-gap:10px; row-gap:6px; align-items:start; }
            .apt-row .apt-divider { display:none; }
            .apt-row .apt-time { grid-area:time; min-width:70px; align-self:center; }
            .apt-row .apt-avatar { grid-area:avatar; }
            .apt-row .apt-details { grid-area:info; }
            .apt-row .apt-info { min-width:0; }
            .apt-row .apt-status { justify-self:start; }
            .apt-row .apt-actions { justify-content:flex-start; }
            .apt-row .apt-info { min-width:0; }
            .apt-row .apt-name { white-space:normal; overflow:visible; text-overflow:clip; overflow-wrap:anywhere; }
            .apt-row .apt-meta { min-width:0; overflow:visible; display:flex; flex-wrap:wrap; line-height:1.35; }
            .apt-row .apt-meta > span:first-child { flex:0 0 100%; }
            .apt-row .apt-meta .apt-meta-dot { display:none; }
            .apt-row .apt-meta span { max-width:none; overflow:visible; text-overflow:clip; white-space:normal; overflow-wrap:anywhere; }
            .apt-row .apt-time-val { white-space:nowrap; font-size:.78rem; }
            .apt-row .apt-time-period { white-space:nowrap; font-size:.6rem; }
        }

        @media (max-width: 700px) {
            #main { width:100%; max-width:100%; padding-left:12px; padding-right:12px; }
            .doctor-appointment-row {
                display:grid !important;
                grid-template-columns:70px 38px minmax(0,1fr);
                grid-template-areas:"time avatar info" "time avatar status" "time avatar actions";
                column-gap:10px !important;
                row-gap:7px !important;
                align-items:start !important;
                padding:14px 12px !important;
            }
            .doctor-appointment-row .doctor-appointment-time { grid-area:time; width:70px !important; align-self:center; }
            .doctor-appointment-row .doctor-appointment-avatar { grid-area:avatar; align-self:start; }
            .doctor-appointment-row > .doctor-appointment-info { grid-area:info; min-width:0; }
            .doctor-appointment-row > .doctor-appointment-info > div:first-child { display:flex; flex-direction:column; align-items:flex-start; gap:3px; }
            .doctor-appointment-row > .doctor-appointment-info > div { width:100%; text-align:left; }
            .doctor-appointment-row > .doctor-appointment-info > div:first-child > span { display:block; width:100%; }
            .doctor-appointment-row > .doctor-appointment-info > div:last-child { margin-top:2px; }
            .doctor-appointment-row .doctor-appointment-status { grid-area:status; justify-self:start; }
            .doctor-appointment-row .doctor-appointment-actions { grid-area:actions; justify-content:flex-start; }
            .doctor-appointment-row .doctor-appointment-info > div { min-width:0; }
            .doctor-appointment-row .doctor-appointment-info span { max-width:100%; overflow-wrap:anywhere; }
            .patients-table-wrap { overflow:visible; }
            .patients-table-wrap .dr-table-min {
                min-width:0 !important;
                grid-template-columns:minmax(0,1fr) 104px !important;
                gap:6px 10px !important;
            }
            .patients-table-wrap .dr-table-min > span:nth-child(n+3),
            .patients-table-wrap .dr-table-min > div:nth-child(n+3):not(:last-child) {
                display:none;
            }
            .patients-table-wrap .dr-table-min > span:nth-child(2) {
                grid-column:1;
            }
            .patients-table-wrap .dr-table-min > div:nth-child(2) {
                grid-column:1;
                grid-row:2;
                font-size:.75rem !important;
                padding-left:46px;
            }
            .patients-table-wrap .dr-table-min > div:last-child {
                grid-column:2;
                grid-row:1 / span 2;
                align-self:center;
                width:104px;
                max-width:104px;
            }
            .patients-table-wrap .dr-table-min > div:first-child {
                min-width:0;
            }
            .patients-table-wrap .dr-table-min > div:first-child > div:last-child {
                min-width:0;
                overflow:hidden;
            }
            .patients-table-wrap .dr-table-min > div:first-child > div:last-child > div:nth-child(2) {
                flex-wrap:wrap;
                max-height:none;
                overflow:visible;
                line-height:1.45;
            }
            .patients-table-wrap .dr-table-min > div:last-child a,
            .patients-table-wrap .dr-table-min > div:last-child button {
                width:100%;
                max-width:104px;
                padding-left:7px !important;
                padding-right:7px !important;
                font-size:.68rem !important;
                white-space:normal !important;
                text-align:center;
            }
        }

        /* ── Shared responsive helpers: 2-col content grid + sticky sidebar ── */
        /* Add class="dr-grid-2col" to a `display:grid;grid-template-columns:1fr <px>` container,
           and class="dr-sidebar-sticky" to its `position:sticky` sidebar child. */
        @media (max-width: 900px) {
            .dr-grid-2col { grid-template-columns: 1fr !important; }
            .dr-sidebar-sticky { position: static !important; }
        }
        /* Add class="dr-table-wrap" around a header+rows grid-based table, with
           class="dr-table-min" on the header row and each data row. */
        @media (max-width: 900px) {
            .dr-table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .dr-table-min { min-width: 640px; }
        }

        /* ── Overlay for mobile ─────────────────────────────────────── */
        #overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.4); z-index: 45;
        }
        #overlay.show { display: block; }

        /* ── Scrollbar ──────────────────────────────────────────────── */
        #sidebar { overflow-y: auto; scrollbar-width: thin; scrollbar-color: rgba(255,255,255,.1) transparent; }
        #sidebar::-webkit-scrollbar { width: 4px; }
        #sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 2px; }
    </style>

    @stack('styles')
</head>
<body>

<!-- ── Sidebar ──────────────────────────────────────────────────────────── -->
<aside id="sidebar" x-data>
    <!-- Brand + Doctor chip -->
    <div class="sidebar-brand">
        <div class="brand-logo">
            <div class="brand-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <span class="brand-name">Naumah Clinic</span>
        </div>

        <div class="doctor-chip">
            <div class="doctor-avatar">
                {{ strtoupper(substr(auth()->user()->profile?->full_name ?? 'D', 0, 1)) }}
            </div>
            <div style="min-width:0">
                <div class="doctor-chip-name">
                    {{ auth()->user()->profile?->full_name ?? 'Doctor' }}
                </div>
                <div class="doctor-chip-spec">
                    {{ auth()->user()->doctorProfile?->specialization ?? 'General Medicine' }}
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="nav-section" style="flex:1">
        <div class="nav-label">Main</div>

        <a href="{{ route('doctor.dashboard') }}"
           class="nav-item {{ request()->routeIs('doctor.dashboard') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>

        <a href="{{ route('doctor.appointments.index') }}"
           class="nav-item {{ request()->routeIs('doctor.appointments*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Appointments
            @php $todayCnt = auth()->user()->doctorAppointments()->whereDate('slot_datetime', today())->whereNotIn('status',['cancelled'])->count(); @endphp
            @if($todayCnt > 0)
                <span class="nav-badge">{{ $todayCnt }}</span>
            @endif
        </a>

        <a href="{{ route('doctor.patients.index') }}"
           class="nav-item {{ request()->routeIs('doctor.patients*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            My Patients
        </a>

        <a href="{{ route('doctor.quick-register.create') }}"
           class="nav-item {{ request()->routeIs('doctor.quick-register*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
            </svg>
            Register Patient
        </a>

        <a href="{{ route('doctor.payments.all') }}"
           class="nav-item {{ request()->routeIs('doctor.payments*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            Payments
        </a>

        <a href="{{ route('doctor.records.index') }}"
        </a>

        <a href="{{ route('doctor.records.index') }}"
           class="nav-item {{ request()->routeIs('doctor.records.*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Medical Records
        </a>

        <a href="{{ route('doctor.prescriptions.index') }}"
           class="nav-item {{ request()->routeIs('doctor.prescriptions*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            Prescriptions
        </a>

        <a href="{{ route('doctor.medicines.index') }}"
           class="nav-item {{ request()->routeIs('doctor.medicines*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/>
            </svg>
            Medicine List
        </a>

        <a href="{{ route('doctor.services.index') }}"
           class="nav-item {{ request()->routeIs('doctor.services*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-2.485 0-4.5-1.343-4.5-3s2.015-3 4.5-3m9 10.5a9.5 9.5 0 11-19 0 9.5 9.5 0 0119 0z"/>
            </svg>
            Service List
        </a>

        <div class="nav-label" style="margin-top:4px">Premium</div>

        @if(auth()->user()->doctorProfile?->is_premium)
        <a href="{{ route('doctor.timelines.index') }}"
           class="nav-item {{ request()->routeIs('doctor.timelines*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
            Care Timelines
        </a>
        <a href="{{ route('doctor.analytics.index') }}"
           class="nav-item {{ request()->routeIs('doctor.analytics*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Analytics
        </a>
        @else
        <span class="nav-item locked">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
            Care Timelines
            <svg class="lock-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        </span>
        <span class="nav-item locked">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Analytics
            <svg class="lock-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        </span>
        @endif

        <div class="nav-label" style="margin-top:4px">Account</div>

        <a href="{{ route('doctor.appointments.slots') }}"
           class="nav-item {{ request()->routeIs('doctor.appointments.slots*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            Slot Settings
        </a>

        <a href="{{ route('doctor.subscription.plans') }}"
           class="nav-item {{ request()->routeIs('doctor.subscription*') ? 'active' : '' }}">
            <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
            </svg>
            Subscription
            @if(!auth()->user()->doctorProfile?->is_premium)
                <span class="nav-badge" style="background:var(--amber);font-size:.6rem">Upgrade</span>
            @endif
        </a>
    </nav>

    <!-- Sidebar footer: logout -->
    <div class="sidebar-footer">
        <form method="POST" action="{{ route('auth.logout') }}">
            @csrf
            <button type="submit" class="nav-item" style="color:rgba(255,255,255,.45)">
                <svg class="nav-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Sign Out
            </button>
        </form>
    </div>
</aside>

<!-- Mobile overlay -->
<div id="overlay" onclick="closeSidebar()"></div>

<!-- ── Topbar ──────────────────────────────────────────────────────────── -->
<header id="topbar">
    <div style="display:flex;align-items:center;gap:14px">
        <!-- Mobile toggle -->
        <button id="mob-toggle" onclick="toggleSidebar()" aria-label="Menu">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        <h1 class="topbar-title">@yield('page-title', 'Dashboard')</h1>
    </div>

    <div class="topbar-right">
        <!-- Today's appointment count -->
        @php $todayCount = auth()->user()->doctorAppointments()->whereDate('slot_datetime',today())->whereNotIn('status',['cancelled'])->count() @endphp
        @if($todayCount > 0)
        <div class="today-badge">
            <div class="today-dot"></div>
            {{ $todayCount }} today
        </div>
        @endif

        <!-- Notifications -->
        @include('partials.notification-bell')

        <!-- Profile -->
        <a href="{{ route('doctor.profile.edit') }}" class="topbar-btn" title="Profile">
            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
        </a>
    </div>
</header>

<!-- ── Page content ───────────────────────────────────────────────────── -->
<main id="main">

    {{-- Flash messages --}}
    @if(session('success'))
    <div style="display:flex;align-items:center;gap:9px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:11px 14px;font-size:.875rem;color:#065f46;margin-bottom:18px">
        <svg width="15" height="15" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div style="display:flex;align-items:center;gap:9px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:11px 14px;font-size:.875rem;color:#991b1b;margin-bottom:18px">
        <svg width="15" height="15" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        {{ session('error') }}
    </div>
    @endif

    @yield('content')
</main>

<x-attachment-preview-modal />

<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('overlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('overlay').classList.remove('show');
}
</script>
@stack('scripts')
</body>
</html>
