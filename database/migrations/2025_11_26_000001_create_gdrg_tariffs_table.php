<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gdrg_tariffs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 255);
            $table->string('mdc_category', 100);
            $table->decimal('tariff_price', 10, 2);
            $table->enum('age_category', ['adult', 'child', 'all'])->default('all');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for search and filtering
            $table->index('mdc_category', 'idx_gdrg_mdc_category');
        });

        // Add prefix index for name column (MySQL key length limitation)
        // SQLite doesn't support prefix indexes, so we use a regular index for testing
        if (DB::getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX idx_gdrg_name ON gdrg_tariffs (name(100))');
        } else {
            Schema::table('gdrg_tariffs', function (Blueprint $table) {
                $table->index('name', 'idx_gdrg_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gdrg_tariffs');
    }
};
