<?php

namespace App\Http\Resources;

use App\Support\Quantity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'low_stock_total' => $this['low_stock_total'],
            'movements_per_day' => $this['movements_per_day'],
            'recent_adjustments' => $this['recent_adjustments'],
            'taken_handovers_total' => $this['taken_handovers_total'],
            'total_products' => $this['total_products'],
            'total_quantity' => $this['total_quantity'],
            'low_stock' => $this['low_stock']->map(fn ($product) => [
                'id' => $product->id,
                'base_unit' => UnitResource::make($product->baseUnit),
                'min_stock' => $product->min_stock,
                'name' => $product->name,
                'sku' => $product->sku,
                'total_quantity' => Quantity::format($product->total_quantity ?? 0),
            ]),
            'recent_movements' => MovementResource::collection($this['recent_movements']),
            'stock_by_category' => $this['stock_by_category'],
            'stock_by_warehouse' => $this['stock_by_warehouse']->map(fn ($warehouse) => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'total_quantity' => Quantity::format($warehouse->total_quantity),
            ]),
            'taken_handovers' => HandoverResource::collection($this['taken_handovers']),
        ];
    }
}
