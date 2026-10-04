<?php

namespace App\Http\Controllers;

use App\Http\Requests\Catalog\ServiceRequest;
use App\Models\Service;
use App\Support\Pagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ServiceController extends Controller
{
    /**
     * List services, searchable by name, description or custom field label.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $services = Service::query()
            ->orderBy('name')
            ->get()
            ->when($search !== '', fn ($services) => $services->filter(
                fn (Service $service): bool => $this->matches($service, $search),
            ))
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();

        return view('services.index', [
            'services' => new LengthAwarePaginator(
                $services->forPage($page, Pagination::PER_PAGE)->values(),
                $services->count(),
                Pagination::PER_PAGE,
                $page,
                ['path' => $request->url()],
            ),
            'search' => $search,
            'fieldTypes' => Service::FIELD_TYPES,
            'canEdit' => $request->user()->can('services.edit'),
            'canDelete' => $request->user()->can('services.delete'),
        ]);
    }

    /**
     * Create a service.
     */
    public function store(ServiceRequest $request): JsonResponse
    {
        $service = Service::query()->create($request->serviceAttributes());

        $message = "Service \"{$service->name}\" added.";

        session()->flash('status', $message);

        return response()->json(['message' => $message], 201);
    }

    /**
     * Update a service and its custom fields.
     */
    public function update(ServiceRequest $request, Service $service): JsonResponse
    {
        $service->update($request->serviceAttributes());

        $message = "Service \"{$service->name}\" updated.";

        session()->flash('status', $message);

        return response()->json(['message' => $message]);
    }

    /**
     * Delete a service. Bills and jobs keep their own copy of the service lines.
     */
    public function destroy(Service $service): RedirectResponse
    {
        Gate::authorize('services.delete');

        $service->delete();

        return to_route('services.index')->with('status', "Service \"{$service->name}\" deleted.");
    }

    /**
     * Determine whether the service's name, description or a custom field label contains the term.
     */
    private function matches(Service $service, string $term): bool
    {
        $haystacks = [
            $service->name,
            (string) $service->description,
            ...array_map(fn (array $field): string => (string) ($field['label'] ?? ''), $service->custom_fields ?? []),
        ];

        return collect($haystacks)->contains(fn (string $haystack): bool => Str::contains($haystack, $term, ignoreCase: true));
    }
}
