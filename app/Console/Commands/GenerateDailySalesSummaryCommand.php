<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDailySalesSummaryJob;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateDailySalesSummaryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sales:daily-summary
                            {date? : The sales date in Y-m-d format}
                            {--yesterday : Generate summary for the previous day}
                            {--sync : Run immediately instead of dispatching to the queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate daily sales summaries using background batch processing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dateInput = $this->option('yesterday')
            ? now()->subDay()->toDateString()
            : ($this->argument('date') ?: now()->toDateString());

        try {
            $saleDate = Carbon::createFromFormat('Y-m-d', $dateInput)->toDateString();
        } catch (\Throwable $exception) {
            $this->error('Invalid date format. Use Y-m-d.');

            return self::FAILURE;
        }

        $job = new GenerateDailySalesSummaryJob($saleDate);

        if ($this->option('sync')) {
            $job->handle();
            $this->info("Daily sales summary generated immediately for {$saleDate}.");

            return self::SUCCESS;
        }

        dispatch($job);
        $this->info("Daily sales summary job dispatched for {$saleDate} on the reports queue.");

        return self::SUCCESS;
    }
}
