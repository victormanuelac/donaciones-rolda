<?php

namespace App\Console\Commands\Kardex;

use App\Enums\ExpiryAlertType;
use App\Enums\StockEntryStatus;
use App\Models\ExpiryAlert;
use App\Models\StockEntry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kardex:update-stock-entry-statuses')]
#[Description('Marca vencidos/retirados los lotes que correspondan y genera alertas de vencimiento próximo')]
class UpdateStockEntryStatuses extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $expired = 0;
        $withdrawn = 0;
        $alertsCreated = 0;

        StockEntry::query()
            ->whereIn('status', [StockEntryStatus::Available->value, StockEntryStatus::Expired->value])
            ->chunkById(200, function ($entries) use (&$expired, &$withdrawn, &$alertsCreated) {
                foreach ($entries as $entry) {
                    if ($entry->availableQuantity() <= 0) {
                        $entry->update(['status' => StockEntryStatus::Withdrawn]);
                        $withdrawn++;

                        continue;
                    }

                    if ($entry->status === StockEntryStatus::Available && $entry->expiry_date?->isPast()) {
                        $entry->update(['status' => StockEntryStatus::Expired]);
                        $expired++;
                    }

                    if ($this->createAlertIfDue($entry)) {
                        $alertsCreated++;
                    }
                }
            });

        $this->info("Lotes marcados como vencidos: {$expired}. Lotes marcados como retirados: {$withdrawn}. Alertas nuevas: {$alertsCreated}.");
    }

    private function createAlertIfDue(StockEntry $entry): bool
    {
        if ($entry->expiry_date === null) {
            return false;
        }

        $daysRemaining = (int) now()->startOfDay()->diffInDays($entry->expiry_date, false);
        $alertType = ExpiryAlertType::fromDaysRemaining($daysRemaining);

        if ($alertType === null) {
            return false;
        }

        $alreadyExists = ExpiryAlert::where('stock_entry_id', $entry->id)
            ->where('alert_type', $alertType)
            ->exists();

        if ($alreadyExists) {
            return false;
        }

        ExpiryAlert::create([
            'stock_entry_id' => $entry->id,
            'alert_type' => $alertType,
        ]);

        return true;
    }
}
