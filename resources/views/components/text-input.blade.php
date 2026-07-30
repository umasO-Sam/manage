@props(['disabled' => false])

@php
    $errorName = $attributes->get('name');
    $hasError = $errorName && $errors->has($errorName);
@endphp

<input @disabled($disabled) {{ $attributes->merge([
    'class' => 'rounded-lg shadow-sm text-sm '
        . ($hasError ? 'bg-red-50 border-red-300 focus:border-red-400 focus:ring-red-400' : 'border-slate-300 focus:border-blue-500 focus:ring-blue-500'),
]) }}>
