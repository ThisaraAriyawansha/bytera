<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\DateRange;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    /**
     * The read-only Audit Log: every admin edit in the From / To range (default last 30 days), filtered by record
     * type and searchable by record number or staff name.
     */
    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request);
        $type = $request->query('type');
        $type = is_string($type) && array_key_exists($type, AuditLog::RECORD_TYPES) ? $type : null;
        $search = trim((string) $request->query('search'));
        $pattern = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';

        return view('audit-log.index', [
            'logs' => AuditLog::query()
                ->whereBetween('created_at', [$range['from'], $range['to']])
                ->when($type !== null, fn (Builder $query) => $query->where('collection_name', $type))
                ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->where('label', 'like', $pattern)
                    ->orWhere('performed_by_name', 'like', $pattern)))
                ->latest()
                ->latest('id')
                ->paginate(Pagination::PER_PAGE)
                ->withQueryString(),
            'type' => $type,
            'search' => $search,
        ]);
    }
}
