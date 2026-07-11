<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Car extends Model
{
    use HasFactory;

    protected $fillable = [
        'vin',
        'car_make_id',
        'car_model_id',
        'make',
        'model',
        'year',
        'color',
        'mileage',
        'status',
        'purchase_price',
        'sale_price',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'mileage' => 'integer',
            'purchase_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
        ];
    }

    public function makeRelation(): BelongsTo
    {
        return $this->belongsTo(CarMake::class, 'car_make_id');
    }

    public function modelRelation(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'car_model_id');
    }
}
