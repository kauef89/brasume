<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pos_audits')) {
            Schema::create('pos_audits', function (Blueprint $table) {
                $table->id();
                $table->string('action', 100);
                $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('authorizer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->json('context')->nullable();
                $table->timestamps();

                $table->index(['action', 'created_at']);
                $table->index('operator_id');
                $table->index('authorizer_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_audits');
    }
};
