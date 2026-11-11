{{--
    Skeleton: Statistics Card
    Usage: <x-skeleton.stat-card />
    Props:
      $color  = 'signal' | 'coral' | 'amber' | 'steel'  (left bar accent)
--}}
@props(['color' => 'signal'])

@php
$barColors = [
    'coral'  => '#dc2626',
    'amber'  => '#d97706',
    'steel'  => '#648071',
];
$bar = $barColors[$color] ?? null;
@endphp

<div {{ $attributes->merge(['class' => 'skeleton-stat-card', 'style' => $bar ? "border-left-color: {$bar};" : 'border-left-color: transparent;']) }}
     role="status" aria-label="Loading statistic" aria-busy="true">
    <div class="sk-stat-top">
        <div style="flex:1;">
            {{-- Large metric number --}}
            <div class="sk sk-3xl sk-w-25 mb-2"></div>
            {{-- Label --}}
            <div class="sk sk-sm sk-w-50"></div>
        </div>
        {{-- Icon box --}}
        <div class="sk sk-icon-lg" style="flex-shrink:0;"></div>
    </div>
    {{-- Sparkline --}}
    <div class="sk sk-sparkline"></div>
</div>
