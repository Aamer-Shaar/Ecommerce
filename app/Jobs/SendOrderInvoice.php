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
    /**
     * تطبيق مفهوم "إدارة الموارد": التحكم في عدد العمليات المتوازية (Concurrency Control)
     * باستخدام Redis Funnel نضمن عدم تجاوز قدرة الخادم عند توليد الفواتير.
     */
    Redis::funnel('invoice-generator')
        ->limit(3) // السماح بحد أقصى لـ 3 عمليات توليد PDF متزامنة
        ->block(5) // انتظار لمدة 5 ثوانٍ إذا كان القمع ممتلئاً قبل اتخاذ إجراء
        ->then(function () {
            
            Log::info("بدء إنشاء الفاتورة للطلب رقم: " . $this->order->order_number);

            // 1. توليد الـ PDF من ملف الـ Blade
            $pdf = Pdf::loadView('invoices.order_pdf', ['order' => $this->order]);

            // 2. تحديد مسار الحفظ (باستخدام رقم الطلب الفريد)
            $fileName = 'invoice_' . $this->order->order_number . '.pdf';
            $path = 'public/invoices/' . $fileName;

            $invoiceData = [
                'order_id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'customer' => $this->order->user->name,
                'total' => $this->order->total_amount, // تم التعديل ليطابق الموديل عندك
                'date' => now()->toDateTimeString(),
            ];

            // 3. حفظ الملف في السيرفر
            Storage::put($path, $pdf->output());

            Log::info("تم توليد الفاتورة بنجاح للطلب: " . $this->order->order_number, $invoiceData);

            // 4. تحديث المسار في قاعدة البيانات (تأكد من وجود العمود أولاً)
            // $this->order->update(['invoice_path' => $fileName]);

        }, function () {
            /**
             * في حال كان النظام تحت ضغط عالٍ وهناك أكثر من 3 عمليات تعمل حالياً
             * نقوم بإعادة المهمة إلى الطابور (Release) لتعالج لاحقاً، مما يمنع الانهيار.
             */
            Log::warning("تأجيل توليد الفاتورة للطلب " . $this->order->id . " بسبب ضغط الموارد.");
            return $this->release(10); // إعادة المحاولة بعد 10 ثوانٍ
        });
}
}