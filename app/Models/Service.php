<?php

namespace App\Models;

use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'default_price', 'description', 'custom_fields', 'active'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    /**
     * Custom field types and the labels shown in the field builder.
     *
     * @var array<string, string>
     */
    public const FIELD_TYPES = [
        'text' => 'Text',
        'number' => 'Number',
        'textarea' => 'Long text',
        'checkbox' => 'Checkbox',
        'select' => 'Dropdown',
        'date' => 'Date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'custom_fields' => 'array',
            'active' => 'boolean',
        ];
    }
}
