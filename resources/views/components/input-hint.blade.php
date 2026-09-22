{{-- One-line helper text under a form field. Link it to the input with
     aria-describedby="{{ id }}" so screen readers announce it too. --}}
@props(['id'])

<p id="{{ $id }}" {{ $attributes->merge(['style' => 'margin:.4rem 0 0;font-size:.78rem;line-height:1.45;opacity:.75;']) }}>{{ $slot }}</p>
