@php
    $currentRoute = request()->route()?->getName() ?? '';
@endphp
<div class="bg-white rounded-xl border border-slate-200/90 p-2 shadow-2xs flex flex-wrap items-center justify-between gap-2">
    <div class="flex flex-wrap items-center gap-1.5">
        <a href="{{ route('pharmacy.inventory.items') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all no-underline {{ $currentRoute === 'pharmacy.inventory.items' ? 'bg-teal-600 text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-100' }}">
            <i class="bi bi-boxes"></i> Inventory Items
        </a>
        <a href="{{ route('pharmacy.inventory.purchase-orders') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all no-underline {{ $currentRoute === 'pharmacy.inventory.purchase-orders' ? 'bg-teal-600 text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-100' }}">
            <i class="bi bi-truck"></i> Purchase Orders
        </a>
        <a href="{{ route('pharmacy.inventory.counts') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all no-underline {{ $currentRoute === 'pharmacy.inventory.counts' ? 'bg-teal-600 text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-100' }}">
            <i class="bi bi-clipboard2-check"></i> Inventory Counts
        </a>
        <a href="{{ route('pharmacy.inventory.adjustments') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all no-underline {{ $currentRoute === 'pharmacy.inventory.adjustments' ? 'bg-teal-600 text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-100' }}">
            <i class="bi bi-sliders"></i> Stock Adjustments
        </a>
        <a href="{{ route('pharmacy.inventory.replenishment.index') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all no-underline {{ str_starts_with($currentRoute, 'pharmacy.inventory.replenishment') ? 'bg-amber-600 text-white shadow-2xs' : 'bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100' }}">
            <i class="bi bi-send-exclamation-fill"></i> Stock Replenishment Requests
            <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold uppercase bg-white/90 text-amber-800">Demo</span>
        </a>
    </div>

    <div class="text-[11px] text-slate-400 font-medium px-2">
        <i class="bi bi-diagram-3 text-teal-600 mr-1"></i>
        Source: <span class="font-semibold text-slate-600">Inventory &amp; Supply Chain Subsystem Feed</span>
    </div>
</div>

