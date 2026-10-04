<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'phone', 'email', 'address', 'notify_emails'])]
class ShopSetting extends Model
{
    /**
     * The shop name used when no settings row exists yet.
     */
    public const DEFAULT_NAME = 'M-Fixpro';

    /**
     * Get the single shop settings row, or unsaved defaults if it has not been seeded.
     */
    public static function current(): self
    {
        return static::query()->first() ?? new static([
            'name' => self::DEFAULT_NAME,
            'phone' => '',
            'notify_emails' => [],
        ]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'notify_emails' => 'array',
        ];
    }
}
