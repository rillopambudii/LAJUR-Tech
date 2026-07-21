@props(['name', 'required' => false])
<div class="star-input" role="radiogroup" aria-label="Beri rating">
    @for ($i = 5; $i >= 1; $i--)
        <input type="radio" name="{{ $name }}" id="{{ $name }}-{{ $i }}" value="{{ $i }}" @if ($required) required @endif>
        <label for="{{ $name }}-{{ $i }}" aria-label="{{ $i }} bintang"><x-icon name="star" /></label>
    @endfor
</div>
