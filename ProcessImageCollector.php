<?php

namespace App\Jobs;

use App\Item;
use App\Repositories\Xml\PriceGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ProcessImageCollector implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $items = Item::onlyTrashed()->where('created_at', '<', DB::raw('DATE_SUB(NOW(), INTERVAL 2 DAY)'));

        foreach ($items->get() as $item) {
            $item->deleteImages();
        }

        $items->forceDelete();
    }


}
