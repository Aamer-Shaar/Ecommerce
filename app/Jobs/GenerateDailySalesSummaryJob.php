<?php

namespace App\Jobs;

use App\Models\DailyProductSale;
use App\Models\DailySalesSummary;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class GenerateDailySalesSummaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(public string $saleDate)
    {
        $this->onQueue('reports');
    }

    public function handle(): void
    {
        $date = Carbon::parse($this->saleDate)->toDateString();
        $startOfDay = Carbon::parse($date)->startOfDay();
        $endOfDay = Carbon::parse($date)->endOfDay();

        $summary = [
            'sale_date' => $date,
            'total_orders' => 0,
            'total_items_sold' => 0,
            'total_revenue' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $productSales = [];

        Order::query()
            ->where('status', 'completed')
            ->whereBetween('completed_at', [$startOfDay, $endOfDay])
            ->select(['id', 'total_amount'])
            ->orderBy('id')
            ->chunkById(500, function ($orders) use (&$summary, &$productSales) {
                if ($orders->isEmpty()) {
                    return;
                }

                $orderIds = $orders->pluck('id');
                $summary['total_orders'] += $orders->count();
                $summary['total_revenue'] += (float) $orders->sum('total_amount');

                $aggregatedItems = OrderItem::query()
                    ->whereIn('order_id', $orderIds)
                    ->select([
                        'product_id',
                        DB::raw('SUM(quantity) as total_quantity_sold'),
                        DB::raw('SUM(quantity * price) as total_revenue'),
                    ])
                    ->groupBy('product_id')
                    ->get();

                foreach ($aggregatedItems as $item) {
                    $productId = (int) $item->product_id;
                    $quantitySold = (int) $item->total_quantity_sold;
                    $revenue = (float) $item->total_revenue;

                    $summary['total_items_sold'] += $quantitySold;

                    if (!isset($productSales[$productId])) {
                        $productSales[$productId] = [
                            'sale_date' => $summary['sale_date'],
                            'product_id' => $productId,
                            'total_quantity_sold' => 0,
                            'total_revenue' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    $productSales[$productId]['total_quantity_sold'] += $quantitySold;
                    $productSales[$productId]['total_revenue'] += $revenue;
                    $productSales[$productId]['updated_at'] = now();
                }
            });

        DB::transaction(function () use ($summary, $productSales) {
            DailySalesSummary::updateOrCreate(
                ['sale_date' => $summary['sale_date']],
                [
                    'total_orders' => $summary['total_orders'],
                    'total_items_sold' => $summary['total_items_sold'],
                    'total_revenue' => $summary['total_revenue'],
                ]
            );

            DailyProductSale::where('sale_date', $summary['sale_date'])->delete();

            foreach (array_chunk(array_values($productSales), 500) as $chunk) {
                DailyProductSale::upsert(
                    $chunk,
                    ['sale_date', 'product_id'],
                    ['total_quantity_sold', 'total_revenue', 'updated_at']
                );
            }
        });
    }
}
