<?php

namespace App\Console\Commands;

use App\Models\Candle;
use App\Models\Market;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

class FVGDetectorBacktestCommand extends Command
{
    protected $signature = 'app:detect-fvg';

    protected $description = 'Command description';

    public function handle(): void
    {
        $market = Market::firstWhere('symbol', 'BTCUSDT');

        $candles = DB::table('candles')
            ->selectRaw('
                    open_time,
                    open as candle_3_open,
                    high as candle_3_high,
                    low as candle_3_low,
                    close as candle_3_close,
                    LAG(open, 1) OVER (
                        PARTITION BY market_id, timeframe
                        ORDER BY open_time
                        ) AS candle_2_open,
                    LAG(high, 1) OVER (
                        PARTITION BY market_id, timeframe
                        ORDER BY open_time
                        ) AS candle_2_high,
                    LAG(low, 1) OVER (
                        PARTITION BY market_id, timeframe
                        ORDER BY open_time
                        ) AS candle_2_low,
                    LAG(close, 1) OVER (
                        PARTITION BY market_id, timeframe
                        ORDER BY open_time
                        ) AS candle_2_close,
                    LAG(open_time, 1) OVER (
                    PARTITION BY market_id, timeframe
                    ORDER BY open_time
                    ) AS candle_2_open_time,

                    LAG(open, 2) OVER (
                        PARTITION BY market_id, timeframe
                        ORDER BY open_time
                        ) AS candle_1_open,
                    LAG(high, 2) OVER (
                        PARTITION BY market_id, timeframe
                        ORDER BY open_time
                        ) AS candle_1_high,
                    LAG(low, 2) OVER (
                        PARTITION BY market_id, timeframe
                        ORDER BY open_time
                        ) AS candle_1_low,
                    LAG(close, 2) OVER (
                        PARTITION BY market_id, timeframe
                        ORDER BY open_time
                        ) AS candle_1_close
            ')
            ->where('market_id', $market->id)
            ->where('timeframe', '1h')
            ->orderBy('open_time')
            ->skip(2)
            ->get();

        foreach ($candles as $candle) {
//            if (! $candle->candle_2_open_time === '2026-08-30 15:30:00') {
//                continue;
//            }

            $time = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                $candle->candle_2_open_time,
                'Asia/Tehran'
            );
            $jalali = Jalalian::fromCarbon($time);

            $jalali->format('Y/m/d H:i:s');

            // bearish
            if ($this->isBearishFVG($candle)) {
                $this->info('bearish fvg detected');
                $this->info($jalali->toDateTimeString());
                $this->info(json_encode($candle));
                $this->info(PHP_EOL);
            }
            // bullish
            if ($this->isBullishFVG($candle)) {
                $this->info('bullish fvg detected');
                $this->info($jalali->toDateTimeString());
                $this->info(json_encode($candle));
                $this->info(PHP_EOL);


            }
        }
    }

    private function isBearishFVG($candle): bool
    {
        if (empty($candle->candle_1_low)) {
            return false;
        }

        if (empty($candle->candle_3_low)) {
            return false;
        }

        if (!($candle->candle_1_low > $candle->candle_3_high)) {
            return false;
        }

        $absGapSize = abs($candle->candle_1_low - $candle->candle_3_high);
        $candle1Size = abs($candle->candle_1_high - $candle->candle_1_low);
        $candle2Size = abs($candle->candle_2_high - $candle->candle_2_low);
        $candle3Size = abs($candle->candle_3_high - $candle->candle_3_low);
        $candle2BodySize = abs($candle->candle_2_open - $candle->candle_2_close);

        if (!($candle2BodySize > $candle2Size * 0.5)) {
            return false;
        }

        if (!($candle2Size > ($candle1Size * 1.2))) {
            return false;
        }

        if (!($candle2Size > ($candle3Size * 1.2))) {
            return false;
        }

        if (!($candle->candle_2_low < $candle->candle_1_low)) {
            return false;
        }


        return true;

    }

    private function isBullishFVG($candle): bool
    {
        if (empty($candle->candle_1_low)) {
            return false;
        }

        if (empty($candle->candle_3_low)) {
            return false;
        }

        if (!($candle->candle_3_low > $candle->candle_1_high)) {
            return false;
        }

        $absGapSize = abs($candle->candle_1_high - $candle->candle_3_low);
        $candle1Size = abs($candle->candle_1_high - $candle->candle_1_low);
        $candle2Size = abs($candle->candle_2_high - $candle->candle_2_low);
        $candle3Size = abs($candle->candle_3_high - $candle->candle_3_low);
        $candle2BodySize = abs($candle->candle_2_open - $candle->candle_2_close);

        if (!($candle2BodySize > $candle2Size * 0.7)) {
            return false;
        }

        if (!($candle2Size > $candle1Size)) {
            return false;
        }

        if (!($candle2Size > $candle3Size)) {
            return false;
        }

        if (!($candle->candle_2_high > $candle->candle_1_high)) {
            return false;
        }

        return true;

    }

}
