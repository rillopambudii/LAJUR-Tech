@props(['user', 'size' => 'md'])
@if ($user->avatarUrl())
    <img {{ $attributes->merge(['class' => "avatar avatar-$size"]) }} src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}">
@else
    <span {{ $attributes->merge(['class' => "avatar avatar-$size"]) }}>{{ $user->initials() }}</span>
@endif
