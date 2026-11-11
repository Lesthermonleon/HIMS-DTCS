@extends('layouts.app')

@section('title', 'Pharmacy Inventory Counts')

@section('content')
<div class="space-y-5">

    <div class="bg-gradient-to-r from-slate-900 via-teal-950 to-slate-900 rounded-2xl p-5 text-white shadow-sm border border-slate-800">
        <div class="flex items-start gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-teal-500/20 border border-teal-400/30 flex items-center justify-center text-teal-300 shrink-0">
                <i class="bi bi-clipboard2-check text-xl"></i>
            </div>
            <div>
                <h1 class="text-lg sm:text-xl font-black tracking-tight text-white">Inventory Counts &amp; Reconciliation</h1>
                <p class="text-xs text-slate-300 mt-0.5">
                    Cycle count records showing expected quantity, physical counted quantity, variance, count date, and responsible audit team.
                </p>
            </div>
        </div>
    </div>

    @include('pharmacy.inventory._subnav')

    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
        <div class="px-5 py-3.5 border-b border-slate-200 flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-800">Physical Inventory Count Register</h2>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">Supply Chain Audit Feed</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-extrabold text-slate-500">
                        <th class="py-3 px-4">Count Ref</th>
                        <th class="py-3 px-4">Medicine</th>
                        <th class="py-3 px-4">Lot No.</th>
                        <th class="py-3 px-4 text-center">Expected Qty</th>
                        <th class="py-3 px-4 text-center">Counted Qty</th>
                        <th class="py-3 px-4 text-center">Variance</th>
                        <th class="py-3 px-4">Count Date</th>
                        <th class="py-3 px-4">Responsible User</th>
                        <th class="py-3 px-4 text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($countRecords as $row)
                        <tr>
                            <td class="py-3 px-4 font-mono font-bold text-slate-700">{{ $row['count_ref'] }}</td>
                            <td class="py-3 px-4 font-bold text-slate-800">
                                {{ $row['medication_name'] }}
                                <span class="block text-[10px] font-mono text-slate-400">{{ $row['code'] }}</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-500">{{ $row['lot_number'] }}</td>
                            <td class="py-3 px-4 text-center font-bold text-slate-700">{{ number_format($row['expected_qty']) }} {{ $row['unit'] }}</td>
                            <td class="py-3 px-4 text-center font-bold text-slate-800">{{ number_format($row['counted_qty']) }} {{ $row['unit'] }}</td>
                            <td class="py-3 px-4 text-center font-extrabold {{ $row['variance'] === 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $row['variance'] }}
                            </td>
                            <td class="py-3 px-4 text-slate-500">{{ $row['count_date'] }}</td>
                            <td class="py-3 px-4 text-slate-600">{{ $row['responsible_user'] }}</td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    {{ $row['status'] }}
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

