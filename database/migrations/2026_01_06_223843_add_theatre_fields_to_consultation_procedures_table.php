<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultation_procedures', function (Blueprint $table) {
            // Basic theatre note fields
            $table->text('indication')->nullable()->after('minor_procedure_type_id');
            $table->string('assistant')->nullable()->after('indication');
            $table->string('anaesthetist')->nullable()->after('assistant');
            $table->enum('anaesthesia_type', ['spinal', 'local', 'general', 'regional', 'sedation'])->nullable()->after('anaesthetist');

            // C-Section specific fields
            $table->string('estimated_gestational_age', 50)->nullable()->after('anaesthesia_type');
            $table->string('parity', 50)->nullable()->after('estimated_gestational_age');
            $table->string('procedure_subtype', 100)->nullable()->after('parity');

            // Procedure documentation fields
            $table->text('procedure_steps')->nullable()->after('procedure_subtype');
            $table->json('template_selections')->nullable()->after('procedure_steps');
            $table->text('findings')->nullable()->after('template_selections');
            $table->text('plan')->nullable()->after('findings');
        });
    }

    public function down(): void
    {
        Schema::table('consultation_procedures', function (Blueprint $table) {
            $table->dropColumn([
                'indication',
                'assistant',
                'anaesthetist',
                'anaesthesia_type',
                'estimated_gestational_age',
                'parity',
                'procedure_subtype',
                'procedure_steps',
                'template_selections',
                'findings',
                'plan',
            ]);
        });
    }
};
