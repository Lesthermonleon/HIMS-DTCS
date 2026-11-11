@extends('layouts.app')

@section('title', 'Pharmacy Stock Adjustments')

@section('content')
<div class="space-y-5">

    <div class="bg-gradient-to-r from-slate-900 via-teal-950 to-slate-900 rounded-2xl p-5 text-white shadow-sm border border-slate-800">
        <div class="flex items-start gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-teal-500/20 border border-teal-400/30 flex items-center justify-center text-teal-300 shrink-0">
                <i class="bi bi-sliders text-xl"></i>
            </div>
            <div>
                <h1 class="text-lg sm:text-xl font-black tracking-tight text-white">Stock Adjustments &amp; Quarantine Log</h1>
                <p class="text-xs text-slate-300 mt-0.5">
                    Read-only audit ledger of stock write-offs, expired batch quarantines, and cycle count reconciliations executed by Supply Chain.
                </p>
            </div>
        </div>
    </div>

    @include('pharmacy.inventory._subnav')

    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-800">Stock Adjustment Ledger</h2>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">Read-Only Audit Trail</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-extrabold text-slate-500">
                        <th class="py-3 px-4">Adjustment No.</th>
                        <th class="py-3 px-4">Medicine</th>
                        <th class="py-3 px-4">Lot No.</th>
                        <th class="py-3 px-4">Adjustment Type</th>
                        <th class="py-3 px-4 text-center">Qty Delta</th>
                        <th class="py-3 px-4">Reason</th>
                        <th class="py-3 px-4">Date &amp; Time</th>
                        <th class="py-3 px-4">Authorized By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($adjustments as $adj)
                        <tr>
                            <td class="py-3 px-4 font-mono font-bold text-slate-700">{{ $adj['adjustment_no'] }}</td>
                            <td class="py-3 px-4 font-bold text-slate-800">
                                {{ $adj['medication_name'] }}
                                <span class="block text-[10px] font-mono text-slate-400">{{ $adj['medicine_code'] }}</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-500">{{ $adj['lot_number'] }}</td>
                            <td class="py-3 px-4 font-semibold text-slate-700">{{ $adj['adjustment_type'] }}</td>
                            <td class="py-3 px-4 text-center font-extrabold {{ $adj['quantity_delta'] < 0 ? 'text-red-600' : 'text-slate-700' }}">
                                {{ $adj['quantity_delta'] }}
                            </td>
                            <td class="py-3 px-4 text-slate-600">{{ $adj['reason'] }}</td>
                            <td class="py-3 px-4 text-slate-500">{{ $adj['adjusted_at'] }}</td>
                            <td class="py-3 px-4 text-slate-700 font-semibold">{{ $adj['authorized_by'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

