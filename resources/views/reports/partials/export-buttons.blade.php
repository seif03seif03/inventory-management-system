{{--
    PDF / CSV export buttons for a report page.

    The current query string is carried through (minus ?page, which exports
    ignore) so the downloaded file contains exactly the filtered rows the user
    is looking at — not the whole table, and not just the visible page.

    Usage: @include('reports.partials.export-buttons', ['route' => 'reports.stock.export'])
--}}
<div class="row-actions">
    <a href="{{ route($route, array_merge(request()->except('page'), ['format' => 'pdf'])) }}"
       class="btn btn-secondary btn-sm" title="{{ __('Download as PDF') }}">
        <i class="fa-regular fa-file-pdf"></i> {{ __('PDF') }}
    </a>
    <a href="{{ route($route, array_merge(request()->except('page'), ['format' => 'csv'])) }}"
       class="btn btn-secondary btn-sm" title="{{ __('Download as CSV') }}">
        <i class="fa-solid fa-file-csv"></i> {{ __('CSV') }}
    </a>
</div>
