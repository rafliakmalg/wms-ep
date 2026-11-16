<?php

namespace App\Console\Commands;

use App\Models\Handover;
use App\Models\Inventory;
use App\Support\Quantity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StockVerify extends Command
{
    protected $signature = 'stock:verify';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription(__('index.stock.verify'));
    }

    /**
     * @param  array<int, object>  $expected
     * @return array<int, array<int, int|string>>
     */
    private function compare(string $column, array $expected): array
    {
        $stored = Inventory::query()->get()->mapWithKeys(fn (Inventory $row) => [$row->product_id.'-'.$row->rack_id => $row->{$column}]);
        $problems = [];
        $seen = [];

        foreach ($expected as $row) {
            $key = $row->product_id.'-'.$row->rack_id;
            $seen[$key] = true;
            $actual = Quantity::format($stored[$key] ?? 0);

            if ($actual !== Quantity::format($row->total)) {
                $problems[] = [$row->product_id, $row->rack_id, $column, $actual, Quantity::format($row->total)];
            }
        }

        foreach ($stored as $key => $value) {
            $isEmpty = Quantity::format($value) === Quantity::format(0);

            if (! isset($seen[$key]) && ! $isEmpty) {
                [$productId, $rackId] = explode('-', $key);

                $problems[] = [(int) $productId, (int) $rackId, $column, Quantity::format($value), Quantity::format(0)];
            }
        }

        return $problems;
    }

    public function handle(): int
    {
        $problems = [...$this->quantities(), ...$this->incoming()];

        if ($problems === []) {
            $this->info(__('index.stock.verified'));

            return self::SUCCESS;
        }

        $this->table([
            __('index.inventory.product_id'),
            __('index.inventory.rack_id'),
            __('index.stock.column'),
            __('index.stock.stored'),
            __('index.stock.expected'),
        ], $problems);

        return self::FAILURE;
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    private function incoming(): array
    {
        $expected = DB::table('handover_items')
            ->join('handovers', 'handovers.id', '=', 'handover_items.handover_id')
            ->where('handovers.status', Handover::STATUS_TAKEN)
            ->selectRaw('handover_items.product_id, handover_items.rack_to_id as rack_id, SUM(handover_items.quantity) as total')
            ->groupBy('handover_items.product_id', 'handover_items.rack_to_id')
            ->get();

        return $this->compare('quantity_incoming', $expected->all());
    }

    /**
     * @return array<int, array<int, int|string>>
     */
    private function quantities(): array
    {
        $in = DB::table('movements')
            ->selectRaw('product_id, rack_to_id as rack_id, quantity as delta')
            ->whereNotNull('rack_to_id');

        $out = DB::table('movements')
            ->selectRaw('product_id, rack_from_id as rack_id, -quantity as delta')
            ->whereNotNull('rack_from_id');

        $expected = DB::query()
            ->fromSub($in->unionAll($out), 'ledger')
            ->selectRaw('product_id, rack_id, SUM(delta) as total')
            ->groupBy('product_id', 'rack_id')
            ->get();

        return $this->compare('quantity', $expected->all());
    }
}
