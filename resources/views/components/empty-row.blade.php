@props([
    'colspan'     => 1,
    'icon'        => 'fa-inbox',
    'title'       => null,
    'message'     => null,
    'createUrl'   => null,
    'createLabel' => null,
])

{{--
    The row a table shows when it has nothing to show.

    An empty table has two quite different causes and the old one-line "No
    products found." covered both, which left the more common one a dead end: a
    user who has filtered down to nothing is told the table is empty and given
    no hint that their own filters did it. So this component looks at the query
    string and answers the question the user is actually asking.

    Filtered:  say so, and offer the way back — a link to the same page with the
               filters dropped.
    Genuinely
    empty:     say what the page is for, and where to create the first record.

    Any query parameter other than the page number counts as a filter. That is
    deliberately broad: every index page here filters through the query string,
    so a parameter being present at all means the user narrowed something.
--}}

@php
    $isFiltered = collect(request()->query())
        ->except(['page'])
        ->filter(fn ($value) => $value !== null && $value !== '')
        ->isNotEmpty();
@endphp

<tr>
    <td colspan="{{ $colspan }}">
        <div class="empty-state">
            @if ($isFiltered)
                <i class="fa-solid fa-filter-circle-xmark" aria-hidden="true"></i>
                <h3>{{ __('Nothing matches these filters') }}</h3>
                <p>{{ __('Try widening your search, or clear the filters to see everything again.') }}</p>
                <a href="{{ url()->current() }}" class="btn btn-secondary btn-sm">
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i> {{ __('Clear filters') }}
                </a>
            @else
                <i class="fa-solid {{ $icon }}" aria-hidden="true"></i>
                <h3>{{ $title ?? __('Nothing here yet') }}</h3>

                @if ($message)
                    <p>{{ $message }}</p>
                @endif

                @if ($createUrl)
                    <a href="{{ $createUrl }}" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> {{ $createLabel ?? __('Create') }}
                    </a>
                @endif
            @endif
        </div>
    </td>
</tr>
