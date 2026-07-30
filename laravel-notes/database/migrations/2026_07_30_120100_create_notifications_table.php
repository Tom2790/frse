<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Własna tabela powiadomień (Zadanie 5a) — świadomie NIE korzystamy z tabeli
 * notyfikacji Laravela (UUID + kolumna `data` w JSON), bo specyfikacja wymaga
 * jawnych kolumn `type`, `title`, `body`, `read_at`.
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

            // Dzwonek pyta o „20 najnowszych" i o licznik nieprzeczytanych — oba
            // zapytania idą po user_id, dlatego indeks złożony z datą i read_at.
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
