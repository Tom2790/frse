<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wlasna tabela, a nie tabela notyfikacji Laravela (UUID + kolumna data w JSON),
 * bo zadanie wymaga jawnych kolumn type, title, body, read_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();

            // Dzwonek pyta o 20 najnowszych i o licznik nieprzeczytanych. Oba
            // zapytania ida po user_id, stad dwa indeksy zlozone.
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
