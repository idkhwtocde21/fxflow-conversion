<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('user')->after('password');
        });

        Schema::create('conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('amount', 20, 4);
            $table->decimal('converted_amount', 20, 4);
            $table->decimal('rate_used', 20, 10);
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('saved_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'from_currency', 'to_currency']);
        });

        Schema::create('rate_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('from_currency', 3);
            $table->string('to_currency', 3);
            $table->decimal('target_rate', 20, 10);
            $table->string('condition');
            $table->string('status')->default('active');
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_alerts');
        Schema::dropIfExists('saved_pairs');
        Schema::dropIfExists('conversions');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
