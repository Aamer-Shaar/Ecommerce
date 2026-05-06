<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyProductSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_date',
        'product_id',
        'total_quantity_sold',
        'total_revenue',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'total_revenue' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
