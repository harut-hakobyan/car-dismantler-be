<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\CarMake;
use App\Models\CarModel;

class PartTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'car_make_id',
        'car_model_id',
        'start_year',
        'end_year',
        'name',
        'name_ru',
        'name_hy',
        'sku',
        'category',
        'path',
        'url',
        'image_url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'car_make_id' => 'integer',
            'car_model_id' => 'integer',
            'start_year' => 'integer',
            'end_year' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function make(): BelongsTo
    {
        return $this->belongsTo(CarMake::class, 'car_make_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'car_model_id');
    }
}
