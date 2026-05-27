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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable(); 

            $table->string('name');
            $table->string('kitchen_name')->nullable();
            $table->text('description')->nullable();
            $table->string('type')->default('resale');
            $table->string('sku')->nullable()->unique();
            $table->string('barcode')->nullable()->unique();

            $table->decimal('base_cost', 10, 2)->default(0);
            $table->decimal('dine_in_price', 10, 2)->default(0);
            $table->decimal('delivery_price', 10, 2)->nullable();
            $table->decimal('promo_price', 10, 2)->nullable();

            $table->string('unit_of_measure', 10)->default('un'); 
            $table->decimal('conversion_factor', 10, 4)->default(1);
            $table->boolean('track_inventory')->default(true);
            $table->decimal('current_stock', 10, 3)->default(0);
            $table->decimal('min_stock', 10, 3)->default(0); // NOVO
            $table->boolean('is_grab_and_go')->default(false);

            $table->string('status')->default('available');
            $table->boolean('show_on_menu')->default(true);
            $table->boolean('show_on_delivery')->default(true);
            $table->boolean('is_age_restricted')->default(false);
            $table->integer('prep_time_minutes')->nullable();
            $table->string('image_path')->nullable();
            $table->integer('serves_people')->nullable(); 

            // === NOVOS CAMPOS DO SEU CSV ===
            $table->unsignedBigInteger('print_station_id')->nullable(); // KDS / Impressora
            $table->boolean('requires_preparation')->default(false); // Exige Preparo
            $table->boolean('loyalty_reward')->default(false); // Recompensa Fidelidade
            
            // Dados Fiscais (NFC-e)
            $table->string('fiscal_ncm', 10)->nullable();
            $table->string('fiscal_cest', 10)->nullable();
            $table->string('fiscal_cfop', 5)->nullable();
            $table->string('fiscal_origin', 2)->nullable();
            $table->string('fiscal_csosn', 4)->nullable();

            // Arrays em JSON para guardar os IDs do seu sistema antigo até migrarmos
            $table->json('legacy_modifier_groups')->nullable();
            $table->json('legacy_recipe')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
