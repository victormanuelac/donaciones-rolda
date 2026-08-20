<?php

namespace App\Console\Commands\Kardex;

use App\Enums\StockEntryStatus;
use App\Models\StockEntry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kardex:update-stock-entry-statuses')]
#[Description('Marca como vencidos los lotes con fecha de vencimiento pasada y como retirados los que ya se agotaron')]
class UpdateStockEntryStatuses extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $expired = 0;
        $withdrawn = 0;

        StockEntry::query()
            ->whereIn('status', [StockEntryStatus::Available->value, StockEntryStatus::Expired->value])
            ->chunkById(200, function ($entries) use (&$expired, &$withdrawn) {
                foreach ($entries as $entry) {
                    if ($entry->availableQuantity() <= 0) {
                        $entry->update(['status' => StockEntryStatus::Withdrawn]);
                        $withdrawn++;
                    } elseif ($entry->status === StockEntryStatus::Available && $entry->expiry_date?->isPast()) {
                        $entry->update(['status' => StockEntryStatus::Expired]);
                        $expired++;
                    }
                }
            });

        $this->info("Lotes marcados como vencidos: {$expired}. Lotes marcados como retirados: {$withdrawn}.");
    }
}
