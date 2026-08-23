@extends('layouts.app')

@section('title', 'Edit Distributor')
@section('subtitle', 'Update distributor information')

@section('content')

    <div class="breadcrumb">
        <a href="{{ url('/distributors') }}">Distributors</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>Edit Distributor</span>
    </div>

    <form action="{{ route('distributors.update', $distributor) }}" method="POST" class="card section" style="margin-top: 12px;">
        @csrf
        @method('PUT')
        <div class="card-header">
            <div>
                <h2>Distributor Details</h2>
                <p>Fields marked * are required</p>
            </div>
        </div>
        <div class="card-body">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Distributor Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $distributor->name) }}" required>
                    @error('name')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control" value="{{ old('phone', $distributor->phone) }}">
                    @error('phone')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $distributor->email) }}">
                    @error('email')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group full">
                    <label>Address</label>
                    <input type="text" name="address" class="form-control" value="{{ old('address', $distributor->address) }}">
                    @error('address')
                        <span class="cell-muted">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <label style="display:flex; align-items:center; gap:8px; font-weight:500; height: 36px;">
                        <input type="hidden" name="active" value="0">
                        <input type="checkbox" name="active" value="1" {{ old('active', $distributor->active) ? 'checked' : '' }}>
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
                <a href="{{ route('distributors.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Update Distributor</button>
            </div>
        </div>
    </form>

@endsection
