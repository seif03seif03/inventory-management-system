<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Inventory Management</title>

    {{-- Fonts & icons --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    {{-- App stylesheet --}}
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    <style>
        .auth-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--color-bg);
            padding: 20px;
        }

        .auth-card {
            width: 100%;
            max-width: 400px;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: var(--radius-lg);
            padding: 32px 28px;
            box-shadow: var(--shadow-md);
        }

        .auth-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 24px;
        }

        .auth-brand-mark {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            background: var(--color-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
            font-size: 16px;
        }

        .auth-brand-text strong {
            display: block;
            color: var(--color-text);
            font-size: 16px;
            font-weight: 700;
        }

        .auth-brand-text span {
            display: block;
            font-size: 11px;
            color: var(--color-text-muted);
            letter-spacing: 0.5px;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .auth-header h1 {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 6px;
            color: var(--color-text);
        }

        .auth-header p {
            font-size: 13px;
            color: var(--color-text-muted);
            margin: 0;
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
    </style>
</head>
<body>

    <div class="auth-wrapper">
        <div class="auth-card">

            <div class="auth-brand">
                <div class="auth-brand-mark">IM</div>
                <div class="auth-brand-text">
                    <strong>{{ __('Inventory') }}</strong>
                    <span>{{ __('MANAGEMENT') }}</span>
                </div>
            </div>

            <div class="auth-header">
                <h1>{{ __('Sign in to your account') }}</h1>
                <p>{{ __('Enter your credentials to access the system') }}</p>
            </div>

            @if (session('info'))
                <div class="alert alert-info">
                    <i class="fa-solid fa-circle-info"></i>
                    <div>{{ session('info') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <div>
                        @foreach ($errors->all() as $error)
                            <div>{{ $error }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <form action="{{ route('login.post') }}" method="POST" class="auth-form">
                @csrf

                <div class="form-group">
                    <label for="email">{{ __('Email Address') }}</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        class="form-control"
                        placeholder="admin@example.com"
                        required
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label for="password">{{ __('Password') }}</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="••••••••"
                        required
                    >
                </div>

                <label class="form-check">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    <span>{{ __('Remember me on this device') }}</span>
                </label>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 11px;">
                    <i class="fa-solid fa-right-to-bracket"></i> {{ __('Sign In') }}
                </button>
            </form>

        </div>
    </div>

</body>
</html>
