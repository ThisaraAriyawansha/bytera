<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\BrandRequest;
use App\Models\Brand;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BrandController extends Controller
{
    /**
     * List brands with how many products use each one.
     */
    public function index(Request $request): View
    {
        return view('brands.index', [
            'brands' => Brand::query()
                ->withCount('products')
                ->orderBy('name')
                ->paginate(Pagination::PER_PAGE),
            'canDelete' => $request->user()->can('brands.delete'),
        ]);
    }

    /**
     * Add a brand.
     */
    public function store(BrandRequest $request): JsonResponse
    {
        $brand = Brand::query()->create($request->validated());

        $message = "Brand \"{$brand->name}\" added.";

        session()->flash('status', $message);

        return response()->json(['message' => $message], 201);
    }

    /**
     * Rename or re-describe a brand.
     */
    public function update(BrandRequest $request, Brand $brand): JsonResponse
    {
        $brand->update($request->validated());

        $message = "Brand \"{$brand->name}\" updated.";

        session()->flash('status', $message);

        return response()->json(['message' => $message]);
    }

    /**
     * Delete a brand that no product uses.
     */
    public function destroy(Brand $brand): RedirectResponse
    {
        Gate::authorize('brands.delete');

        $productCount = $brand->products()->count();

        if ($productCount > 0) {
            return to_route('brands.index')->with(
                'error',
                "\"{$brand->name}\" is used by {$productCount} ".str('product')->plural($productCount)." and can't be deleted.",
            );
        }

        $brand->delete();

        return to_route('brands.index')->with('status', "Brand \"{$brand->name}\" deleted.");
    }
}
