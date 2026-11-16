<?php

namespace App\Http\Controllers;

use App\Http\Requests\Adjustment\AdjustmentRequest;
use App\Http\Requests\Adjustment\IndexAdjustmentRequest;
use App\Http\Resources\AdjustmentResource;
use App\Models\Adjustment;
use App\Models\Inventory;
use App\Support\Filter;
use App\Support\Quantity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdjustmentController extends Controller
{
    public function cancel(Request $request, Adjustment $adjustment): AdjustmentResource
    {
        $userId = $request->user()->id;
        $return = new AdjustmentResource(null);

        DB::transaction(function () use ($adjustment, $userId, &$return) {
            $lockedAdjustment = Adjustment::query()
                ->where('id', $adjustment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAdjustment->status !== Adjustment::STATUS_ACTIVE) {
                abort(409, __('index.adjustment.not_active'));
            }

            $this->reverseItems($lockedAdjustment, $userId);

            $lockedAdjustment->update([
                'status' => Adjustment::STATUS_CANCELLED,
                'updated_by_id' => $userId,
            ]);

            $lockedAdjustment->load(['items.product', 'items.rack.warehouse']);

            $message = __('index.cancelled', ['resource' => __('index.adjustment')]);

            $return = AdjustmentResource::make($lockedAdjustment)->additional(['message' => $message]);
        }, attempts: 5);

        return $return;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function createItems(Adjustment $adjustment, array $items, int $userId): void
    {
        $changes = [];

        foreach ($items as $index => $item) {
            $quantity = Quantity::format($item['quantity']);
            $increase = $quantity > 0;

            $adjustment->items()->create([
                ...$item,
                'quantity' => $quantity,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            $adjustment->movements()->create([
                'created_by_id' => $userId,
                'product_id' => $item['product_id'],
                'quantity' => ltrim($quantity, '-'),
                'rack_from_id' => $increase ? null : $item['rack_id'],
                'rack_to_id' => $increase ? $item['rack_id'] : null,
                'updated_by_id' => $userId,
            ]);

            $changes[] = [
                'path' => "items.{$index}.quantity",
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'rack_id' => $item['rack_id'],
            ];
        }

        Inventory::applyChanges($changes, $userId);
    }

    public function index(IndexAdjustmentRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = Adjustment::query();

        if ($param['search']) {
            $query->where(function ($query) use ($param) {
                $query->where('code', 'like', '%'.$param['search'].'%')
                    ->orWhere('note', 'like', '%'.$param['search'].'%');
            });
        }

        Filter::apply($query, $param['filters']);

        $adjustments = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return AdjustmentResource::collection($adjustments);
    }

    private function reverseItems(Adjustment $adjustment, int $userId): void
    {
        $changes = [];

        foreach ($adjustment->items as $item) {
            $increase = str_starts_with($item->quantity, '-');
            $quantity = ltrim($item->quantity, '-');

            $adjustment->movements()->create([
                'created_by_id' => $userId,
                'product_id' => $item->product_id,
                'quantity' => $quantity,
                'rack_from_id' => $increase ? null : $item->rack_id,
                'rack_to_id' => $increase ? $item->rack_id : null,
                'updated_by_id' => $userId,
            ]);

            $changes[] = [
                'path' => 'status',
                'product_id' => $item->product_id,
                'quantity' => $increase ? $quantity : '-'.$quantity,
                'rack_id' => $item->rack_id,
            ];
        }

        Inventory::applyChanges($changes, $userId);
    }

    public function show(Adjustment $adjustment): AdjustmentResource
    {
        $adjustment->load(['items.product', 'items.rack.warehouse']);

        return AdjustmentResource::make($adjustment);
    }

    public function store(AdjustmentRequest $request): AdjustmentResource
    {
        $param = $request->validated();
        $userId = $request->user()->id;
        $return = new AdjustmentResource(null);

        DB::transaction(function () use ($param, $userId, &$return) {
            $adjustment = Adjustment::create([
                'code' => (string) Str::uuid(),
                'created_by_id' => $userId,
                'note' => $param['note'] ?? null,
                'updated_by_id' => $userId,
            ]);

            $adjustment->update(['code' => sprintf('PN-%06d', $adjustment->id)]);

            $this->createItems($adjustment, $param['items'], $userId);

            $adjustment->load(['items.product', 'items.rack.warehouse']);

            $message = __('index.created', ['resource' => __('index.adjustment')]);

            $return = AdjustmentResource::make($adjustment)->additional(['message' => $message]);
        }, attempts: 5);

        return $return;
    }
}
