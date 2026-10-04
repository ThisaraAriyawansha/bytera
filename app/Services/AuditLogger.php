<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogger
{
    /**
     * Compare a record's current values with an edit and list the allow-listed fields that change (SPEC §8.21).
     *
     * Only fields that are both allow-listed and present in the patch are compared. Numbers compare by value
     * ("1500.00" equals 1500) and an empty string equals null, so re-saving an untouched form changes nothing.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $patch
     * @param  list<string>  $allowedFields
     * @return list<array{field: string, before: mixed, after: mixed}>
     */
    public function diff(array $before, array $patch, array $allowedFields): array
    {
        $changes = [];

        foreach ($allowedFields as $field) {
            if (! array_key_exists($field, $patch)) {
                continue;
            }

            $old = $this->normalize($before[$field] ?? null);
            $new = $this->normalize($patch[$field]);

            if (! $this->same($old, $new)) {
                $changes[] = ['field' => $field, 'before' => $old, 'after' => $new];
            }
        }

        return $changes;
    }

    /**
     * Write an audit log row for an admin edit, unless nothing changed. Call it inside the edit's transaction.
     *
     * @param  list<array{field: string, before: mixed, after: mixed}>  $changes
     */
    public function write(string $collection, int|string $docId, string $label, array $changes, User $user): ?AuditLog
    {
        if ($changes === []) {
            return null;
        }

        return AuditLog::query()->create([
            'collection_name' => $collection,
            'doc_id' => (string) $docId,
            'label' => $label,
            'changes' => $changes,
            'performed_by_id' => $user->id,
            'performed_by_name' => $user->name ?: $user->email,
        ]);
    }

    /**
     * Normalize a value for comparison and storage: strings are trimmed and an empty string becomes null.
     */
    private function normalize(mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === '' ? null : $value;
    }

    /**
     * Determine whether two normalized values are the same. Two numbers compare by value to the cent,
     * so a decimal column's "1500.00" equals an input of 1500; anything else compares as text.
     */
    private function same(mixed $old, mixed $new): bool
    {
        if ($old === null || $new === null) {
            return $old === $new;
        }

        if (is_numeric($old) && is_numeric($new)) {
            return round((float) $old, 2) === round((float) $new, 2);
        }

        return (string) $old === (string) $new;
    }
}
