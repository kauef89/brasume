<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('credit_cards', function (Blueprint $table) {
            $table->string('last_four_digits', 4)->nullable()->after('name');
            $table->string('card_type')->default('physical')->after('last_four_digits'); // physical, virtual
            $table->string('holder_name')->nullable()->after('card_type');
        });

        Schema::table('account_payables', function (Blueprint $table) {
            $table->foreignId('credit_card_id')->nullable()->constrained('credit_cards')->nullOnDelete()->after('bank_account_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('credit_card_id')->nullable()->constrained('credit_cards')->nullOnDelete()->after('bank_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['credit_card_id']);
            $table->dropColumn('credit_card_id');
        });

        Schema::table('account_payables', function (Blueprint $table) {
            $table->dropForeign(['credit_card_id']);
            $table->dropColumn('credit_card_id');
        });

        Schema::table('credit_cards', function (Blueprint $table) {
            $table->dropColumn(['last_four_digits', 'card_type', 'holder_name']);
        });
    }
};