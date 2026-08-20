<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\SmsLog;
use App\Models\User;
use App\Services\Sms\SmsService;
use App\Services\Sms\SmsTemplates;
use Illuminate\Support\Facades\Log;

class LowStockAlertService
{
    public function __construct(private SmsService $sms) {}

    /**
     * Send low-stock SMS to all accountants, owners, and superadmins for the item's school.
     * Fires only when quantity has just dropped at or below the reorder level.
     */
    public function alertIfNeeded(InventoryItem $item): void
    {
        if (! $item->isLowStock()) {
            return;
        }

        $school = $item->school()->first();
        if (! $school) {
            return;
        }

        $message = SmsTemplates::lowStockAlert(
            itemName:    $item->name,
            currentQty:  (float) $item->quantity,
            reorderLevel:(float) $item->reorder_level,
            unit:        $item->unit ?? 'kipande',
            schoolName:  $school->name,
        );

        // Collect all staff with alert roles for this school + superadmins across all schools
        $schoolStaff = User::where('school_id', $school->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['accountant', 'owner']))
            ->whereNotNull('phone')
            ->get();

        $superadmins = User::whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))
            ->whereNotNull('phone')
            ->get();

        $recipients = $schoolStaff->merge($superadmins)->unique('id');

        foreach ($recipients as $user) {
            try {
                $ok = $this->sms->send($user->phone, $message);

                SmsLog::create([
                    'school_id'       => $school->id,
                    'sender_id'       => auth()->id(),
                    'recipient_phone' => $user->phone,
                    'message'         => $message,
                    'status'          => $ok ? 'sent' : 'failed',
                    'sent_at'         => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning("[LowStockAlert] SMS failed to {$user->phone}: ".$e->getMessage());
            }
        }
    }
}
