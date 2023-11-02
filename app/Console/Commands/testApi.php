<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class testApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:api';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $response = Http::withHeaders(['Content-Type' => 'application/json'])->send('POST', 'http://maiharta.ddns.net:8888/api/logs/delete', [
            'body' => json_encode([
                'id_invoice' => 'INV-1695702620',
            ])
         ]);
        dd($response->json());
    }
}
