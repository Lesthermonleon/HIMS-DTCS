@extends('layouts.app')

@section('title', 'Low-Stock Replenishment Requests (Static Demo)')

@section('content')
<div class="space-y-5">

    {{-- Page Header --}}
    <div class="bg-gradient-to-r from-slate-900 via-amber-950 to-slate-900 rounded-2xl p-5 text-white shadow-sm border border-amber-800/50 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-start gap-3.5">
            <div class="w-11 h-11 rounded-xl bg-amber-500/20 border border-amber-400/30 flex items-center justify-center text-amber-300 shrink-0">
                <i class="bi bi-send-exclamation-fill text-xl"></i>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-lg sm:text-xl font-black tracking-tight text-white">Low-Stock Replenishment Requests</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-amber-400 text-slate-950">
                        Demo / Not Transmitted
                    </span>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-amber-500/20 text-amber-200 border border-amber-400/30">
                        Static Demonstration Only
                    </span>
                </div>
                <p class="text-xs text-slate-300 mt-0.5">
                    Static Demo / Simulated Request to Inventory Subsystem &bull; Prepare, preview, and simulate low-stock medicine replenishment requests without modifying actual inventory stocks or generating live supplier purchase orders.
                </p>
            </div>
        </div>
    </div>

    @include('pharmacy.inventory._subnav')

    {{-- Prominent Demo Boundary Notice --}}
    <div class="bg-amber-50 border border-amber-300 rounded-xl p-4 flex items-start gap-3 text-xs text-amber-950 shadow-2xs">
        <i class="bi bi-exclamation-triangle-fill text-amber-600 text-lg shrink-0 mt-0.5"></i>
        <div>
            <div class="font-extrabold uppercase tracking-wider text-amber-900">
                Static Demo / Simulated Request to Inventory Subsystem (Demo / Not Transmitted)
            </div>
            <p class="mt-0.5 text-amber-800">
                This workflow is for <strong>Static Demonstration Only</strong>. Submitting a replenishment request here records a local demo entry in PMS (`is_demo = true`), performs <strong>zero external API calls</strong>, creates <strong>no live Purchase Orders</strong>, and leaves actual medicine stock quantities untouched.
            </p>
        </div>
    </div>

    @if ($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-xs text-red-700">
            <div class="font-bold mb-1">Please fix the following validation issues:</div>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Replenishment Request Preparation Form (Pharmacist Only) --}}
    @if(auth()->user()?->hasRole('pharmacist'))
        <form method="POST"
              action="{{ route('pharmacy.inventory.replenishment.store') }}"
              id="demoReplenishmentForm"
              class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
            @csrf

            <div class="px-5 py-4 bg-slate-900 text-white flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-extrabold text-white">1. Prepare Simulated Low-Stock Replenishment Request</h2>
                    <p class="text-[11px] text-slate-400">Select one or more low-stock or out-of-stock medicines below and specify requested replenishment quantities</p>
                </div>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-400/30">
                    Low / Out of Stock Count: {{ $lowStockMedicines->count() }}
                </span>
            </div>

            <div class="p-5 space-y-5">
                {{-- Low-Stock & Out-of-Stock Medicines Selection Table --}}
                <div class="border border-slate-200 rounded-xl overflow-hidden">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-extrabold text-slate-500">
                                <th class="py-2.5 px-3 w-10 text-center">Select</th>
                                <th class="py-2.5 px-3">Code</th>
                                <th class="py-2.5 px-3">Medicine Name &amp; Strength</th>
                                <th class="py-2.5 px-3 text-center">Current Stock</th>
                                <th class="py-2.5 px-3 text-center">Reorder Level</th>
                                <th class="py-2.5 px-3 text-center">Status</th>
                                <th class="py-2.5 px-3 text-center w-44">Requested Qty</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($catalog as $idx => $med)
                                @php
                                    $isPrechecked = $med['is_low_or_out'];
                                @endphp
                                <tr class="{{ $med['is_low_or_out'] ? 'bg-amber-50/40' : '' }} hover:bg-slate-50">
                                    <td class="py-2.5 px-3 text-center">
                                        <input type="checkbox"
                                               id="rep_chk_{{ $idx }}"
                                               class="rep-med-checkbox w-4 h-4 accent-teal-600 rounded cursor-pointer"
                                               data-idx="{{ $idx }}"
                                               data-code="{{ $med['code'] }}"
                                               data-name="{{ $med['medication_name'] }}"
                                               data-stock="{{ $med['available_quantity'] }}"
                                               data-reorder="{{ $med['reorder_level'] }}"
                                               data-unit="{{ $med['unit'] }}"
                                               onchange="repToggleRow({{ $idx }})"
                                               {{ $isPrechecked ? 'checked' : '' }}>
                                    </td>
                                    <td class="py-2.5 px-3 font-mono font-bold text-slate-600">{{ $med['code'] }}</td>
                                    <td class="py-2.5 px-3">
                                        <label for="rep_chk_{{ $idx }}" class="font-extrabold text-slate-800 cursor-pointer">
                                            {{ $med['medication_name'] }}
                                        </label>
                                        <span class="block text-[10px] text-slate-500">{{ $med['brand_name'] }} &bull; {{ $med['strength'] }} {{ $med['dosage_form'] }}</span>
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-black {{ $med['available_quantity'] <= 0 ? 'text-red-600' : ($med['is_low_or_out'] ? 'text-amber-700' : 'text-slate-700') }}">
                                        {{ number_format($med['available_quantity']) }} {{ $med['unit'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center font-semibold text-slate-500">
                                        {{ number_format($med['reorder_level']) }} {{ $med['unit'] }}
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        @if($med['status_key'] === 'out')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700 border border-red-200">Out of Stock</span>
                                        @elseif($med['status_key'] === 'low')
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">Low Stock</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">In Stock</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 px-3 text-center">
                                        <input type="hidden" id="rep_name_{{ $idx }}" name="items[{{ $idx }}][medication_name]" value="{{ $med['medication_name'] }}" {{ $isPrechecked ? '' : 'disabled' }}>
                                        <input type="hidden" id="rep_code_{{ $idx }}" name="items[{{ $idx }}][medicine_code]" value="{{ $med['code'] }}" {{ $isPrechecked ? '' : 'disabled' }}>
                                        <input type="hidden" id="rep_stock_{{ $idx }}" name="items[{{ $idx }}][current_stock]" value="{{ $med['available_quantity'] }}" {{ $isPrechecked ? '' : 'disabled' }}>
                                        <input type="hidden" id="rep_reorder_{{ $idx }}" name="items[{{ $idx }}][reorder_level]" value="{{ $med['reorder_level'] }}" {{ $isPrechecked ? '' : 'disabled' }}>
                                        <input type="hidden" id="rep_unit_{{ $idx }}" name="items[{{ $idx }}][unit]" value="{{ $med['unit'] }}" {{ $isPrechecked ? '' : 'disabled' }}>
                                        <input type="number"
                                               id="rep_qty_{{ $idx }}"
                                               name="items[{{ $idx }}][requested_quantity]"
                                               min="1"
                                               max="50000"
                                               value="{{ $med['suggested_order_qty'] }}"
                                               {{ $isPrechecked ? '' : 'disabled' }}
                                               class="w-28 text-center rounded-lg border border-slate-200 px-2 py-1 text-xs font-extrabold text-slate-800 disabled:bg-slate-100 disabled:text-slate-400">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Department, Priority, Status & Notes --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-slate-50 rounded-xl p-4 border border-slate-200/80">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Requesting Pharmacy / Department
                        </label>
                        <select name="requesting_department" id="repDepartment" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800">
                            @foreach($departments as $dept)
                                <option value="{{ $dept }}">{{ $dept }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Urgency / Priority
                        </label>
                        <select name="priority" id="repPriority" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800">
                            <option value="Normal">Normal</option>
                            <option value="High" selected>High (Low Stock Replenishment)</option>
                            <option value="Urgent">Urgent (Critical Stockout)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Initial Demo Status
                        </label>
                        <select name="status" id="repInitialStatus" class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-800">
                            <option value="Submitted (Demo)" selected>Submitted (Demo)</option>
                            <option value="Pending (Demo)">Pending (Demo)</option>
                            <option value="Draft">Draft</option>
                        </select>
                    </div>

                    <div class="md:col-span-3">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">
                            Pharmacist Remarks / Replenishment Notes
                        </label>
                        <textarea name="notes"
                                  id="repNotes"
                                  rows="2"
                                  placeholder="Enter clinical replenishment remarks (e.g. Urgent restock for Salbutamol Inhaler and Amoxicillin 500mg due to ward demand)..."
                                  class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-800"></textarea>
                    </div>
                </div>

                {{-- Preview & Submit Buttons --}}
                <div class="flex flex-wrap items-center justify-end gap-2.5">
                    <button type="button"
                            onclick="repOpenPreviewModal()"
                            class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold border border-slate-200 cursor-pointer">
                        <i class="bi bi-eye"></i> Preview Simulated Request
                    </button>
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-600 text-slate-950 text-xs font-black shadow-xs border-0 cursor-pointer">
                        <i class="bi bi-send-check-fill"></i> Submit Simulated Replenishment Request (Demo)
                    </button>
                </div>
            </div>
        </form>
    @endif

    {{-- 2. Simulated Replenishment Request History --}}
    <div class="bg-white rounded-2xl border border-slate-200/90 shadow-2xs overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-extrabold text-slate-800">2. Simulated Replenishment Request History</h2>
                <p class="text-[11px] text-slate-500">All requests listed below are local static demonstrations (`Demo / Not Transmitted`)</p>
            </div>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
                Static Demonstration Only
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-extrabold text-slate-500">
                        <th class="py-3 px-4">Request No.</th>
                        <th class="py-3 px-4">Department &amp; Pharmacist</th>
                        <th class="py-3 px-4">Requested Medicines</th>
                        <th class="py-3 px-4 text-center">Priority</th>
                        <th class="py-3 px-4 text-center">Demo Status</th>
                        <th class="py-3 px-4">Notes</th>
                        @if(auth()->user()?->hasRole('pharmacist'))
                            <th class="py-3 px-4 text-right">Simulate Status Transition</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($requests as $req)
                        @php
                            $statusStyle = match($req->status) {
                                'Draft'               => 'bg-slate-100 text-slate-700 border-slate-300',
                                'Pending (Demo)'      => 'bg-amber-50 text-amber-800 border-amber-200',
                                'Submitted (Demo)'    => 'bg-sky-50 text-sky-800 border-sky-200',
                                'Acknowledged (Demo)' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                                'Rejected (Demo)'     => 'bg-red-50 text-red-700 border-red-200',
                                default               => 'bg-slate-100 text-slate-700 border-slate-200',
                            };
                        @endphp
                        <tr class="align-top">
                            <td class="py-3.5 px-4">
                                <span class="font-mono font-extrabold text-slate-800 block">{{ $req->request_no }}</span>
                                <span class="inline-block mt-1 px-1.5 py-0.2 rounded text-[9px] font-extrabold uppercase bg-amber-100 text-amber-900">
                                    Demo / Not Transmitted
                                </span>
                                <span class="block text-[10px] text-slate-400 mt-0.5">{{ $req->created_at?->format('M d, Y h:i A') }}</span>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-800">{{ $req->requesting_department }}</div>
                                <div class="text-[11px] text-slate-500">By: {{ $req->requestedBy?->name ?? 'Pharmacist' }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                <ul class="space-y-1">
                                    @foreach($req->items_payload ?? [] as $line)
                                        <li class="text-xs text-slate-700">
                                            <span class="font-bold text-slate-900">{{ $line['medication_name'] ?? 'Medicine' }}</span>
                                            &mdash; <span class="font-extrabold text-teal-700">Req: {{ number_format((int) ($line['requested_quantity'] ?? 0)) }} {{ $line['unit'] ?? 'units' }}</span>
                                            <span class="text-[10px] text-slate-400">(Stock at req: {{ $line['current_stock'] ?? 0 }})</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $req->priority === 'Urgent' ? 'bg-red-100 text-red-700' : ($req->priority === 'High' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700') }}">
                                    {{ $req->priority }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border {{ $statusStyle }}">
                                    {{ $req->status }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-slate-600 max-w-xs">
                                {{ $req->notes ?: '—' }}
                            </td>
                            @if(auth()->user()?->hasRole('pharmacist'))
                                <td class="py-3.5 px-4 text-right">
                                    <form method="POST" action="{{ route('pharmacy.inventory.replenishment.status', $req) }}" class="inline-flex items-center gap-1.5">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="rounded-lg border border-slate-200 px-2 py-1 text-[11px] font-semibold text-slate-700">
                                            @foreach($demoStatuses as $st)
                                                <option value="{{ $st }}" {{ $req->status === $st ? 'selected' : '' }}>{{ $st }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-white text-[11px] font-bold border-0 cursor-pointer">
                                            Update
                                        </button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400">
                                No simulated replenishment requests have been recorded yet. Use the form above to submit a static demo request.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($requests->hasPages())
            <div class="p-4 border-t border-slate-100">
                {{ $requests->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Preview Modal --}}
<div id="repPreviewModal" class="hidden fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-xl border border-slate-200 overflow-hidden">
        <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-eye text-amber-400"></i>
                <h3 class="text-sm font-bold">Simulated Replenishment Request Preview (Demo)</h3>
            </div>
            <button type="button" onclick="document.getElementById('repPreviewModal').classList.add('hidden')" class="text-slate-400 hover:text-white bg-transparent border-0 cursor-pointer">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="p-5 space-y-3 text-xs">
            <div class="bg-amber-50 border border-amber-200 rounded-lg p-2.5 text-amber-900 font-bold text-center">
                DEMO / NOT TRANSMITTED &bull; STATIC DEMONSTRATION ONLY
            </div>
            <div id="repPreviewContent" class="space-y-2"></div>
        </div>
    </div>
</div>

<script>
    function repToggleRow(idx) {
        const chk = document.getElementById('rep_chk_' + idx);
        const fields = ['name', 'code', 'stock', 'reorder', 'unit', 'qty'];
        fields.forEach(prefix => {
            const el = document.getElementById('rep_' + prefix + '_' + idx);
            if (el) {
                el.disabled = !chk.checked;
            }
        });
    }

    function repOpenPreviewModal() {
        const dept = document.getElementById('repDepartment')?.value || '';
        const priority = document.getElementById('repPriority')?.value || '';
        const status = document.getElementById('repInitialStatus')?.value || '';
        const notes = document.getElementById('repNotes')?.value || 'None';

        const selectedRows = [];
        document.querySelectorAll('.rep-med-checkbox:checked').forEach(chk => {
            const idx = chk.dataset.idx;
            const qty = document.getElementById('rep_qty_' + idx)?.value || 0;
            selectedRows.push({
                name: chk.dataset.name,
                stock: chk.dataset.stock,
                qty: qty,
                unit: chk.dataset.unit
            });
        });

        let itemsHtml = selectedRows.length === 0
            ? '<p class="text-red-600 font-bold">No medicines selected.</p>'
            : '<ul class="list-disc list-inside space-y-1">' + selectedRows.map(r => `<li><strong>${r.name}</strong>: Requesting <strong>${r.qty} ${r.unit}</strong> (Current Stock: ${r.stock})</li>`).join('') + '</ul>';

        document.getElementById('repPreviewContent').innerHTML = `
            <div><strong>Department:</strong> ${dept}</div>
            <div><strong>Priority:</strong> ${priority} &bull; <strong>Status:</strong> ${status}</div>
            <div><strong>Notes:</strong> ${notes}</div>
            <div class="pt-2 border-t border-slate-200"><strong>Selected Medicines:</strong>${itemsHtml}</div>
        `;
        document.getElementById('repPreviewModal').classList.remove('hidden');
    }
</script>
@endsection

