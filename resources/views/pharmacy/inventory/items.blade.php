@extends('layouts.app')

@section('title', 'Pharmacy Inventory Items & Stock Monitoring')

@section('content')
<div class="space-y-5">

    {{-- Page Header --}}
    <div class="bg-gradient-to-r from-slate-900 via-teal-950 to-slate-900 rounded-2xl p-5 text-white shadow-sm border border-slate-800 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-start gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-teal-500/20 border border-teal-400/30 flex items-center justify-center text-teal-300 shrink-0">
                <i class="bi bi-boxes text-xl"></i>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-lg sm:text-xl font-black tracking-tight text-white">Inventory Items &amp; Stock Monitoring</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-teal-500/20 text-teal-300 border border-teal-400/30">
                        Read-Only Stock Feed
                    </span>
                </div>
                <p class="text-xs text-slate-300 mt-0.5">
                    Real-time pharmacy visibility into medicine stock balances, reorder thresholds, lot numbers, expiration dates, and dispensing/POS movement logs.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('pharmacy.inventory.replenishment.index') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-extrabold transition-colors no-underline shadow-xs">
                <i class="bi bi-send-exclamation-fill"></i> Request Low-Stock Replenishment (Demo)
            </a>
            <a href="{{ route('pharmacy.pos.index') }}"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 text-xs font-bold transition-colors no-underline">
                <i class="bi bi-calculator"></i> Open POS
            </a>
        </div>
    </div>

    @include('pharmacy.inventory._subnav')

    {{-- Inventory Status Scorecards / Filter Pills --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        @php
            $filterCards = [
                ['key' => 'all',         'label' => 'All Items',              'count' => $counts['all'],         'icon' => 'bi-collection',            'tone' => 'slate'],
                ['key' => 'available',   'label' => 'Available Stocks',       'count' => $counts['available'],   'icon' => 'bi-check2-circle',         'tone' => 'emerald'],
                ['key' => 'low',         'label' => 'Low Stocks',             'count' => $counts['low'],         'icon' => 'bi-exclamation-triangle',  'tone' => 'amber'],
                ['key' => 'out',         'label' => 'Out-of-Stock Medicines', 'count' => $counts['out'],         'icon' => 'bi-x-octagon',             'tone' => 'red'],
                ['key' => 'near_expiry', 'label' => 'Near-Expiry Stocks',     'count' => $counts['near_expiry'], 'icon' => 'bi-hourglass-split',       'tone' => 'orange'],
                ['key' => 'expired',     'label' => 'Expired Medicines',      'count' => $counts['expired'],     'icon' => 'bi-shield-x',              'tone' => 'rose'],
            ];
        @endphp
        @foreach($filterCards as $card)
            @php $isActive = $filter === $card['key']; @endphp
            <a href="{{ route('pharmacy.inventory.items', ['filter' => $card['key'], 'search' => $search, 'category' => $category]) }}"
               class="rounded-xl p-3.5 border transition-all no-underline flex flex-col justify-between {{ $isActive ? 'bg-teal-950 text-white border-teal-700 shadow-sm' : 'bg-white text-slate-800 border-slate-200/90 hover:border-teal-400' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider {{ $isActive ? 'text-teal-300' : 'text-slate-400' }}">{{ $card['label'] }}</span>
                    <i class="bi {{ $card['icon'] }} text-sm {{ $isActive ? 'text-teal-300' : 'text-teal-600' }}"></i>
                </div>
                <div class="text-xl font-black mt-1.5 {{ $isActive ? 'text-white' : 'text-slate-900' }}">
                    {{ number_format($card['count']) }}
                </div>
            </a>
        @endforeach
    </div>

    {{-- Search & Category Filter Bar --}}
    <div class="bg-white rounded-xl border border-slate-200/90 p-3.5 shadow-2xs">
        <form method="GET" action="{{ route('pharmacy.inventory.items') }}" class="grid grid-cols-1 sm:grid-cols-12 gap-2.5 items-center">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <div class="sm:col-span-6 relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text"
                       name="search"
                       value="{{ $search }}"
                       placeholder="Search by code, generic/brand name, lot number, or category..."
                       class="w-full pl-8 pr-3 py-2 rounded-lg border border-slate-200 text-xs text-slate-800 focus:outline-none focus:border-teal-500">
            </div>
            <div class="sm:col-span-3">
                <select name="category" class="w-full py-2 px-3 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}" {{ strcasecmp($category, $cat) === 0 ? 'selected' : '' }}>{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-3 flex items-center gap-2">
                <button type="submit" class="flex-1 py-2 px-3 rounded-lg bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold border-0 cursor-pointer">
                    Apply Filter
                </button>
                <a href="{{ route('pharmacy.inventory.items') }}" class="py-2 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold no-underline">
                    Reset
                </a>
            </div>
        </form>
    </div>

    {{-- Inventory Items Table --}}
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-extrabold text-slate-800">Medicine Stock Directory</h2>
                <p class="text-[11px] text-slate-500">Showing {{ $items->count() }} medicine records</p>
            </div>
            <span class="text-[11px] text-slate-400">
                <i class="bi bi-lock-fill text-teal-600"></i> Direct stock mutations disabled in PMS (owned by Inventory Subsystem)
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-extrabold text-slate-500">
                        <th class="py-3 px-4">Code</th>
                        <th class="py-3 px-4">Medicine / Generic &amp; Brand</th>
                        <th class="py-3 px-4">Category</th>
                        <th class="py-3 px-4">Strength &amp; Form</th>
                        <th class="py-3 px-4 text-right">Unit Price</th>
                        <th class="py-3 px-4 text-center">Available Qty</th>
                        <th class="py-3 px-4 text-center">Reorder Level</th>
                        <th class="py-3 px-4">Lot &amp; Expiration</th>
                        <th class="py-3 px-4 text-center">Stock Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($items as $item)
                        @php
                            $badgeStyle = match($item['status_key']) {
                                'available' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'low'       => 'bg-amber-50 text-amber-700 border-amber-200',
                                'out'       => 'bg-red-50 text-red-700 border-red-200',
                                'expired'   => 'bg-slate-200 text-slate-800 border-slate-300',
                                default     => 'bg-slate-100 text-slate-600 border-slate-200',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50/70">
                            <td class="py-3 px-4 font-mono font-bold text-slate-600">{{ $item['code'] }}</td>
                            <td class="py-3 px-4">
                                <div class="font-extrabold text-slate-800">{{ $item['medication_name'] }}</div>
                                <div class="text-[11px] text-slate-500">Generic: {{ $item['generic_name'] }} &bull; Brand: {{ $item['brand_name'] }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700 font-semibold text-[11px]">{{ $item['category'] }}</span>
                            </td>
                            <td class="py-3 px-4 text-slate-600">
                                {{ $item['strength'] }} &bull; {{ $item['dosage_form'] }} ({{ $item['unit'] }})
                            </td>
                            <td class="py-3 px-4 text-right font-extrabold text-slate-800">
                                ₱{{ number_format($item['unit_price'], 2) }}
                            </td>
                            <td class="py-3 px-4 text-center font-black {{ $item['available_quantity'] <= $item['reorder_level'] ? 'text-amber-700' : 'text-slate-800' }}">
                                {{ number_format($item['available_quantity']) }}
                            </td>
                            <td class="py-3 px-4 text-center text-slate-500 font-semibold">
                                {{ number_format($item['reorder_level']) }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-mono text-[11px] font-bold text-slate-700">{{ $item['lot_number'] }}</span>
                                <span class="block text-[10px] {{ $item['is_expired'] ? 'text-red-600 font-bold' : ($item['is_near_expiry'] ? 'text-amber-600 font-bold' : 'text-slate-400') }}">
                                    Exp: {{ $item['expiry_date'] ?? 'N/A' }}
                                    @if($item['is_near_expiry']) (Near Expiry) @endif
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $badgeStyle }}">
                                    {{ $item['status_label'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-8 text-center text-slate-400">
                                No inventory items match the selected filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Stock Movement History (Dispensing & POS Activity) --}}
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-extrabold text-slate-800">Stock Movement History (PMS Dispensing &amp; POS Ledger)</h2>
                <p class="text-[11px] text-slate-500">Recent medication outflows recorded by prescription dispensing and POS retail transactions</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-extrabold text-slate-500">
                        <th class="py-2.5 px-4">Date &amp; Time</th>
                        <th class="py-2.5 px-4">Movement Type</th>
                        <th class="py-2.5 px-4">Reference No.</th>
                        <th class="py-2.5 px-4">Medicine</th>
                        <th class="py-2.5 px-4">Lot No.</th>
                        <th class="py-2.5 px-4 text-center">Quantity Delta</th>
                        <th class="py-2.5 px-4">Recorded By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($movements as $mv)
                        <tr>
                            <td class="py-2.5 px-4 text-slate-500">{{ $mv['timestamp']?->format('M d, Y h:i A') ?? 'N/A' }}</td>
                            <td class="py-2.5 px-4 font-bold text-slate-700">{{ $mv['type'] }}</td>
                            <td class="py-2.5 px-4 font-mono font-bold text-teal-700">{{ $mv['reference'] }}</td>
                            <td class="py-2.5 px-4 font-semibold text-slate-800">{{ $mv['medicine'] }}</td>
                            <td class="py-2.5 px-4 font-mono text-slate-500">{{ $mv['lot_number'] }}</td>
                            <td class="py-2.5 px-4 text-center font-extrabold text-red-600">{{ $mv['qty_change'] }}</td>
                            <td class="py-2.5 px-4 text-slate-600">{{ $mv['actor'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-400">No pharmacy stock movement records yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

