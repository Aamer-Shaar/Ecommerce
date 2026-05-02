<?php

namespace App\Jobs;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Inventory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateOrderJob implements ShouldQueue
{
    use Dispatchable;

    /**
     * Create a new job instance.
     */
     protected $userId;
    public function __construct($userId)
    {
       
    }

    public function handle()
    {
        //
    }
}