<?php

namespace App\Services\Suppliers;

use App\Models\AuditLog;
use App\Models\GoodsReceipt;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Supplier;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Receiving a delivery adds each line to stock (a stock-in transaction tied to the receipt);
 * voiding it takes the same quantities out again.
 */
class GoodsReceiptService
{
    /** @param  array  $items  [['inventory_item_id', 'quantity', 'unit_cost_cents'?, 'notes'?], ...] */
    public function receive(Supplier $supplier, array $data, array $items): GoodsReceipt
    {
        return DB::transaction(function () use ($supplier, $data, $items) {
            $grn = GoodsReceipt::create(Tenant::stripSchoolId([
                'school_id' => $supplier->school_id,
                'supplier_id' => $supplier->id,
                'grn_number' => SupplierNumbering::grnNumber(),
                'receipt_date' => $data['receipt_date'],
                'delivery_note_no' => $data['delivery_note_no'] ?? null,
                'supplier_bill_id' => $data['supplier_bill_id'] ?? null,
                'status' => GoodsReceipt::RECEIVED,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => Tenant::actorId(),
            ]));

            foreach ($items as $line) {
                $item = InventoryItem::lockForUpdate()->findOrFail($line['inventory_item_id']);
                if ($item->type !== 'consumable') {
                    throw ValidationException::withMessages(['items' => __('suppliers.err.grn_consumable_only')]);
                }
                $cost = (int) ($line['unit_cost_cents'] ?? 0);

                $grn->items()->create([
                    'inventory_item_id' => $item->id,
                    'quantity' => $line['quantity'],
                    'unit_cost_cents' => $cost,
                    'notes' => $line['notes'] ?? null,
                ]);
                InventoryTransaction::create(Tenant::stripSchoolId([
                    'item_id' => $item->id,
                    'school_id' => $item->school_id,
                    'type' => 'in',
                    'quantity' => $line['quantity'],
                    'reference' => $grn->grn_number,
                    'notes' => __('suppliers.grn_stock_note', ['supplier' => $supplier->name]),
                    'transaction_date' => $data['receipt_date'],
                    'recorded_by' => Tenant::actorId(),
                    'supplier_id' => $supplier->id,
                    'delivery_note_no' => $data['delivery_note_no'] ?? null,
                    'goods_receipt_id' => $grn->id,
                ]));

                $item->increment('quantity', (float) $line['quantity']);
                $update = ['supplier_id' => $supplier->id];
                if ($cost > 0) {
                    $update['unit_cost_cents'] = $cost;
                }
                $item->update($update);
            }
            AuditLog::record('goods_receipt.created', $grn, [], $grn->toArray());

            return $grn->load('items');
        });
    }

    public function void(GoodsReceipt $grn, string $reason): GoodsReceipt
    {
        return DB::transaction(function () use ($grn, $reason) {
            $grn = GoodsReceipt::lockForUpdate()->findOrFail($grn->id);
            if ($grn->status !== GoodsReceipt::RECEIVED) {
                throw ValidationException::withMessages(['status' => __('suppliers.err.grn_already_void')]);
            }

            foreach ($grn->items as $line) {
                $item = InventoryItem::lockForUpdate()->findOrFail($line->inventory_item_id);
                if ((float) $item->quantity < (float) $line->quantity) {
                    throw ValidationException::withMessages(['status' => __('suppliers.err.grn_stock_used', ['item' => $item->name])]);
                }
            }
            foreach ($grn->items as $line) {
                $item = InventoryItem::findOrFail($line->inventory_item_id);
                InventoryTransaction::create(Tenant::stripSchoolId([
                    'item_id' => $item->id,
                    'school_id' => $item->school_id,
                    'type' => 'out',
                    'quantity' => $line->quantity,
                    'reference' => $grn->grn_number,
                    'notes' => __('suppliers.grn_void_note', ['reason' => $reason]),
                    'transaction_date' => now()->toDateString(),
                    'recorded_by' => Tenant::actorId(),
                    'supplier_id' => $grn->supplier_id,
                    'goods_receipt_id' => $grn->id,
                ]));
                $item->decrement('quantity', (float) $line->quantity);
            }
            $grn->update(['status' => GoodsReceipt::VOID, 'voided_by' => Tenant::actorId(), 'voided_at' => now(), 'void_reason' => $reason]);
            AuditLog::record('goods_receipt.voided', $grn, [], ['reason' => $reason]);

            return $grn;
        });
    }
}
