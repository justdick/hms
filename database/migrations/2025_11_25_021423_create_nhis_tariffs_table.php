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
        Schema::create('nhis_tariffs', function (Blueprint $table) {
            $table->id();
            $table->string('nhis_code', 50)->unique();
            $table->string('name', 255);
            $table->enum('category', ['medicine', 'lab', 'procedure', 'consultation', 'consumable']);
            $table->decimal('price', 10, 2);
            $table->string('unit', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes for search and filtering
            $table->index('category', 'idx_category');
            // Note: nhis_code already has unique index, name uses prefix index for MySQL compatibility
        });

        // Add prefix index for name column (MySQL key length limitation)
        // SQLite doesn't support prefix indexes, so we use a regular index for testing
        if (DB::getDriverName() === 'mysql') {
            DB::statement('CREATE INDEX idx_name ON nhis_tariffs (name(100))');
        } else {
            Schema::table('nhis_tariffs', function (Blueprint $table) {
                $table->index('name', 'idx_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nhis_tariffs');
    }
};
