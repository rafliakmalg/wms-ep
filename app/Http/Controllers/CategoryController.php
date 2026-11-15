<?php

namespace App\Http\Controllers;

use App\Http\Requests\Category\CategoryRequest;
use App\Http\Requests\Category\IndexCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\Filter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(IndexCategoryRequest $request): AnonymousResourceCollection
    {
        $param = $request->validated();

        $query = Category::query();

        if ($param['search']) {
            $query->where('name', 'like', '%'.$param['search'].'%');
        }

        Filter::apply($query, $param['filters']);

        $categories = $query
            ->orderBy($param['sort_by'], $param['sort_dir'])
            ->paginate($param['per_page'])
            ->withQueryString();

        return CategoryResource::collection($categories);
    }

    public function store(CategoryRequest $request): CategoryResource
    {
        $param = $request->validated();

        $category = Category::create([
            ...$param,
            'created_by_id' => $request->user()->id,
            'updated_by_id' => $request->user()->id,
        ]);

        $message = __('index.created', ['resource' => __('index.category')]);

        return CategoryResource::make($category)->additional(['message' => $message]);
    }

    public function show(Category $category): CategoryResource
    {
        return CategoryResource::make($category);
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        $param = $request->validated();

        $category->update([
            ...$param,
            'updated_by_id' => $request->user()->id,
        ]);

        $message = __('index.updated', ['resource' => __('index.category')]);

        return CategoryResource::make($category)->additional(['message' => $message]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json(['message' => __('index.deleted', ['resource' => __('index.category')])]);
    }
}
