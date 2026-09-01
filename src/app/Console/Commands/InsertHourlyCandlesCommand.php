<?php

namespace App\Console\Commands;

use App\Models\Candle;
use App\Models\Market;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class InsertHourlyCandlesCommand extends Command
{
    protected $signature = 'app:inset-hourly-candles';

    protected $description = 'Command description';

    public function handle(): void
    {
        $market = Market::updateOrCreate(
            ['symbol' => 'BTCUSDT'],
            ['is_active' => true],
        );

        $to = now()
            ->subHour()
            ->startOfHour();

        $from = now()
            ->subHours(2)
            ->startOfHour();

        $response = Http::withToken(config('services.wallex.token'))
            ->acceptJson()
            ->get(
                'https://api.wallex.ir/v1/udf/history',
                [
                    'symbol' => $market->symbol,
                    'resolution' => '60',
                    'from' => $from->timestamp,
                    'to' => $to->timestamp,
                ]
            )
            ->throw();

        $data = $response->json();

        if (($data['s'] ?? null) !== 'ok') {
            throw new \RuntimeException(
                'Wallex returned an unsuccessful response.'
            );
        }

        $timestamps = $data['t'] ?? [];

        $rows = [];

        foreach ($timestamps as $index => $timestamp) {
            $rows[] = [
                'market_id' => $market->id,
                'timeframe' => '1h',
                'open_time' => Carbon::createFromTimestamp($timestamp,config('app.timezone')),

                'open' => $data['o'][$index],
                'high' => $data['h'][$index],
                'low' => $data['l'][$index],
                'close' => $data['c'][$index],
                'volume' => $data['v'][$index] ?? null,

                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Candle::upsert(
            $rows,
            [
                'market_id',
                'timeframe',
                'open_time',
            ],
            [
                'open',
                'high',
                'low',
                'close',
                'volume',
                'updated_at',
            ],
        );

        $this->info(
            "BTCUSDT: {$market->symbol} market created/updated."
        );

        $this->info(
            count($rows) . ' hourly candles synchronized.'
        );

        $this->info(
            "Range: {$from} → {$to}"
        );
    }
}
