<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\MainCategoryRequest;
use App\Http\Requests\Catalog\SubCategoryRequest;
use App\Models\MainCategory;
use App\Models\Product;
use App\Models\SubCategory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /**
     * List the main categories, each with its sub categories.
     */
    public function index(Request $request): View
    {
        return view('categories.index', [
            'mainCategories' => MainCategory::query()
                ->withCount('products')
                ->with(['subCategories' => fn (HasMany $query) => $query->withCount('products')->orderBy('name')])
                ->orderBy('name')
                ->get(),
            'canDelete' => $request->user()->can('categories.delete'),
        ]);
    }

    /**
     * Add a main category.
     */
    public function storeMain(MainCategoryRequest $request): JsonResponse
    {
        $mainCategory = MainCategory::query()->create($request->validated());

        return $this->saved("Main category \"{$mainCategory->name}\" added.", 201);
    }

    /**
     * Edit a main category.
     */
    public function updateMain(MainCategoryRequest $request, MainCategory $mainCategory): JsonResponse
    {
        $mainCategory->update($request->validated());

        return $this->saved("Main category \"{$mainCategory->name}\" updated.");
    }

    /**
     * Delete a main category together with its sub categories, unless a product uses any of them.
     */
    public function destroyMain(MainCategory $mainCategory): RedirectResponse
    {
        Gate::authorize('categories.delete');

        $productCount = Product::query()->where('main_category_id', $mainCategory->id)->count();

        if ($productCount > 0) {
            return $this->blocked($mainCategory->name, $productCount);
        }

        $mainCategory->delete();

        return to_route('categories.index')->with('status', "Main category \"{$mainCategory->name}\" and its subcategories deleted.");
    }

    /**
     * Add a sub category under a main category.
     */
    public function storeSub(SubCategoryRequest $request): JsonResponse
    {
        $subCategory = SubCategory::query()->create($request->validated());

        return $this->saved("Subcategory \"{$subCategory->name}\" added.", 201);
    }

    /**
     * Edit a sub category (it can be moved to another main category).
     */
    public function updateSub(SubCategoryRequest $request, SubCategory $subCategory): JsonResponse
    {
        $productCount = $subCategory->products()->count();

        if ($productCount > 0 && $request->integer('main_category_id') !== $subCategory->main_category_id) {
            return response()->json([
                'message' => 'Products use this subcategory, so it cannot be moved.',
                'errors' => ['main_category_id' => ["{$productCount} ".str('product')->plural($productCount).' use this subcategory, so it can\'t be moved to another main category.']],
            ], 422);
        }

        $subCategory->update($request->validated());

        return $this->saved("Subcategory \"{$subCategory->name}\" updated.");
    }

    /**
     * Delete a sub category no product uses.
     */
    public function destroySub(SubCategory $subCategory): RedirectResponse
    {
        Gate::authorize('categories.delete');

        $productCount = $subCategory->products()->count();

        if ($productCount > 0) {
            return $this->blocked($subCategory->name, $productCount);
        }

        $subCategory->delete();

        return to_route('categories.index')->with('status', "Subcategory \"{$subCategory->name}\" deleted.");
    }

    /**
     * Flash the success message and answer the Alpine form.
     */
    private function saved(string $message, int $status = 200): JsonResponse
    {
        session()->flash('status', $message);

        return response()->json(['message' => $message], $status);
    }

    /**
     * Redirect back explaining that the category is still in use.
     */
    private function blocked(string $name, int $productCount): RedirectResponse
    {
        return to_route('categories.index')->with(
            'error',
            "\"{$name}\" is used by {$productCount} ".str('product')->plural($productCount)." and can't be deleted.",
        );
    }
}
