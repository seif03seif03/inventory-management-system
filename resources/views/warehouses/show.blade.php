@extends('layouts.app')

@section('title', __('Warehouse Details'))
@section('subtitle', $warehouse->name . ' — ' . ($warehouse->location ?? 'No location'))

@section('content')

    <div class="breadcrumb">
        <a href="{{ route('warehouses.index') }}">{{ __('Warehouses') }}</a> <i class="fa-solid fa-chevron-right" style="font-size:9px"></i> <span>{{ $warehouse->name }}</span>
    </div>

    <div class="detail-header" style="margin-top: 12px;">
        <div class="cell-with-avatar">
            <div class="avatar-sq" style="width:48px;height:48px;font-size:17px;"><i class="fa-solid fa-warehouse"></i></div>
            <div>
                <h2 style="margin:0;font-size:17px;">{{ $warehouse->name }}</h2>
                @if ($warehouse->active)
                    <span class="badge badge-green">{{ __('Active') }}</span>
                @else
                    <span class="badge badge-gray">{{ __('Inactive') }}</span>
                @endif
            </div>
        </div>
        <div class="row-actions">
            <a href="{{ route('warehouses.edit', $warehouse) }}" class="btn btn-secondary"><i class="fa-regular fa-pen-to-square"></i> {{ __('Edit') }}</a>
            <form action="{{ route('warehouses.destroy', $warehouse) }}" method="POST" style="display:inline;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger-outline" onclick="return confirm('Delete this warehouse?')"><i class="fa-regular fa-trash-can"></i> {{ __('Delete') }}</button>
            </form>
        </div>
    </div>

    <div class="stat-grid section">
        <div class="stat-card">
            <div class="stat-card-top"><div class="stat-icon blue"><i class="fa-solid fa-box"></i></div></div>
            <div class="stat-value">0</div><div class="stat-label">{{ __('Products Stored') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-top"><div class="stat-icon green"><i class="fa-solid fa-boxes-stacked"></i></div></div>
            <div class="stat-value">0</div><div class="stat-label">{{ __('Total Stock Quantity') }}</div>
        </div>
        <div class="stat-card">
            <div class="stat-card-top"><div class="stat-icon gray"><i class="fa-solid fa-location-dot"></i></div></div>
            <div class="stat-value" style="font-size:16px;">{{ $warehouse->location ?? 'N/A' }}</div><div class="stat-label">{{ __('Location') }}</div>
        </div>
    </div>

    @if ($warehouse->description)
        <div class="card section">
            <div class="card-header"><h2>{{ __('Description') }}</h2></div>
            <div class="card-body">
                <p class="cell-muted" style="margin:0;">{{ $warehouse->description }}</p>
            </div>
        </div>
    @endif

@endsection
