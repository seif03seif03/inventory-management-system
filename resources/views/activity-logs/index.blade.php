@extends('layouts.app')

@section('title', __('Activity Logs'))
@section('subtitle', __('Audit trail of record changes'))

@section('content')

    <div class="card">
        <div class="card-header">
            <div>
                <h2>{{ __('Activity Logs') }}</h2>
                <p>{{ $logs->total() }} {{ __('entries recorded') }}</p>
            </div>
        </div>

        <div class="card-body" style="padding-bottom: 0;">
            <form action="{{ route('activity-logs.index') }}" method="GET" class="filters-bar">
                <div class="search-field">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="{{ __('Search by record name...') }}">
                </div>

                <select name="user_id" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Users') }}</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" {{ request('user_id') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>

                <select name="action" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Actions') }}</option>
                    @foreach (App\Models\ActivityLog::ACTIONS as $action)
                        <option value="{{ $action }}" {{ request('action') === $action ? 'selected' : '' }}>
                            {{ __(ucfirst($action)) }}
                        </option>
                    @endforeach
                </select>

                <select name="subject_type" class="select-field" onchange="this.form.submit()">
                    <option value="">{{ __('All Record Types') }}</option>
                    @foreach ($subjectTypes as $type)
                        <option value="{{ $type }}" {{ request('subject_type') === $type ? 'selected' : '' }}>
                            {{ ucwords(str_replace('_', ' ', $type)) }}
                        </option>
                    @endforeach
                </select>

                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="select-field" title="{{ __('Date From') }}" onchange="this.form.submit()">

                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="select-field" title="{{ __('Date To') }}" onchange="this.form.submit()">

                @if (request()->hasAny(['search', 'user_id', 'action', 'subject_type', 'date_from', 'date_to']))
                    <a href="{{ route('activity-logs.index') }}" class="btn btn-secondary btn-sm">{{ __('Clear') }}</a>
                @endif
            </form>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('When') }}</th>
                        <th>{{ __('User') }}</th>
                        <th>{{ __('Action') }}</th>
                        <th>{{ __('Record Type') }}</th>
                        <th>{{ __('Record') }}</th>
                        <th>{{ __('Changes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="cell-muted">{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td class="cell-primary">
                                {{-- Null when the account was deleted; the entry itself survives. --}}
                                {{ $log->user->name ?? __('System') }}
                            </td>
                            <td>
                                @if ($log->action === App\Models\ActivityLog::ACTION_CREATED)
                                    <span class="badge badge-green">{{ __('Created') }}</span>
                                @elseif ($log->action === App\Models\ActivityLog::ACTION_UPDATED)
                                    <span class="badge badge-amber">{{ __('Updated') }}</span>
                                @else
                                    <span class="badge badge-red">{{ __('Deleted') }}</span>
                                @endif
                            </td>
                            <td class="cell-muted">{{ $log->subjectName() }}</td>
                            <td class="cell-primary">{{ $log->subject_label ?? '#' . $log->subject_id }}</td>
                            <td class="cell-muted" style="font-size:11px;">
                                @if (empty($log->properties))
                                    &mdash;
                                @else
                                    {{ implode(', ', array_keys($log->properties)) }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-empty-row
                            colspan="6"
                            icon="fa-clipboard-list"
                            :title="__('No activity recorded yet')"
                            :message="__('Creates, edits and deletions are logged here as people work.')"
                        />
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($logs->hasPages())
            <div class="pagination-bar">
                <span>
                    {{ __('Showing') }} {{ $logs->firstItem() }}-{{ $logs->lastItem() }}
                    {{ __('of') }} {{ $logs->total() }} {{ __('entries') }}
                </span>
                <div class="pagination-controls">
                    @if ($logs->onFirstPage())
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-left"></i></button>
                    @else
                        <a href="{{ $logs->previousPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-left"></i></a>
                    @endif

                    @foreach ($logs->getUrlRange(1, $logs->lastPage()) as $page => $url)
                        <a href="{{ $url }}" class="page-btn {{ $page === $logs->currentPage() ? 'active' : '' }}">{{ $page }}</a>
                    @endforeach

                    @if ($logs->hasMorePages())
                        <a href="{{ $logs->nextPageUrl() }}" class="page-btn"><i class="fa-solid fa-chevron-right"></i></a>
                    @else
                        <button class="page-btn" disabled><i class="fa-solid fa-chevron-right"></i></button>
                    @endif
                </div>
            </div>
        @endif
    </div>

@endsection
