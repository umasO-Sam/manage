<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'major_category', 'sub_category', 'item_name', 'is_parts', 'is_internal', 'is_outsourcing'])]
class CategoryCode extends Model
{
    protected function casts(): array
    {
        return [
            'is_parts' => 'boolean',
            'is_internal' => 'boolean',
            'is_outsourcing' => 'boolean',
        ];
    }
}
