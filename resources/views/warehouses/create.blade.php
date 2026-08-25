@extends('layouts.app')

@section('title', __('Add Warehouse'))
@section('subtitle', __('Register a new warehouse location'))

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('warehouses.index') }}">{{ __('Warehouses') }}</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>{{ __('Add Warehouse') }}</span>
    </div>

    <form action="{{ route('warehouses.store') }}" method="POST" class="card section" style="margin-top: 12px;">
        @csrf
        <div class="card-header">
            <div>
                <h2>{{ __('Warehouse Details') }}</h2>
                <p>{{ __('Fields marked * are required') }}</p>
            </div>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group">
                    <label>{{ __('Warehouse Name *') }}</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. Main Warehouse" required>
                    @error('name')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Location') }}</label>
                    <input type="text" name="location" class="form-control" value="{{ old('location') }}" placeholder="e.g. Obour City, Cairo">
                    @error('location')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group full">
                    <label>{{ __('Description') }}</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="{{ __('Optional notes about this warehouse') }}">{{ old('description') }}</textarea>
                    @error('description')
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
                <a href="{{ route('warehouses.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> {{ __('Save Warehouse') }}</button>
            </div>
        </div>
    </form>

@endsection
