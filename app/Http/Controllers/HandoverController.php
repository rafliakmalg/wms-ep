<?php

namespace App\Http\Controllers;

use App\Http\Requests\Handover\HandoverActionRequest;
use App\Http\Requests\Handover\HandoverRequest;
use App\Http\Requests\Handover\IndexHandoverRequest;
use App\Http\Requests\Handover\TransferHandoverRequest;
use App\Http\Resources\HandoverResource;
use App\Models\Handover;
use App\Models\HandoverLog;
use App\Models\Inventory;
use App\Models\Rack;
use App\Support\Filter;
use App\Support\Quantity;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HandoverController extends Controller
{
    private function assertTaken(Handover $handover): void
    {
        if ($handover->status !== Handover::STATUS_TAKEN) {
            abort(409, __('index.handover.not_taken'));
        }
    }

    #[Authorize('handover.cancel')]
    public function cancel(HandoverActionRequest $request, Handover $handover): HandoverResource
    {
        $param = $request->validated();
        $userId = $request->user()->id;
        $return = new HandoverResource(null);

        DB::transaction(function () use ($param, $handover, $userId, &$return) {
            $lockedHandover = $this->lockHandover($handover);

            if ($lockedHandover->status === Handover::STATUS_CANCELLED) {
                abort(409, __('index.handover.already_cancelled'));
            }

            if ($lockedHandover->status === Handover::STATUS_COMPLETED) {
                $this->reverse($lockedHandover, $userId, $param['note'] ?? null);
            } else {
                $this->close($lockedHandover, Handover::STATUS_CANCELLED, $userId, $param['note'] ?? null);
            }

            $this->loadDetail($lockedHandover);

            $message = __('index.cancelled', ['resource' => __('index.handover')]);

            $return = HandoverResource::make($lockedHandover)->additional(['message' => $message]);
        }, attempts: 5);

        return $return;
    }

    private function close(Handover $handover, string $status, int $userId, ?string $note): void
    {
        $complete = $status === Handover::STATUS_COMPLETED;
        $changes = [];

        foreach ($handover->items as $item) {
            $rackId = $complete ? $item->rack_to_id : $item->rack_from_id;

            $handover->movements()->create([
                'created_by_id' => $userId,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'rack_to_id' => $rackId,
                'updated_by_id' => $userId,
            ]);

            $changes[] = [
                'path' => 'status',
                'product_id' => $item->product_id,
                'quantity_incoming' => '-'.$item->quantity,
                'rack_id' => $item->rack_to_id,
            ];

            $changes[] = [
                'path' => 'status',
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'rack_id' => $rackId,
            ];
        }

        Inventory::applyChanges($changes, $userId);

        $handover->update([
            'status' => $status,
            'updated_by_id' => $userId,
        ]);

        $this->log($handover, $complete ? HandoverLog::ACTION_COMPLETE : HandoverLog::ACTION_CANCEL, $userId, [
            'custody_from_id' => $handover->custody_id,
            'note' => $note,
        ]);
    }

    #[Authorize('handover.complete')]
    public function complete(HandoverActionRequest $request, Handover $handover): HandoverResource
    {
        $param = $request->validated();
        $userId = $request->user()->id;
        $return = new HandoverResource(null);

        DB::transaction(function () use ($param, $handover, $userId, &$return) {
            $lockedHandover = $this->lockHandover($handover);

            $this->assertTaken($lockedHandover);

            $this->close($lockedHandover, Handover::STATUS_COMPLETED, $userId, $param['note'] ?? null);

            $this->loadDetail($lockedHandover);

            $message = __('index.completed', ['resource' => __('index.handover')]);

            $return = HandoverResource::make($lockedHandover)->additional(['message' => $message]);
        }, attempts: 5);

        return $return;
    }

    #[Authorize('handover.read')]
    public function index(IndexHandoverRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = Handover::query()->with('custody');

        if ($param['search']) {
            $query->where(function ($query) use ($param) {
                $query->where('code', 'like', '%'.$param['search'].'%')
                    ->orWhere('note', 'like', '%'.$param['search'].'%');
            });
        }

        Filter::apply($query, $param['filters']);

        $handovers = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return HandoverResource::collection($handovers);
    }

    private function loadDetail(Handover $handover): void
    {
        $handover->load([
            'custody',
            'items.product',
            'items.rackFrom.warehouse',
            'items.rackTo.warehouse',
            'logs.createdBy',
            'logs.custodyFrom',
            'logs.custodyTo',
        ]);
    }

    private function lockHandover(Handover $handover): Handover
    {
        $lockedHandover = Handover::query()
            ->where('id', $handover->id)
            ->lockForUpdate()
            ->firstOrFail();

        return $lockedHandover;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function log(Handover $handover, string $action, int $userId, array $attributes): void
    {
        $handover->logs()->create([
            ...$attributes,
            'action' => $action,
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);
    }

    private function reverse(Handover $handover, int $userId, ?string $note): void
    {
        $changes = [];

        foreach ($handover->items as $item) {
            $handover->movements()->create([
                'created_by_id' => $userId,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'rack_from_id' => $item->rack_to_id,
                'rack_to_id' => $item->rack_from_id,
                'updated_by_id' => $userId,
            ]);

            $changes[] = [
                'path' => 'status',
                'product_id' => $item->product_id,
                'quantity' => '-'.$item->quantity,
                'rack_id' => $item->rack_to_id,
            ];

            $changes[] = [
                'path' => 'status',
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'rack_id' => $item->rack_from_id,
            ];
        }

        Inventory::applyChanges($changes, $userId);

        $handover->update([
            'status' => Handover::STATUS_CANCELLED,
            'updated_by_id' => $userId,
        ]);

        $this->log($handover, HandoverLog::ACTION_CANCEL, $userId, [
            'custody_from_id' => $handover->custody_id,
            'note' => $note,
        ]);
    }

    #[Authorize('handover.read')]
    public function show(Handover $handover): HandoverResource
    {
        $this->loadDetail($handover);

        return HandoverResource::make($handover);
    }

    #[Authorize('handover.create')]
    public function store(HandoverRequest $request): HandoverResource
    {
        $param = $request->validated();
        $userId = $request->user()->id;
        $return = new HandoverResource(null);

        DB::transaction(function () use ($param, $userId, &$return) {
            $handover = Handover::create([
                'code' => (string) Str::uuid(),
                'created_by_id' => $userId,
                'custody_id' => $userId,
                'note' => $param['note'] ?? null,
                'updated_by_id' => $userId,
            ]);

            $handover->update(['code' => sprintf('ST-%06d', $handover->id)]);

            $this->take($handover, $param['items'], $userId);

            $this->log($handover, HandoverLog::ACTION_TAKE, $userId, [
                'custody_to_id' => $userId,
                'note' => $param['note'] ?? null,
            ]);

            $this->loadDetail($handover);

            $message = __('index.created', ['resource' => __('index.handover')]);

            $return = HandoverResource::make($handover)->additional(['message' => $message]);
        }, attempts: 5);

        return $return;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function take(Handover $handover, array $items, int $userId): void
    {
        $destinations = [];

        foreach ($items as $index => $item) {
            $destinations["items.{$index}.rack_to_id"] = $item['rack_to_id'];
        }

        Rack::lockAndCheckActive($destinations, 'index.handover.items.rack_to_id');

        $changes = [];

        foreach ($items as $index => $item) {
            $quantity = Quantity::format($item['quantity']);

            $handover->items()->create([
                ...$item,
                'quantity' => $quantity,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            $handover->movements()->create([
                'created_by_id' => $userId,
                'product_id' => $item['product_id'],
                'quantity' => $quantity,
                'rack_from_id' => $item['rack_from_id'],
                'updated_by_id' => $userId,
            ]);

            $changes[] = [
                'path' => "items.{$index}.quantity",
                'product_id' => $item['product_id'],
                'quantity' => '-'.$quantity,
                'rack_id' => $item['rack_from_id'],
            ];

            $changes[] = [
                'path' => "items.{$index}.quantity",
                'product_id' => $item['product_id'],
                'quantity_incoming' => $quantity,
                'rack_id' => $item['rack_to_id'],
            ];
        }

        Inventory::applyChanges($changes, $userId);
    }

    #[Authorize('handover.transfer')]
    public function transfer(TransferHandoverRequest $request, Handover $handover): HandoverResource
    {
        $param = $request->validated();
        $userId = $request->user()->id;
        $return = new HandoverResource(null);

        DB::transaction(function () use ($param, $handover, $userId, &$return) {
            $lockedHandover = $this->lockHandover($handover);

            $this->assertTaken($lockedHandover);

            $custodyFromId = $lockedHandover->custody_id;

            $lockedHandover->update([
                'custody_id' => $param['custody_to_id'],
                'updated_by_id' => $userId,
            ]);

            $this->log($lockedHandover, HandoverLog::ACTION_TRANSFER, $userId, [
                'custody_from_id' => $custodyFromId,
                'custody_to_id' => $param['custody_to_id'],
                'note' => $param['note'] ?? null,
            ]);

            $this->loadDetail($lockedHandover);

            $message = __('index.transferred', ['resource' => __('index.handover')]);

            $return = HandoverResource::make($lockedHandover)->additional(['message' => $message]);
        }, attempts: 5);

        return $return;
    }
}
