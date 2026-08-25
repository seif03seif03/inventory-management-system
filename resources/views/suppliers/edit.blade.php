@extends('layouts.app')

@section('title', __('Edit Supplier'))
@section('subtitle', __('Update supplier information'))

@section('content')

    <div class="breadcrumb">
        <a href="{{ url('/suppliers') }}">{{ __('Suppliers') }}</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>{{ __('Edit Supplier') }}</span>
    </div>

    <form action="{{ route('suppliers.update', $supplier) }}" method="POST" class="card section" style="margin-top: 12px;">
        @csrf
        @method('PUT')
        <div class="card-header">
            <div>
                <h2>{{ __('Supplier Details') }}</h2>
                <p>{{ __('Fields marked * are required') }}</p>
            </div>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label>{{ __('Supplier Name *') }}</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $supplier->name) }}" required>
                    @error('name')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Phone') }}</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $supplier->phone) }}">
                    @error('phone')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Email') }}</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $supplier->email) }}">
                    @error('email')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group full">
                    <label>{{ __('Address') }}</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address', $supplier->address) }}">
                    @error('address')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Status') }}</label>
                    <label style="display:flex; align-items:center; gap:8px; font-weight:500; height: 36px;">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" {{ old('active', $supplier->active) ? 'checked' : '' }}>
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
                <a href="{{ route('suppliers.index') }}" class="btn btn-secondary">{{ __('Cancel') }}</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> {{ __('Update Supplier') }}</button>
            </div>
        </div>
    </form>

@endsection
