<?php

namespace App\Services\Queue;

use App\Models\QueueSequence;
use App\Models\QueueTicket;
use App\Services\Settings\SettingsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class QueueTicketService
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * Generate the next atomic queue ticket for a given queue type.
     * Supported types: 'diagnostic', 'treatment'.
     */
    public function issueTicket(string $queueType, ?int $issuedBy = null, ?Carbon $date = null): QueueTicket
    {
        $today = ($date ?? now())->toDateString();

        if (! in_array($queueType, ['diagnostic', 'treatment'], true)) {
            throw new \InvalidArgumentException("Unsupported queue type [{$queueType}].");
        }

        $prefixKey = $queueType === 'diagnostic'
            ? 'queue.prefix_diagnostic'
            : 'queue.prefix_treatment';

        $prefix = (string) $this->settings->get($prefixKey, $queueType === 'diagnostic' ? 'DIAG-' : 'TX-');

        return DB::transaction(function () use ($queueType, $today, $prefix, $issuedBy) {
            // Pessimistic locking on sequence row for date and type to prevent race conditions
            $sequence = QueueSequence::where('queue_type', $queueType)
                ->where('ticket_date', $today)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = QueueSequence::create([
                    'queue_type'  => $queueType,
                    'ticket_date' => $today,
                    'last_number' => 0,
                ]);
                // Re-lock
                $sequence = QueueSequence::where('id', $sequence->id)->lockForUpdate()->first();
            }

            $nextNumber = $sequence->last_number + 1;
            $sequence->update(['last_number' => $nextNumber]);

            $ticketNo = sprintf('%s%s-%04d', $prefix, str_replace('-', '', $today), $nextNumber);

            return QueueTicket::create([
                'queue_type'  => $queueType,
                'ticket_date' => $today,
                'sequence'    => $nextNumber,
                'prefix'      => $prefix,
                'ticket_no'   => $ticketNo,
                'issued_by'   => $issuedBy,
                'created_at'  => now(),
            ]);
        });
    }
}
