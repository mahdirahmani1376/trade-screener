<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public function __construct(
        public string $direction,
        public string $open_time,
    )
    {
    }

    public function handle(): void
    {
        try {
            Http::post(
                'https://api.telegram.org/bot' . config('services.telegram.bot_token') . '/sendMessage',
                [
                    'chat_id' => config('services.telegram.chat_id'),
                    'text' => "🚨 BTCUSDT {$this->direction} FVG detected on time {$this->open_time}",
                ]
            );
        } catch (\Exception $e) {
            Log::error('telegram message sending error',[
                'error' => $e->getMessage()
            ]);
        }
    }
}
