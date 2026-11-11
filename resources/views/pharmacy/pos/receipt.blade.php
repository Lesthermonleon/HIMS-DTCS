@extends('layouts.app')

@section('title', 'POS Official Receipt ' . $transaction->transaction_no)

@section('content')
<div class="max-w-3xl mx-auto space-y-5">

    {{-- Top Actions Bar --}}
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('pharmacy.pos.index') }}"
           class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 text-xs font-bold shadow-2xs transition-colors no-underline">
            <i class="bi bi-arrow-left"></i> Back to Point of Sale
        </a>

        <div class="flex items-center gap-2">
            @if($transaction->prescription)
                <a href="{{ route('pharmacy.prescriptions.show', $transaction->prescription) }}"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-teal-50 hover:bg-teal-100 text-teal-800 border border-teal-200 text-xs font-bold transition-colors no-underline">
                    <i class="bi bi-file-earmark-medical"></i> View Linked Rx ({{ $transaction->prescription->prescription_no }})
                </a>
            @endif
            <button type="button"
                    onclick="window.print()"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold shadow-xs transition-colors cursor-pointer border-0">
                <i class="bi bi-printer-fill"></i> Print Official Receipt
            </button>
        </div>
    </div>

    {{-- Printable Official Receipt Card --}}
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">

        {{-- Receipt Header --}}
        <div class="bg-slate-900 text-white p-6 text-center relative">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl bg-teal-500/20 border border-teal-400/30 text-teal-300 mb-2">
                <i class="bi bi-capsule-pill text-2xl"></i>
            </div>
            <h1 class="text-lg font-black tracking-tight text-white">DTCS HOSPITAL PHARMACY</h1>
            <p class="text-xs text-slate-300">Diagnostics, Treatment &amp; Clinical Services HIMS &bull; Store: {{ $transaction->store_code }}</p>
            <p class="text-[11px] text-slate-400 font-mono mt-0.5">Terminal: {{ $transaction->terminal_id }}</p>

            <div class="mt-3">
                @php
                    $statusClasses = match($transaction->status) {
                        'Completed' => 'bg-emerald-500/20 text-emerald-300 border-emerald-400/40',
                        'Voided'    => 'bg-red-500/20 text-red-300 border-red-400/40',
                        'Refunded'  => 'bg-amber-500/20 text-amber-300 border-amber-400/40',
                        default     => 'bg-slate-700 text-slate-300 border-slate-600',
                    };
                @endphp
                <span class="inline-block px-3 py-0.5 rounded-full text-xs font-extrabold uppercase tracking-wider border {{ $statusClasses }}">
                    {{ $transaction->status }}
                </span>
            </div>
        </div>

        {{-- Transaction Metadata --}}
        <div class="p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 bg-slate-50 rounded-xl p-4 border border-slate-200/80 text-xs">
                <div class="space-y-1.5">
                    <div class="flex justify-between sm:justify-start sm:gap-2">
                        <span class="text-slate-400 font-semibold">Receipt No:</span>
                        <span class="font-mono font-extrabold text-slate-800">{{ $transaction->transaction_no }}</span>
                    </div>
                    <div class="flex justify-between sm:justify-start sm:gap-2">
                        <span class="text-slate-400 font-semibold">Date &amp; Time:</span>
                        <span class="font-bold text-slate-700">{{ $transaction->transacted_at?->format('M d, Y h:i:s A') }}</span>
                    </div>
                    <div class="flex justify-between sm:justify-start sm:gap-2">
                        <span class="text-slate-400 font-semibold">Sale Type:</span>
                        <span class="font-bold text-teal-700 uppercase">{{ $transaction->sale_type === 'prescription' ? 'Prescription-Linked Sale' : 'Walk-In / OTC Sale' }}</span>
                    </div>
                </div>
                <div class="space-y-1.5">
                    <div class="flex justify-between sm:justify-start sm:gap-2">
                        <span class="text-slate-400 font-semibold">Customer / Patient:</span>
                        <span class="font-bold text-slate-800">{{ $transaction->customer_name ?? $transaction->patient?->full_name ?? 'Walk-In Customer' }}</span>
                    </div>
                    @if($transaction->prescription)
                        <div class="flex justify-between sm:justify-start sm:gap-2">
                            <span class="text-slate-400 font-semibold">Prescription No:</span>
                            <span class="font-mono font-bold text-teal-700">{{ $transaction->prescription->prescription_no }} (Rx Status: {{ $transaction->prescription->status }})</span>
                        </div>
                    @endif
                    <div class="flex justify-between sm:justify-start sm:gap-2">
                        <span class="text-slate-400 font-semibold">Pharmacist:</span>
                        <span class="font-bold text-slate-700">{{ $transaction->pharmacist?->name ?? 'N/A' }}</span>
                    </div>
                </div>
            </div>

            {{-- Itemized Medicines Table --}}
            <div class="border border-slate-200 rounded-xl overflow-hidden">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-100 border-b border-slate-200 text-[10px] uppercase font-extrabold text-slate-500">
                            <th class="py-2.5 px-3">Code</th>
                            <th class="py-2.5 px-3">Medicine Description</th>
                            <th class="py-2.5 px-3">Lot / Expiry</th>
                            <th class="py-2.5 px-3 text-center">Qty</th>
                            <th class="py-2.5 px-3 text-right">Unit Price</th>
                            <th class="py-2.5 px-3 text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($transaction->items as $item)
                            <tr>
                                <td class="py-2.5 px-3 font-mono font-bold text-slate-500">{{ $item->medicine_code }}</td>
                                <td class="py-2.5 px-3">
                                    <div class="font-bold text-slate-800">{{ $item->medication_name }}</div>
                                    <div class="text-[10px] text-slate-500">{{ $item->brand_name }} &bull; {{ $item->strength }} {{ $item->dosage_form }}</div>
                                </td>
                                <td class="py-2.5 px-3 font-mono text-[11px] text-slate-500">
                                    {{ $item->lot_number ?? 'N/A' }}
                                    @if($item->expiry_date)
                                        <span class="block text-[10px] text-slate-400">Exp: {{ $item->expiry_date->format('Y-m-d') }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-center font-bold text-slate-800">{{ $item->quantity }} {{ $item->unit }}</td>
                                <td class="py-2.5 px-3 text-right text-slate-700">₱{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="py-2.5 px-3 text-right font-extrabold text-slate-900">₱{{ number_format((float) $item->line_subtotal, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Totals Breakdown --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                <div class="text-xs text-slate-500 space-y-1.5 bg-slate-50 p-3.5 rounded-xl border border-slate-200/70">
                    <div><strong>Payment Method:</strong> <span class="text-slate-800 font-bold">{{ $transaction->payment_method }}</span></div>
                    @if($transaction->discount_type !== 'none')
                        <div>
                            <strong>Discount Applied:</strong>
                            <span class="text-amber-700 font-bold uppercase">{{ str_replace('_', ' ', $transaction->discount_type) }} ({{ number_format((float) $transaction->discount_rate * 100, 0) }}%)</span>
                            @if($transaction->discount_reference)
                                <span class="block text-[11px] font-mono">ID Ref: {{ $transaction->discount_reference }}</span>
                            @endif
                        </div>
                    @endif
                    @if($transaction->status !== 'Completed')
                        <div class="pt-2 border-t border-red-200 text-red-700">
                            <strong>{{ $transaction->status }} By:</strong> {{ $transaction->voidedByUser?->name ?? 'Pharmacist' }}
                            ({{ $transaction->voided_at?->format('M d, Y h:i A') }})<br>
                            <strong>Reason:</strong> {{ $transaction->void_reason }}
                        </div>
                    @endif
                </div>

                <div class="bg-slate-900 text-slate-200 rounded-xl p-4 space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-400">Gross Subtotal:</span>
                        <span class="font-bold text-white">₱{{ number_format((float) $transaction->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">VATable Sales:</span>
                        <span class="font-semibold">₱{{ number_format((float) $transaction->vatable_sales, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">VAT-Exempt Sales:</span>
                        <span class="font-semibold">₱{{ number_format((float) $transaction->vat_exempt_sales, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Discount Amount:</span>
                        <span class="font-bold text-amber-400">-₱{{ number_format((float) $transaction->discount_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">VAT ({{ number_format((float) $transaction->vat_rate * 100, 0) }}%):</span>
                        <span class="font-bold text-slate-200">₱{{ number_format((float) $transaction->vat_amount, 2) }}</span>
                    </div>
                    <div class="pt-2 border-t border-slate-700 flex justify-between items-baseline">
                        <span class="font-extrabold uppercase tracking-wider text-teal-300">Grand Total:</span>
                        <span class="text-lg font-black text-emerald-400">₱{{ number_format((float) $transaction->grand_total, 2) }}</span>
                    </div>
                    <div class="pt-1.5 border-t border-slate-800 flex justify-between">
                        <span class="text-slate-400">Amount Tendered:</span>
                        <span class="font-bold text-white">₱{{ number_format((float) $transaction->amount_tendered, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400">Change Given:</span>
                        <span class="font-bold text-emerald-300">₱{{ number_format((float) $transaction->change_amount, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Authorized Void / Refund Panel (Pharmacist Only, Completed Transactions Only) --}}
    @if($transaction->status === 'Completed' && auth()->user()?->hasRole('pharmacist') && auth()->user()?->hasPermission('pms.pos.refund_void'))
        <div class="bg-white rounded-2xl border border-red-200 p-5 shadow-xs print:hidden">
            <h3 class="text-xs font-extrabold uppercase tracking-wider text-red-700 flex items-center gap-1.5 mb-2">
                <i class="bi bi-shield-exclamation text-sm"></i> Authorized Void / Refund Action
            </h3>
            <p class="text-xs text-slate-500 mb-3">
                Completed POS transactions are never deleted from the financial ledger. Voiding or refunding preserves the full audit trail and deducts from net sales.
            </p>
            <form method="POST" action="{{ route('pharmacy.pos.void-refund', $transaction) }}" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                @csrf
                @method('PATCH')
                <div class="sm:col-span-3">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Action Type</label>
                    <select name="action" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold text-slate-800">
                        <option value="Voided">Void Transaction</option>
                        <option value="Refunded">Refund Transaction</option>
                    </select>
                </div>
                <div class="sm:col-span-6">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Mandatory Audit Reason</label>
                    <input type="text"
                           name="void_reason"
                           required
                           minlength="3"
                           placeholder="State reason for void or refund (e.g. Wrong item punched, customer returned unopened item)..."
                           class="w-full rounded-lg border border-slate-200 px-3 py-2 text-xs text-slate-800">
                </div>
                <div class="sm:col-span-3">
                    <button type="submit"
                            class="w-full py-2 px-4 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-bold cursor-pointer border-0 transition-colors">
                        Confirm Void / Refund
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
@endsection

