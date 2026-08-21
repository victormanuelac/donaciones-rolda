<?php

declare(strict_types=1);

namespace App\Actions\MasterItems;

use App\Enums\MasterItemStatus;
use App\Models\AuditLog;
use App\Models\MasterItem;
use App\Models\User;

class RejectMasterItemAction
{
    public function handle(MasterItem $item, User $admin, string $rejectionReason): MasterItem
    {
        $item->update([
            'status' => MasterItemStatus::Rejected,
            'rejection_reason' => $rejectionReason,
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'master_item_rejected',
            'table_name' => 'master_items',
            'record_id' => $item->id,
            'new_value' => ['rejection_reason' => $rejectionReason],
        ]);

        return $item;
    }
}
