<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Part extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_id',
        'name',
        'name_ru',
        'name_hy',
        'sku',
        'category',
        'condition',
        'description',
        'price',
        'quantity',
        'status',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'car_id' => 'integer',
            'price' => 'decimal:2',
            'quantity' => 'integer',
        ];
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
