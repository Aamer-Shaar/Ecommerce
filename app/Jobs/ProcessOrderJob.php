<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected Order $order;

      public $tries = 3; //عدد المحاولات

    public function __construct(Order $order)
    {
         $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        sleep(2);
        $this->order->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
