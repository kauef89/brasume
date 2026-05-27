<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // 1. Torna o documento não obrigatório
        Schema::table("suppliers", function (Blueprint $table) {
            $table->string("document")->nullable()->change();
        });

        // 2. Cria a nova coluna 'document_name' (Razão Social)
        Schema::table("suppliers", function (Blueprint $table) {
            $table->string("document_name")->nullable()->after("name");
        });

        // 3. Move os dados antigos com segurança (Para não perder nada)
        // A Razão Social antiga (name) vai para a nova coluna document_name
        DB::table("suppliers")->update(["document_name" => DB::raw("name")]);

        // O Nome Fantasia antigo (trade_name) passa a ser o nome principal, se existir
        DB::table("suppliers")
            ->whereNotNull("trade_name")
            ->where("trade_name", "!=", "")
            ->update(["name" => DB::raw("trade_name")]);

        // 4. Apaga a coluna velha
        Schema::table("suppliers", function (Blueprint $table) {
            $table->dropColumn("trade_name");
        });
    }

    public function down(): void
    {
        // ... (pode deixar vazio ou fazer o reverso se quiser)
    }
};
