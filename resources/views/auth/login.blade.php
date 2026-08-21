<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ColdTrace Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    {{-- Modern dashboard font --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            box-sizing: border-box;
        }

        :root {
            --navy: #0f172a;
            --navy-soft: #1e293b;
            --blue: #2563eb;
            --blue-dark: #1d4ed8;
            --cyan: #38bdf8;
            --ice: #e0f2fe;
            --body-bg: #f4f7fb;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e5e7eb;
            --danger: #dc2626;
            --success: #16a34a;
            --warning: #f59e0b;
        }

        html,
        body,
        button,
        input {
            font-family: "Space Grotesk", "Avenir Next", Montserrat, Arial, sans-serif;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(56, 189, 248, 0.25), transparent 32%),
                radial-gradient(circle at bottom right, rgba(37, 99, 235, 0.18), transparent 35%),
                linear-gradient(135deg, #f8fafc, #e0f2fe);
            color: var(--text-main);
        }

        .login-page {
            min-height: 100vh;
            display: grid;
            grid-template-columns: 1.05fr 0.95fr;
        }

        /*
        |--------------------------------------------------------------------------
        | Left Brand Panel
        |--------------------------------------------------------------------------
        */

        .brand-panel {
            background:
                linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(30, 64, 175, 0.92)),
                url("data:image/svg+xml,%3Csvg width='120' height='120' viewBox='0 0 120 120' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%2338bdf8' stroke-opacity='0.16'%3E%3Cpath d='M20 20h80v80H20z'/%3E%3Cpath d='M0 60h120M60 0v120'/%3E%3Ccircle cx='60' cy='60' r='24'/%3E%3C/g%3E%3C/svg%3E");
            color: #ffffff;
            padding: 56px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .brand-panel::after {
            content: "";
            position: absolute;
            width: 420px;
            height: 420px;
            right: -180px;
            bottom: -180px;
            background: rgba(56, 189, 248, 0.12);
            border-radius: 999px;
        }

        .brand-top {
            position: relative;
            z-index: 2;
        }

        .logo-card {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            border-radius: 18px;
            padding: 8px 12px;
            box-shadow: 0 18px 40px rgba(0, 0, 0, 0.24);
            margin-bottom: 42px;
        }

        .logo-img {
            height: 58px;
            width: auto;
            max-width: 210px;
            object-fit: contain;
            display: block;
        }

        .fallback-logo {
            display: none;
            width: 58px;
            height: 58px;
            border-radius: 16px;
            background: linear-gradient(135deg, var(--cyan), var(--blue));
            color: white;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 22px;
        }

        .brand-panel h1 {
            margin: 0;
            max-width: 620px;
            font-size: 46px;
            line-height: 1.06;
            letter-spacing: -1.4px;
        }

        .brand-panel h1 span {
            color: var(--cyan);
        }

        .brand-panel p {
            margin: 20px 0 0;
            max-width: 560px;
            color: #cbd5e1;
            font-size: 16px;
            line-height: 1.75;
        }

        .feature-grid {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-top: 40px;
            max-width: 620px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.14);
            backdrop-filter: blur(10px);
            border-radius: 18px;
            padding: 18px;
        }

        .feature-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: rgba(56, 189, 248, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            color: var(--cyan);
            font-weight: 800;
        }

        .feature-card strong {
            display: block;
            font-size: 15px;
            margin-bottom: 6px;
        }

        .feature-card small {
            color: #cbd5e1;
            line-height: 1.5;
        }

        .brand-footer {
            position: relative;
            z-index: 2;
            color: #94a3b8;
            font-size: 13px;
            margin-top: 40px;
        }

        /*
        |--------------------------------------------------------------------------
        | Login Panel
        |--------------------------------------------------------------------------
        */

        .form-panel {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 34px;
        }

        .login-card {
            width: 100%;
            max-width: 460px;
            background: rgba(255, 255, 255, 0.92);
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 28px;
            padding: 34px;
            box-shadow: 0 28px 70px rgba(15, 23, 42, 0.14);
            backdrop-filter: blur(12px);
        }

        .mobile-logo {
            display: none;
            justify-content: center;
            margin-bottom: 22px;
        }

        .login-heading {
            margin-bottom: 28px;
        }

        .login-heading .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #eff6ff;
            color: var(--blue);
            border: 1px solid #dbeafe;
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 12px;
            font-weight: 800;
            margin-bottom: 14px;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            background: var(--success);
            border-radius: 999px;
            box-shadow: 0 0 0 5px rgba(22, 163, 74, 0.12);
        }

        .login-heading h2 {
            margin: 0;
            color: var(--text-main);
            font-size: 30px;
            letter-spacing: -0.8px;
        }

        .login-heading p {
            margin: 9px 0 0;
            color: var(--text-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        .error-box {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            padding: 13px 14px;
            border-radius: 14px;
            font-size: 14px;
            margin-bottom: 18px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
            color: #334155;
            font-size: 13px;
            font-weight: 800;
        }

        .input-wrap {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 15px;
            pointer-events: none;
        }

        input[type="email"],
        input[type="password"],
        input[type="text"] {
            width: 100%;
            padding: 14px 46px 14px 42px;
            border: 1px solid #cbd5e1;
            border-radius: 15px;
            font-size: 15px;
            outline: none;
            background: #ffffff;
            color: var(--text-main);
            transition: 0.2s ease;
        }

        input::placeholder {
            color: #94a3b8;
        }

        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="text"]:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.13);
        }

        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #475569;
            border-radius: 10px;
            padding: 6px 8px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
        }

        .toggle-password:hover {
            background: #e2e8f0;
        }

        .remember-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 4px 0 22px;
            font-size: 14px;
            color: #475569;
        }

        .remember-row label {
            display: flex;
            align-items: center;
            gap: 9px;
            margin: 0;
            font-weight: 600;
            cursor: pointer;
        }

        .remember-row input {
            width: 16px;
            height: 16px;
            accent-color: var(--blue);
        }

        .login-button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--blue), var(--blue-dark));
            color: #ffffff;
            border: none;
            border-radius: 15px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 14px 28px rgba(37, 99, 235, 0.26);
            transition: 0.2s ease;
        }

        .login-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 18px 34px rgba(37, 99, 235, 0.32);
        }

        .security-note {
            margin-top: 18px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #64748b;
            border-radius: 14px;
            padding: 13px 14px;
            font-size: 13px;
            line-height: 1.55;
        }

        .demo-box {
            margin-top: 18px;
            border-top: 1px solid #e2e8f0;
            padding-top: 18px;
        }

        .demo-box strong {
            display: block;
            color: #334155;
            font-size: 13px;
            margin-bottom: 10px;
        }

        .demo-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 8px;
        }

        .demo-account {
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            border-radius: 12px;
            padding: 9px 10px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            text-align: left;
            transition: 0.2s ease;
        }

        .demo-account:hover {
            border-color: var(--blue);
            color: var(--blue);
            background: #eff6ff;
        }

        .footer-text {
            text-align: center;
            margin-top: 22px;
            color: #94a3b8;
            font-size: 12px;
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 980px) {
            .login-page {
                grid-template-columns: 1fr;
            }

            .brand-panel {
                display: none;
            }

            .form-panel {
                min-height: 100vh;
                padding: 22px;
            }

            .mobile-logo {
                display: flex;
            }

            .login-card {
                max-width: 480px;
            }
        }

        @media (max-width: 520px) {
            .form-panel {
                padding: 16px;
            }

            .login-card {
                padding: 24px;
                border-radius: 22px;
            }

            .login-heading h2 {
                font-size: 26px;
            }

            .demo-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="login-page">

    {{-- Left branding panel --}}
    <section class="brand-panel">
        <div class="brand-top">
            <div class="logo-card">
                <img
                    src="{{ asset('images/coldtrace-logo.png') }}"
                    alt="ColdTrace Logo"
                    class="logo-img"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                >
                <div class="fallback-logo">CT</div>
            </div>

            <h1>
                Predictive cold-chain monitoring for <span>safer deliveries.</span>
            </h1>

            <p>
                ColdTrace helps administrators monitor GPS location, temperature changes,
                route risks, alerts, and remaining shelf life in one secure dashboard.
            </p>

            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon">GPS</div>
                    <strong>Live Location</strong>
                    <small>Track delivery trucks using real-time GPS telemetry.</small>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">°C</div>
                    <strong>Temperature Safety</strong>
                    <small>Detect unsafe storage temperature during transport.</small>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">RSL</div>
                    <strong>Shelf Life Risk</strong>
                    <small>Estimate remaining shelf life using telemetry records.</small>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">AI</div>
                    <strong>Route Support</strong>
                    <small>Recommend routes based on ETA, traffic, and spoilage risk.</small>
                </div>
            </div>
        </div>

        <div class="brand-footer">
            © {{ date('Y') }} ColdTrace. Cold-chain monitoring and spoilage prediction system.
        </div>
    </section>

    {{-- Login form panel --}}
    <main class="form-panel">
        <div class="login-card">

            <div class="mobile-logo">
                <div class="logo-card">
                    <img
                        src="{{ asset('images/coldtrace-logo.png') }}"
                        alt="ColdTrace Logo"
                        class="logo-img"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                    >
                    <div class="fallback-logo">CT</div>
                </div>
            </div>

            <div class="login-heading">
               

                <h2>Welcome back</h2>
                <p>
                    Sign in to monitor deliveries, alerts, product condition, and live truck telemetry.
                </p>
            </div>

            {{-- Login error messages --}}
            @if ($errors->any())
                <div class="error-box">
                    {{ $errors->first() }}
                </div>
            @endif

            {{-- Login form --}}
            <form method="POST" action="{{ route('login.submit') }}">
                @csrf

                <div class="form-group">
                    <label for="email">Email Address</label>

                    <div class="input-wrap">
                        <span class="input-icon">@</span>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="admin@coldtrace.test"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>

                    <div class="input-wrap">
                        <span class="input-icon">●</span>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            required
                        >

                        <button
                            type="button"
                            class="toggle-password"
                            onclick="togglePassword()"
                        >
                            Show
                        </button>
                    </div>
                </div>

                <div class="remember-row">
                    <label for="remember">
                        <input type="checkbox" id="remember" name="remember">
                        Remember me
                    </label>
                </div>

                <button type="submit" class="login-button">
                    Log In
                </button>
            </form>

       
           

        </div>
    </main>
</div>

<script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleButton = document.querySelector('.toggle-password');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleButton.innerText = 'Hide';
        } else {
            passwordInput.type = 'password';
            toggleButton.innerText = 'Show';
        }
    }

    function fillDemo(email) {
        document.getElementById('email').value = email;
        document.getElementById('password').value = 'password';
    }
</script>

</body>
</html>
