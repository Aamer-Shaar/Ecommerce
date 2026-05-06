<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailySalesSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_date',
        'total_orders',
        'total_items_sold',
        'total_revenue',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'total_revenue' => 'decimal:2',
    ];
}
