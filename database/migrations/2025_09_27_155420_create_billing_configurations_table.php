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
        Schema::create('billing_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // Configuration key (e.g., 'global_payment_enforcement')
            $table->string('category')->default('general'); // general, department, service, ward
            $table->text('value'); // JSON configuration value
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_configurations');
    }
};
