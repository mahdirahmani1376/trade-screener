<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('candles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('market_id')
                ->constrained('markets')
                ->cascadeOnDelete();

            $table->string('timeframe', 10);

            $table->timestamp('open_time');

            $table->decimal('open', 30, 12);
            $table->decimal('high', 30, 12);
            $table->decimal('low', 30, 12);
            $table->decimal('close', 30, 12);
            $table->decimal('volume', 40, 18)->nullable();

            $table->unique(
                ['market_id', 'timeframe', 'open_time'],
                'candles_market_timeframe_open_time_unique'
            );

            $table->index(
                ['market_id', 'timeframe', 'open_time'],
                'candles_lookup_index'
            );

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('candles');
    }
};
