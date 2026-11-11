<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pharmacy_pos_transactions')) {
            Schema::table('pharmacy_pos_transactions', function (Blueprint $table) {
                if (! Schema::hasColumn('pharmacy_pos_transactions', 'patient_type')) {
                    $table->string('patient_type', 32)->default('Outpatient')->after('sale_type');
                }
            });
        }
    }

    public function down(): void
    {
        // Additive-only per project constraints.
    }
};

