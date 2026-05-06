<?php

namespace App\Jobs;

use App\Models\Order;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class SendOrderInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    protected $order;
    
    public function __construct(Order $order)
    {// نستخدم load لضمان جلب كافة البيانات المرتبطة (Eager Loading) لتقليل استعلامات قاعدة البيانات
        $this->order = $order->load(['user', 'items.product']);
    }

    /**
     * Execute the job.
     */
  public function handle(): void
{
    Log::info("بدء إنشاء الفاتورة: " . $this->order->order_number);

    $pdf = Pdf::loadView('invoices.order_pdf', ['order' => $this->order]);

    $fileName = 'invoice_' . $this->order->order_number . '.pdf';
    $path = 'public/invoices/' . $fileName;

    Storage::put($path, $pdf->output());

    Log::info("تم إنشاء الفاتورة: " . $this->order->order_number);
}
}