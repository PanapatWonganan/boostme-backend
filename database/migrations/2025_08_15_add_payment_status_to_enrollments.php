<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->enum('payment_status', ['pending', 'completed', 'failed', 'refunded'])
                ->default('pending')
                ->after('status');
            $table->decimal('amount_paid', 10, 2)->default(0)->after('payment_status');
            $table->timestamp('payment_date')->nullable()->after('amount_paid');
            $table->string('payment_method')->nullable()->after('payment_date');
            $table->string('transaction_id')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'amount_paid', 'payment_date', 'payment_method', 'transaction_id']);
        });
    }
};