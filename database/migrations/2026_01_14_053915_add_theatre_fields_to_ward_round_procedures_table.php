<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds enhanced theatre documentation fields to ward_round_procedures
     * to match consultation_procedures functionality.
     */
    public function up(): void
    {
        Schema::table('ward_round_procedures', function (Blueprint $table) {
            $table->text('indication')->nullable()->after('minor_procedure_type_id');
            $table->string('assistant', 255)->nullable()->after('indication');
            $table->string('anaesthetist', 255)->nullable()->after('assistant');
            $table->string('anaesthesia_type', 50)->nullable()->after('anaesthetist');
            $table->string('estimated_gestational_age', 50)->nullable()->after('anaesthesia_type');
            $table->string('parity', 50)->nullable()->after('estimated_gestational_age');
            $table->string('procedure_subtype', 100)->nullable()->after('parity');
            $table->text('procedure_steps')->nullable()->after('procedure_subtype');
            $table->json('template_selections')->nullable()->after('procedure_steps');
            $table->text('findings')->nullable()->after('template_selections');
            $table->text('plan')->nullable()->after('findings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ward_round_procedures', function (Blueprint $table) {
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
