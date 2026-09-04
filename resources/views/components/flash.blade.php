{{--
    Every flash message on every page, rendered one way.

    Before this component each page carried its own copy of these blocks, and the
    same class of message did not look the same twice: a success was a small
    green pill on the index pages and a full-width banner elsewhere, while an
    error was always a banner. Weight reads as importance, so a confirmation
    styled as a footnote reads as one — and the duplication meant fixing that in
    twenty-two places.

    Rendered once from layouts.app, immediately above the page content, so a
    message always appears in the same spot no matter which page redirected.

    Four keys cover everything the controllers flash:

      success      a completed action
      error        an action that could not be carried out at all
      info         a neutral note
      stockErrors  an array of per-product reasons a document was refused

    The validation summary belongs here for the same reason the others do: it
    reports on the request that just happened. It repeats what the inline
    @error messages say beside each field, which is deliberate — the summary is
    what a user sees without scrolling, and the inline message is what tells
    them which box to fix. $errors is always defined in a Blade view (Laravel
    shares an empty bag when there is nothing to report), so it needs no guard.
--}}

@if (session('success'))
    <div class="alert alert-success" role="status">
        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
        <div>{{ session('success') }}</div>
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <div>{{ session('error') }}</div>
    </div>
@endif

@if (session('info'))
    <div class="alert alert-info" role="status">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <div>{{ session('info') }}</div>
    </div>
@endif

@if (session('stockErrors'))
    <div class="alert alert-danger" role="alert">
        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
        <div>
            <strong>{{ __('Nothing was saved — there is not enough stock:') }}</strong>
            <ul class="alert-list">
                @foreach ((array) session('stockErrors') as $stockError)
                    <li>{{ $stockError }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <div>
            <strong>{{ __('Please correct the following:') }}</strong>
            <ul class="alert-list">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
