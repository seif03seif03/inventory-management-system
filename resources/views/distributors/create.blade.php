@extends('layouts.app')

@section('title', __('Add Distributor'))
@section('subtitle', __('Register a new distributor'))

@section('content')

    <div class="breadcrumb">
        <a href="{{ url('/distributors') }}">{{ __('Distributors') }}</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>{{ __('Add Distributor') }}</span>
    </div>

    <form action="{{ route('distributors.store') }}" method="POST" class="card section" style="margin-top: 12px;">
        @csrf
        <div class="card-header">
            <div>
                <h2>{{ __('Distributor Details') }}</h2>
                <p>{{ __('Fields marked * are required') }}</p>
            </div>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label>{{ __('Distributor Name *') }}</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. Mona Adel" required>
                    @error('name')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Phone') }}</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="+20 1XX XXX XXXX">
                    @error('phone')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Email') }}</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="name@company.com">
                    @error('email')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group full">
                    <label>{{ __('Address') }}</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address') }}" placeholder="{{ __('Street, City, Country') }}">
                    @error('address')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Status') }}</label>
                    <label style="display:flex; align-items:center; gap:8px; font-weight:500; height: 36px;">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" {{ old('active', true) ? 'checked' : '' }}>
                        Active
                    </label>
                    @error('active')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
            </div>
        </div>
        <div class="card-body" style="padding-top:0;">
            <div class="form-actions">
                <a href="{{ route('distributors.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> {{ __('Save Distributor') }}</button>
            </div>
        </div>
    </form>

@endsection
