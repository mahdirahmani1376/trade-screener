<?php

namespace App\Console\Commands;

use App\Jobs\SendTelegramMessageJob;
use App\Models\Candle;
use App\Models\Market;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Morilog\Jalali\Jalalian;

class FVGDetectorCronCommand extends Command
{
    protected $signature = 'app:detect-fvg-cron';

    protected $description = 'Command description';

    public function handle(): void
    {
        $market = Market::firstWhere('symbol', 'BTCUSDT');

        $candles = Candle::query()
            ->where('market_id', $market->id)
            ->where('timeframe', '1h')
            ->latest('open_time')
            ->limit(3)
            ->get()
            ->reverse()
            ->values();

        if ($candles->count() !== 3) {
            $this->warn('Not enough candles.');
            return;
        }

        [$candle1, $candle2, $candle3] = $candles;

        if ($this->isBullishFVG($candle1, $candle2, $candle3)) {
            $this->alertIfNew(
                $market,
                $candle1,
                $candle2,
                $candle3,
                'bullish'
            );
        }

        if ($this->isBearishFVG($candle1, $candle2, $candle3)) {
            $this->alertIfNew(
                $market,
                $candle1,
                $candle2,
                $candle3,
                'bearish'
            );
        }
    }

    private function isBullishFVG(
        Candle $candle1,
        Candle $candle2,
        Candle $candle3
    ): bool {
        if ($candle3->low <= $candle1->high) {
            return false;
        }

        $candle1Size = $candle1->high - $candle1->low;
        $candle2Size = $candle2->high - $candle2->low;
        $candle3Size = $candle3->high - $candle3->low;

        $candle2BodySize = abs(
            $candle2->open - $candle2->close
        );

        return
            $candle2BodySize > $candle2Size * 0.7
            && $candle2Size > $candle1Size
            && $candle2Size > $candle3Size
            && $candle2->high > $candle1->high;
    }

    private function isBearishFVG(
        Candle $candle1,
        Candle $candle2,
        Candle $candle3
    ): bool {
        if ($candle1->low <= $candle3->high) {
            return false;
        }

        $candle1Size = abs($candle1->high - $candle1->low);
        $candle2Size = abs($candle2->high - $candle2->low);
        $candle3Size = abs($candle3->high - $candle3->low);

        $candle2BodySize = abs(
            $candle2->open - $candle2->close
        );

        return
            $candle2BodySize > $candle2Size * 0.7
            && $candle2Size > $candle1Size
            && $candle2Size > $candle3Size
            && $candle2->low < $candle1->low;
    }

    private function alertIfNew(
        Market $market,
        Candle $candle1,
        Candle $candle2,
        Candle $candle3,
        string $direction
    ): void {
        $key = sprintf(
            'fvg-alert:%s:%s:%s',
            $market->symbol,
            $candle2->open_time->timestamp,
            $direction
        );

        if (! Cache::add($key, true)) {
            SendTelegramMessageJob::dispatch($direction,$candle2->open_time->toDateTimeString());
        }

    }

}
