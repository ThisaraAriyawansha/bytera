<?php

namespace App\Http\Controllers;

use App\Support\DataTables;
use App\Support\Permissions;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataToolController extends Controller
{
    /**
     * Stream a table as a CSV or JSON download.
     */
    public function export(Request $request, string $table, string $format): StreamedResponse
    {
        $this->authorizeSuperAdmin($request);
        abort_unless(array_key_exists($table, DataTables::TABLES), 404);

        $columns = array_values(array_diff(
            Schema::getColumnListing($table),
            DataTables::HIDDEN_COLUMNS[$table] ?? [],
        ));
        $filename = $table.'-'.now()->format('Y-m-d-His').'.'.$format;

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($table, $columns): void {
                $output = fopen('php://output', 'w');
                fputcsv($output, $columns, escape: '');

                foreach (DB::table($table)->select($columns)->cursor() as $row) {
                    fputcsv($output, array_values((array) $row), escape: '');
                }

                fclose($output);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($table, $columns): void {
            echo '[';

            foreach (DB::table($table)->select($columns)->cursor() as $index => $row) {
                echo ($index > 0 ? ',' : '')."\n".json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            echo "\n]\n";
        }, $filename, ['Content-Type' => 'application/json']);
    }

    /**
     * Delete every row of a transactional table after a typed confirmation.
     */
    public function clean(Request $request, string $table): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);
        abort_unless(in_array($table, DataTables::CLEANABLE, true), 404);

        $request->validateWithBag('clean', [
            'confirmation' => ['required', 'string', Rule::in([$table])],
        ], [
            'confirmation.in' => "Type {$table} exactly to confirm.",
        ]);

        try {
            $deleted = DB::transaction(fn (): int => DB::table($table)->delete());
        } catch (QueryException $exception) {
            report($exception);

            return to_route('settings.index')->with(
                'error',
                "Can't clean {$table}: other records still point to it. Clean those tables first.",
            );
        }

        return to_route('settings.index')->with('status', "Cleaned {$table}: {$deleted} rows deleted.");
    }

    /**
     * Data tools are Super Admin only.
     */
    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()->role === Permissions::SUPER_ADMIN, 403);
    }
}
