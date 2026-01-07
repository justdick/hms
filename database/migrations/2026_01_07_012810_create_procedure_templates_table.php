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
        Schema::create('procedure_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('minor_procedure_type_id')->nullable()->constrained('minor_procedure_types')->nullOnDelete();
            $table->string('procedure_code', 50)->nullable();
            $table->string('name');
            $table->text('template_text');
            $table->json('variables');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('procedure_code');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('procedure_templates');
    }
};
