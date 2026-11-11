@extends('layouts.app')

@section('title', 'Pharmacy Purchase Orders — Inventory Subsystem Reference')

@section('content')
<div class="space-y-5">

    <div class="bg-gradient-to-r from-slate-900 via-teal-950 to-slate-900 rounded-2xl p-5 text-white shadow-sm border border-slate-800 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-start gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-teal-500/20 border border-teal-400/30 flex items-center justify-center text-teal-300 shrink-0">
                <i class="bi bi-truck text-xl"></i>
            </div>
            <div>
                <h1 class="text-lg sm:text-xl font-black tracking-tight text-white">Purchase Orders (Supply Chain Reference)</h1>
                <p class="text-xs text-slate-300 mt-0.5">
                    Read-only pharmacy visibility into supplier purchase orders managed by the external Hospital Inventory &amp; Supply Chain Subsystem.
                </p>
            </div>
        </div>
        <a href="{{ route('pharmacy.inventory.replenishment.index') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-extrabold transition-colors no-underline">
            <i class="bi bi-send-exclamation-fill"></i> Open Low-Stock Replenishment Demo ({{ $demoReplenishmentCount }})
        </a>
    </div>

    @include('pharmacy.inventory._subnav')

    {{-- Subsystem Boundary Banner --}}
    <div class="bg-sky-50 border border-sky-200 rounded-xl p-4 flex items-start gap-3 text-xs text-sky-900">
        <i class="bi bi-info-circle-fill text-sky-600 text-base shrink-0 mt-0.5"></i>
        <div>
            <strong class="font-extrabold">Subsystem Boundary Notice (No Conflict Policy):</strong>
            Supplier Purchase Orders, receiving inspections, and vendor contracts are authored and executed in the separate <strong>Hospital Inventory / Supply Chain Subsystem</strong>. To simulate a pharmacy replenishment request without triggering external procurement, use the <a href="{{ route('pharmacy.inventory.replenishment.index') }}" class="font-bold text-teal-700 underline">Stock Replenishment Requests (Static Demo)</a> workspace.
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-800">External Purchase Order Feed</h2>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">Read-Only Feed</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-extrabold text-slate-500">
                        <th class="py-3 px-4">PO Number</th>
                        <th class="py-3 px-4">Supplier</th>
                        <th class="py-3 px-4">Receiving Pharmacy</th>
                        <th class="py-3 px-4">Order Date</th>
                        <th class="py-3 px-4">Expected Delivery</th>
                        <th class="py-3 px-4 text-center">Line Items</th>
                        <th class="py-3 px-4 text-right">PO Amount</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($purchaseOrders as $po)
                        <tr>
                            <td class="py-3 px-4 font-mono font-bold text-slate-800">{{ $po['po_number'] }}</td>
                            <td class="py-3 px-4 font-bold text-slate-700">{{ $po['supplier'] }}</td>
                            <td class="py-3 px-4 text-slate-600">{{ $po['department'] }}</td>
                            <td class="py-3 px-4 text-slate-500">{{ $po['ordered_at'] }}</td>
                            <td class="py-3 px-4 text-slate-500">{{ $po['expected_at'] }}</td>
                            <td class="py-3 px-4 text-center font-bold text-slate-700">{{ $po['items_count'] }}</td>
                            <td class="py-3 px-4 text-right font-extrabold text-slate-800">₱{{ number_format($po['total_amount'], 2) }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200">
                                    {{ $po['status'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

