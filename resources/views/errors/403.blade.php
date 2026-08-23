@extends('layouts.app')

@section('title', '403 Forbidden')
@section('subtitle', 'Access Restricted')

@section('content')
    <div class="card" style="max-width: 540px; margin: 40px auto; text-align: center;">
        <div class="card-body" style="padding: 48px 32px;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--color-danger-soft); color: var(--color-danger); display: inline-flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 20px;">
                <i class="fa-solid fa-lock"></i>
            </div>

            <h2 style="font-size: 22px; font-weight: 700; margin: 0 0 10px; color: var(--color-text);">
                403 Forbidden
            </h2>

            <p style="font-size: 14px; color: var(--color-text-muted); margin: 0 0 24px; line-height: 1.6;">
                {{ $exception->getMessage() ?: 'You do not have permission to access this page or perform this action.' }}
            </p>

            <div style="display: flex; gap: 12px; justify-content: center;">
                <a href="{{ route('dashboard') }}" class="btn btn-primary">
                    <i class="fa-solid fa-house"></i> Return to Dashboard
                </a>
            </div>
        </div>
    </div>
@endsection
