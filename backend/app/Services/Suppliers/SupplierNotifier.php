<?php

namespace App\Services\Suppliers;

use App\Models\SchoolNotification;
use App\Models\SupplierDocument;
use App\Support\Tenant;
use Illuminate\Support\Carbon;

/** In-app notices for the owner (approvals waiting) and for owner + accountant (documents expiring). */
final class SupplierNotifier
{
    public static function approvalNeeded(string $type, string $title, string $body, array $data = []): void
    {
        self::send(['owner'], $type, $title, $body, $data);
    }

    /**
     * Document expiry notices, once per stage (30, 14, 7 days out, then expired) so a document does not nag daily.
     *
     * @return int how many documents raised a notice
     */
    public static function sendDueDocumentAlerts(?Carbon $today = null): int
    {
        $today ??= now();
        $sent = 0;

        foreach (SupplierDocument::with('supplier:id,name')->whereNotNull('expiry_date')->get() as $doc) {
            $stage = self::stageFor($doc, $today);
            if ($stage === null || ($doc->last_alert_stage !== null && $stage >= $doc->last_alert_stage)) {
                continue;
            }
            $days = $doc->daysLeft($today);
            $what = __('suppliers.doc_types.'.$doc->type);
            $title = $stage === 0
                ? __('suppliers.notify.doc_expired', ['supplier' => $doc->supplier?->name, 'doc' => $what])
                : __('suppliers.notify.doc_expiring', ['supplier' => $doc->supplier?->name, 'doc' => $what, 'days' => $days]);

            self::send(['owner', 'accountant'], 'supplier_document_expiry', $title,
                __('suppliers.notify.doc_body', ['doc' => $what, 'supplier' => $doc->supplier?->name, 'date' => $doc->expiry_date->toDateString()]),
                ['supplier_id' => $doc->supplier_id, 'document_id' => $doc->id, 'stage' => $stage]);

            $doc->update(['last_alert_stage' => $stage]);
            $sent++;
        }

        return $sent;
    }

    /** 30 / 14 / 7 days before expiry (the tightest one crossed), or 0 once expired; null if still far off. */
    public static function stageFor(SupplierDocument $doc, ?Carbon $today = null): ?int
    {
        $days = $doc->daysLeft($today);
        if ($days === null) {
            return null;
        }
        if ($days < 0) {
            return 0;
        }

        return collect([30, 14, 7])->filter(fn ($d) => $days <= $d)->min();
    }

    private static function send(array $roles, string $type, string $title, string $body, array $data): void
    {
        foreach ($roles as $role) {
            SchoolNotification::create([
                'school_id' => Tenant::school()?->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'recipient_role' => $role,
                'is_read' => false,
            ]);
        }
    }
}
