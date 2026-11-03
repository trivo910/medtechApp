<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'My Health') — Naumah Clinic</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Lora:ital,wght@0,400;0,500;0,600;1,400&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --sw: 256px;
            --th: 58px;

            /* Patient palette — warm, calm, personal */
            --plum:     #4a3760;   /* sidebar bg */
            --plum-md:  #5a4572;   /* sidebar hover */
            --plum-lt:  #6b548a;   /* sidebar active */
            --rose:     #c0737a;   /* accent rose */
            --rose-lt:  #f9eff0;   /* rose tint */
            --mauve:    #a893c0;   /* light mauve */
            --cream:    #fffcf8;   /* page bg */
            --white:    #ffffff;
            --warm-bd:  #ede8e0;   /* warm border */
            --sand:     #f4efe8;   /* card bg */
            --sage:     #6a9e8e;   /* health green */
            --sage-lt:  #eef5f3;
            --amber:    #c98a3a;   /* warning / meds */
            --amber-lt: #fdf5e8;
            --sky:      #4f87b0;   /* appointment blue */
            --sky-lt:   #eff5fb;
            --txt:      #2d2535;
            --txt-md:   #6b6278;
            --txt-lt:   #a89fb8;

            /* Sidebar text */
            --st:       rgba(255,255,255,.72);
            --st-dim:   rgba(255,255,255,.34);
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--cream);
            color: var(--txt);
            font-size: 14px;
            line-height: 1.5;
        }
        .font-serif { font-family: 'Lora', serif; }

        /* ── Sidebar ─────────────────────────────────────────────────────── */
        #sidebar {
            position: fixed; top: 0; left: 0;
            width: var(--sw); height: 100vh;
            background: var(--plum);
            display: flex; flex-direction: column;
            z-index: 50; overflow-y: auto;
            scrollbar-width: none;
            transition: transform .3s cubic-bezier(.4,0,.2,1);
        }
        #sidebar::-webkit-scrollbar { display: none; }

        .sb-brand {
            padding: 22px 20px 18px;
            border-bottom: 1px solid rgba(255,255,255,.07);
        }
        .sb-logo { display: flex; align-items: center; gap: 9px; margin-bottom: 16px; }
        .sb-icon {
            width: 32px; height: 32px; border-radius: 9px;
            background: var(--rose); display: flex; align-items: center;
            justify-content: center; flex-shrink: 0;
        }
        .sb-brand-name {
            font-size: 1rem; font-weight: 600; color: #fff; letter-spacing: -.02em;
        }

        .patient-chip {
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 12px; padding: 10px 12px;
            display: flex; align-items: center; gap: 10px;
        }
        .p-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: var(--rose); display: flex; align-items: center;
            justify-content: center; font-size: .95rem; font-weight: 700;
            color: #fff; flex-shrink: 0;
        }
        .p-name {
            font-family: 'Lora', serif;
            font-size: .95rem; font-weight: 500; color: #fff;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .p-age { font-size: .7rem; color: var(--st-dim); }

        /* Nav */
        .nav-sect { padding: 4px 12px; }
        .nav-lbl {
            font-size: .62rem; font-weight: 600; letter-spacing: .1em;
            text-transform: uppercase; color: var(--st-dim);
            padding: 14px 10px 5px;
        }
        .nav-a {
            display: flex; align-items: center; gap: 10px;
            padding: 9px 10px; border-radius: 9px;
            color: var(--st); text-decoration: none;
            font-size: .875rem; font-weight: 400;
            transition: all .15s; position: relative;
            cursor: pointer; border: none; background: none; width: 100%;
        }
        .nav-a:hover { background: var(--plum-md); color: #fff; }
        .nav-a.on {
            background: var(--plum-lt); color: #fff; font-weight: 500;
        }
        .nav-a.on::before {
            content: ''; position: absolute; left: 0; top: 50%;
            transform: translateY(-50%); width: 3px; height: 55%;
            background: var(--mauve); border-radius: 0 2px 2px 0;
        }
        .nav-ic { width: 17px; height: 17px; flex-shrink: 0; opacity: .75; }
        .nav-a.on .nav-ic, .nav-a:hover .nav-ic { opacity: 1; }
        .nav-bd {
            margin-left: auto; background: var(--rose); color: #fff;
            font-size: .62rem; font-weight: 700;
            padding: 1px 6px; border-radius: 20px; line-height: 1.6;
        }

        .sb-footer {
            margin-top: auto; padding: 12px;
            border-top: 1px solid rgba(255,255,255,.07);
        }

        /* ── Topbar ──────────────────────────────────────────────────────── */
        #topbar {
            position: fixed; top: 0; left: var(--sw); right: 0;
            height: var(--th); background: var(--white);
            border-bottom: 1px solid var(--warm-bd);
            display: flex; align-items: center;
            justify-content: space-between;
            padding: 0 24px; z-index: 40; gap: 14px;
        }
        .tb-title {
            font-family: 'Lora', serif;
            font-size: 1.15rem; font-weight: 500; color: var(--txt);
            min-width: 0;
            line-height: 1.2;
        }
        .tb-right { display: flex; align-items: center; gap: 10px; }
        .tb-btn {
            display: flex; align-items: center; justify-content: center;
            width: 34px; height: 34px; border-radius: 9px;
            border: 1.5px solid var(--warm-bd); background: var(--white);
            color: var(--txt-md); cursor: pointer;
            transition: all .15s; text-decoration: none; position: relative;
        }
        .tb-btn:hover { background: var(--sand); color: var(--txt); }
        .tb-dot {
            position: absolute; top: 4px; right: 4px;
            width: 7px; height: 7px; border-radius: 50%;
            background: var(--rose); border: 1.5px solid #fff;
        }

        .next-apt-chip {
            display: flex; align-items: center; gap: 7px;
            background: var(--sky-lt); border: 1px solid #c2d9ec;
            border-radius: 8px; padding: 5px 12px;
            font-size: .8rem; font-weight: 500; color: var(--sky);
            white-space: nowrap;
        }

        /* ── Main ────────────────────────────────────────────────────────── */
        #main {
            margin-left: var(--sw);
            margin-top: var(--th);
            padding: 24px;
            min-height: calc(100vh - var(--th));
        }

        /* ── Health stats row ────────────────────────────────────────────── */
        .health-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }
        .h-card {
            background: var(--white);
            border: 1px solid var(--warm-bd);
            border-radius: 14px; padding: 16px 18px;
            display: flex; align-items: center; gap: 14px;
            transition: box-shadow .15s, transform .15s;
        }
        .h-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.06); transform: translateY(-1px); }
        .h-ring {
            position: relative; width: 52px; height: 52px; flex-shrink: 0;
        }
        .h-ring svg { transform: rotate(-90deg); }
        .h-ring-val {
            position: absolute; inset: 0;
            display: flex; align-items: center; justify-content: center;
            font-size: .7rem; font-weight: 700; color: var(--txt);
            line-height: 1;
        }
        .h-info { min-width: 0; }
        .h-label {
            font-size: .68rem; font-weight: 600; letter-spacing: .06em;
            text-transform: uppercase; color: var(--txt-lt); margin-bottom: 3px;
        }
        .h-value {
            font-family: 'Lora', serif;
            font-size: 1.2rem; font-weight: 500; color: var(--txt);
            line-height: 1.1;
        }
        .h-date { font-size: .7rem; color: var(--txt-lt); margin-top: 2px; }
        .h-status {
            font-size: .68rem; font-weight: 600; padding: 2px 7px;
            border-radius: 20px; display: inline-block; margin-top: 3px;
        }
        .hs-ok   { background: var(--sage-lt); color: #3a7a6a; }
        .hs-warn { background: var(--amber-lt); color: #8a5a1a; }
        .hs-hi   { background: var(--rose-lt); color: #8a3a40; }
        .hs-na   { background: var(--sand); color: var(--txt-lt); }

        /* ── Panels ──────────────────────────────────────────────────────── */
        .panel {
            background: var(--white);
            border: 1px solid var(--warm-bd);
            border-radius: 14px; overflow: hidden;
        }
        .ph {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 18px; border-bottom: 1px solid var(--warm-bd);
        }
        .ph-title {
            font-family: 'Lora', serif;
            font-size: 1.05rem; font-weight: 500; color: var(--txt);
            display: flex; align-items: center; gap: 8px;
        }
        .ph-link {
            font-size: .8rem; font-weight: 500; color: var(--plum);
            text-decoration: none; padding: 4px 9px; border-radius: 7px;
            border: 1px solid transparent; transition: all .15s;
        }
        .ph-link:hover { background: #f2eef8; border-color: #d4c8e8; }

        /* ── Appointment rows ────────────────────────────────────────────── */
        .apt-r {
            display: flex; align-items: center; gap: 12px;
            padding: 13px 18px; border-bottom: 1px solid var(--warm-bd);
            transition: background .12s;
        }
        .apt-r:last-child { border-bottom: none; }
        .apt-r:hover { background: var(--sand); }

        .dr-avatar {
            width: 38px; height: 38px; border-radius: 50%;
            background: var(--plum-lt); display: flex; align-items: center;
            justify-content: center; font-size: .875rem; font-weight: 700;
            color: #fff; flex-shrink: 0; text-transform: uppercase;
        }
        .apt-info { flex: 1; min-width: 0; }
        .apt-dr-name {
            font-size: .9rem; font-weight: 600; color: var(--txt);
        }
        .apt-meta {
            font-size: .75rem; color: var(--txt-lt);
            margin-top: 2px; display: flex; gap: 7px; align-items: center; flex-wrap: wrap;
        }
        .apt-dot { width: 3px; height: 3px; border-radius: 50%; background: var(--warm-bd); }
        .apt-date-badge {
            font-size: .75rem; font-weight: 600; color: var(--sky);
            background: var(--sky-lt); border-radius: 7px;
            padding: 3px 9px; white-space: nowrap; flex-shrink: 0;
        }
        .apt-date-badge.soon { color: var(--rose); background: var(--rose-lt); }

        /* ── Family member cards ─────────────────────────────────────────── */
        .fam-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
            gap: 10px; padding: 16px 18px;
        }
        .fam-card {
            border: 1.5px solid var(--warm-bd); border-radius: 12px;
            padding: 14px 10px; text-align: center;
            transition: all .15s; text-decoration: none; cursor: pointer;
            background: var(--white);
        }
        .fam-card:hover { border-color: var(--mauve); background: #faf7ff; transform: translateY(-1px); }
        .fam-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem; font-weight: 700; color: #fff;
            margin: 0 auto 8px; font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .fam-name {
            font-size: .8rem; font-weight: 600; color: var(--txt);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .fam-rel {
            font-size: .68rem; color: var(--txt-lt); margin-top: 2px;
            text-transform: capitalize;
        }
        .fam-sub-id {
            font-size: .6rem; color: var(--mauve); font-family: monospace;
            margin-top: 4px; letter-spacing: .02em;
        }
        .fam-add-card {
            border: 1.5px dashed var(--warm-bd); border-radius: 12px;
            padding: 14px 10px; text-align: center;
            background: transparent; transition: all .15s;
            text-decoration: none; display: block;
        }
        .fam-add-card:hover { border-color: var(--mauve); background: #faf7ff; }

        /* ── Medication pills ────────────────────────────────────────────── */
        .med-r {
            display: flex; align-items: center; gap: 11px;
            padding: 11px 18px; border-bottom: 1px solid var(--warm-bd);
        }
        .med-r:last-child { border-bottom: none; }
        .med-ic {
            width: 32px; height: 32px; border-radius: 9px;
            background: var(--amber-lt); display: flex; align-items: center;
            justify-content: center; flex-shrink: 0;
        }
        .med-name { font-size: .875rem; font-weight: 500; color: var(--txt); }
        .med-meta { font-size: .72rem; color: var(--txt-lt); margin-top: 1px; }
        .med-times { display: flex; gap: 4px; flex-wrap: wrap; margin-left: auto; }
        .med-time-pill {
            font-size: .65rem; font-weight: 600; padding: 2px 7px;
            border-radius: 20px; background: var(--sand); color: var(--txt-md);
        }

        /* ── Timeline progress bar ───────────────────────────────────────── */
        .tl-card {
            padding: 14px 18px;
            border-bottom: 1px solid var(--warm-bd);
        }
        .tl-card:last-child { border-bottom: none; }
        .tl-header { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
        .tl-icon {
            width: 34px; height: 34px; border-radius: 9px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; font-size: 1rem;
        }
        .tl-title { font-size: .9rem; font-weight: 600; color: var(--txt); }
        .tl-sub   { font-size: .75rem; color: var(--txt-lt); margin-top: 1px; }
        .tl-bar-bg {
            height: 6px; background: var(--sand);
            border-radius: 3px; overflow: hidden; margin-bottom: 6px;
        }
        .tl-bar-fill { height: 100%; border-radius: 3px; transition: width .6s ease; }
        .tl-progress-row {
            display: flex; justify-content: space-between;
            font-size: .72rem; color: var(--txt-lt);
        }

        /* ── Pending OTP alert ───────────────────────────────────────────── */
        .otp-alert {
            background: linear-gradient(135deg, #fdf0eb 0%, #fdf5f5 100%);
            border: 1.5px solid #f0b99e;
            border-radius: 12px; padding: 14px 16px;
            margin-bottom: 20px;
            display: flex; align-items: flex-start; gap: 12px;
        }
        .otp-alert-ic {
            width: 36px; height: 36px; border-radius: 10px;
            background: #fff; display: flex; align-items: center;
            justify-content: center; flex-shrink: 0;
            border: 1px solid #f0b99e;
        }

        /* ── Empty states ────────────────────────────────────────────────── */
        .empty {
            padding: 28px 18px; text-align: center; color: var(--txt-lt);
        }
        .empty-ic {
            width: 42px; height: 42px; border-radius: 12px;
            background: var(--sand); display: flex; align-items: center;
            justify-content: center; margin: 0 auto 10px;
        }
        .empty-t { font-size: .875rem; font-weight: 500; color: var(--txt-md); }
        .empty-s { font-size: .78rem; margin-top: 3px; }

        /* ── Active doctor chips ─────────────────────────────────────────── */
        .dr-chip {
            display: flex; align-items: center; gap: 10px;
            padding: 11px 18px; border-bottom: 1px solid var(--warm-bd);
        }
        .dr-chip:last-child { border-bottom: none; }
        .dr-chip-spec {
            font-size: .72rem; font-weight: 500; padding: 2px 8px;
            border-radius: 20px; background: var(--sage-lt); color: #2a6a5a;
            flex-shrink: 0;
        }
        .dr-expiry { font-size: .7rem; color: var(--txt-lt); margin-left: auto; }

        /* ── Mobile ──────────────────────────────────────────────────────── */
        #mob-btn {
            display: none; background: none; border: none;
            cursor: pointer; color: var(--txt); padding: 4px;
        }
        #overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.38); z-index: 45;
        }
        #overlay.show { display: block; }

        /* ── Animations ──────────────────────────────────────────────────── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fu   { animation: fadeUp .32s ease both; }
        .fu-1 { animation: fadeUp .32s ease .06s both; opacity: 0; }
        .fu-2 { animation: fadeUp .32s ease .12s both; opacity: 0; }
        .fu-3 { animation: fadeUp .32s ease .18s both; opacity: 0; }

        /* ── Responsive ──────────────────────────────────────────────────── */
        @media (max-width: 1100px) { .health-row { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.open { transform: translateX(0); box-shadow: 0 0 60px rgba(0,0,0,.35); }
            #main, #topbar { margin-left: 0; left: 0; }
            #mob-btn { display: flex; }
            .next-apt-chip { display: none; }
            #topbar { padding: 0 14px; gap: 8px; }
            #topbar > div:first-child { min-width: 0; flex: 1; }
            .tb-title { font-size: 1rem; overflow-wrap: anywhere; }
            .tb-right { gap: 6px; flex-shrink: 0; }
            #main { padding: 14px; overflow-x: hidden; }
            .health-row { grid-template-columns: repeat(2, 1fr); gap: 10px; }
        }
        @media (max-width: 480px) {
            .health-row { grid-template-columns: 1fr 1fr; }
        }
    </style>
    @stack('styles')
</head>
<body>

<!-- ── Sidebar ────────────────────────────────────────────────────────────── -->
<aside id="sidebar">
    <div class="sb-brand">
        <div class="sb-logo">
            <div class="sb-icon">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                </svg>
            </div>
            <span class="sb-brand-name">Naumah Clinic</span>
        </div>

        <div class="patient-chip">
            <div class="p-avatar">
                {{ strtoupper(substr(auth()->user()->profile?->full_name ?? 'P', 0, 1)) }}
            </div>
            <div style="min-width:0">
                <div class="p-name">{{ auth()->user()->profile?->full_name ?? 'My Account' }}</div>
                <div class="p-age">
                    @if(auth()->user()->profile?->age)
                        Age {{ auth()->user()->profile->age }}
                        @if(auth()->user()->profile?->blood_group) · {{ auth()->user()->profile->blood_group }} @endif
                    @else
                        Patient Account
                    @endif
                </div>
            </div>
        </div>
    </div>

    <nav class="nav-sect" style="flex:1">
        <div class="nav-lbl">Health</div>

        <a href="{{ route('patient.dashboard') }}"
           class="nav-a {{ request()->routeIs('patient.dashboard') ? 'on' : '' }}">
            <svg class="nav-ic" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            My Dashboard
        </a>

        <a href="{{ route('patient.history.index') }}"
           class="nav-a {{ request()->routeIs('patient.history*') ? 'on' : '' }}">
            <svg class="nav-ic" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            Medical History
        </a>

        <a href="{{ route('patient.appointments.index') }}"
           class="nav-a {{ request()->routeIs('patient.appointments*') ? 'on' : '' }}">
            <svg class="nav-ic" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Appointments
            @php $upCnt = auth()->user()->appointments()->where('slot_datetime','>',now())->whereNotIn('status',['cancelled'])->count() @endphp
            @if($upCnt > 0)
                <span class="nav-bd">{{ $upCnt }}</span>
            @endif
        </a>

        <a href="{{ route('patient.health.index') }}"
           class="nav-a {{ request()->routeIs('patient.health*') ? 'on' : '' }}">
            <svg class="nav-ic" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            Health Tracker
        </a>

        <a href="{{ route('patient.reminders.index') }}"
           class="nav-a {{ request()->routeIs('patient.reminders*') ? 'on' : '' }}">
            <svg class="nav-ic" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
            Medications
        </a>

        <div class="nav-lbl">Family</div>

        <a href="{{ route('patient.family.index') }}"
           class="nav-a {{ request()->routeIs('patient.family*') ? 'on' : '' }}">
            <svg class="nav-ic" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Family Members
            @php $famCnt = auth()->user()->familyMembers()->where('is_delinked',false)->where('relation', '!=', 'self')->count() @endphp
            @if($famCnt > 0)
                <span class="nav-bd" style="background:var(--mauve)">{{ $famCnt }}</span>
            @endif
        </a>

        <a href="{{ route('patient.payments.index') }}"
           class="nav-a {{ request()->routeIs('patient.payments*') ? 'on' : '' }}">
            <svg class="nav-ic" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            Payments
        </a>

        <div class="nav-lbl">Privacy</div>

        <a href="{{ route('patient.access.index') }}"
           class="nav-a {{ request()->routeIs('patient.access*') ? 'on' : '' }}">
            <svg class="nav-ic" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
            Doctor Access
            @php $pendCnt = auth()->user()->accessRequests()->where('status','pending')->where('otp_expires_at','>',now())->count() @endphp
            @if($pendCnt > 0)
                <span class="nav-bd">{{ $pendCnt }}</span>
            @endif
        </a>

        <a href="{{ route('patient.timelines.index') }}"
           class="nav-a {{ request()->routeIs('patient.timelines*') ? 'on' : '' }}">
            <svg class="nav-ic" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
            </svg>
            Care Timelines
        </a>
    </nav>

    <div class="sb-footer">
        <form method="POST" action="{{ route('auth.logout') }}">
            @csrf
            <button type="submit" class="nav-a" style="color:rgba(255,255,255,.38)">
                <svg class="nav-ic" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
                Sign Out
            </button>
        </form>
    </div>
</aside>

<div id="overlay" onclick="closeSb()"></div>

<!-- ── Topbar ──────────────────────────────────────────────────────────────── -->
<header id="topbar">
    <div style="display:flex;align-items:center;gap:12px">
        <button id="mob-btn" onclick="toggleSb()">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <h1 class="tb-title">@yield('page-title', 'My Health')</h1>
    </div>
    <div class="tb-right">
        @yield('topbar-extras')
        @include('partials.notification-bell')
        <a href="{{ route('patient.appointments.book') }}" class="next-apt-chip" style="text-decoration:none">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
            </svg>
            Book Appointment
        </a>

        <a href="{{ route('patient.access.index') }}" class="tb-btn" title="Doctor Access">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
            @php $pc = auth()->user()->accessRequests()->where('status','pending')->where('otp_expires_at','>',now())->count() @endphp
            @if($pc > 0)<span class="tb-dot"></span>@endif
        </a>


        <a href="{{ route('patient.profile.edit') }}" class="tb-btn" title="Settings">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><circle cx="12" cy="12" r="3"/>
            </svg>
        </a>
    </div>
</header>

<!-- ── Content ─────────────────────────────────────────────────────────────── -->
<main id="main">
    @if(session('success'))
    <div style="display:flex;align-items:center;gap:9px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;padding:10px 14px;font-size:.875rem;color:#065f46;margin-bottom:16px">
        <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div style="display:flex;align-items:center;gap:9px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:10px 14px;font-size:.875rem;color:#991b1b;margin-bottom:16px">
        <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
        {{ session('error') }}
    </div>
    @endif

    @yield('content')
</main>

<x-attachment-preview-modal />

<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
function toggleSb(){ document.getElementById('sidebar').classList.toggle('open'); document.getElementById('overlay').classList.toggle('show'); }
function closeSb(){ document.getElementById('sidebar').classList.remove('open'); document.getElementById('overlay').classList.remove('show'); }
</script>
@stack('scripts')
</body>
</html>
