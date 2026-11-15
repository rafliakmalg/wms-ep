<?php

namespace App\Http\Controllers;

use App\Http\Requests\Product\IndexProductRequest;
use App\Http\Requests\Product\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Support\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(IndexProductRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = Product::query()->with(['baseUnit', 'category', 'productUnits.unit']);

        if ($param['search']) {
            $query->where(function ($query) use ($param) {
                $query->where('sku', 'like', '%'.$param['search'].'%')
                    ->orWhere('name', 'like', '%'.$param['search'].'%');
            });
        }

        Filter::apply($query, $param['filters']);

        $products = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return ProductResource::collection($products);
    }

    public function store(ProductRequest $request): ProductResource
    {
        $param = $request->validated();
        $userId = $request->user()->id;
        $return = new ProductResource(null);

        DB::transaction(function () use ($param, $userId, &$return) {
            $paramUnits = Arr::pull($param, 'units');

            $product = Product::create([
                ...$param,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            $this->createProductUnits($product, $paramUnits, $userId);

            $product->load(['baseUnit', 'category', 'productUnits.unit']);

            $return = ProductResource::make($product)->additional(['message' => __('index.created', ['resource' => __('index.tables.products.name')])]);
        });

        return $return;
    }

    public function show(Product $product): ProductResource
    {
        $product->load(['baseUnit', 'category', 'productUnits.unit']);

        return ProductResource::make($product);
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        $param = $request->validated();
        $userId = $request->user()->id;
        $return = new ProductResource(null);

        DB::transaction(function () use ($param, $product, $userId, &$return) {
            $paramUnits = Arr::pull($param, 'units');

            $product->update([
                ...$param,
                'updated_by_id' => $userId,
            ]);

            $product->productUnits()->delete();
            $this->createProductUnits($product, $paramUnits, $userId);

            $product->load(['baseUnit', 'category', 'productUnits.unit']);

            $return = ProductResource::make($product)->additional(['message' => __('index.updated', ['resource' => __('index.tables.products.name')])]);
        });

        return $return;
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => __('index.deleted', ['resource' => __('index.tables.products.name')])]);
    }

    /**
     * @param  array<int, array{unit_id: int, factor: numeric}>  $units
     */
    private function createProductUnits(Product $product, array $units, int $userId): void
    {
        $productUnits = [];

        foreach ($units as $unit) {
            $productUnits[] = [
                ...$unit,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ];
        }

        $product->productUnits()->createMany($productUnits);
    }
}
