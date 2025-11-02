<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('carbon_analyses', function (Blueprint $table) {
            $table->id();

            // Identificação da resposta na OpenAI
            $table->string('response_id')->unique();

            // Contato
            $table->string('email')->index();
            $table->string('telefone', 32)->nullable()->index();

            // JSON bruto retornado pela API (mantém tudo)
            $table->json('data');

            // ---- Campos desnormalizados úteis (opcionais, para filtro/relatório) ----
            // Área cultivada e nativa (quando disponível no JSON)
            $table->decimal('area_cultivo_hectares', 10, 2)->nullable();
            $table->decimal('area_desmatamento_hectares', 10, 2)->nullable();

            // Faixas de créditos (convertidas do texto "x–y" para min/max quando você fizer o parse)
            $table->decimal('cred_cultivo_min', 12, 2)->nullable();
            $table->decimal('cred_cultivo_max', 12, 2)->nullable();

            $table->decimal('cred_evitado_min', 12, 2)->nullable();
            $table->decimal('cred_evitado_max', 12, 2)->nullable();

            $table->decimal('cred_total_min', 12, 2)->nullable();
            $table->decimal('cred_total_max', 12, 2)->nullable();

            // Preço e valor anual (separado em min/max)
            $table->decimal('preco_credito', 12, 2)->nullable();
            $table->decimal('valor_anual_min', 14, 2)->nullable();
            $table->decimal('valor_anual_max', 14, 2)->nullable();

            // Observações livres
            $table->text('observacoes')->nullable();

            $table->timestamps();

            // Índices úteis
            $table->index(['cred_total_min', 'cred_total_max']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carbon_analyses');
    }
};
