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
        Schema::table('users', function (Blueprint $table) {
            // Rename institute_id to institution_id
            $table->renameColumn('institute_id', 'institution_id');
        });
        
        Schema::table('users', function (Blueprint $table) {
            // Change the column type to match the institutions table (assuming it's uuid)
            $table->uuid('institution_id')->nullable()->change();

            // Add foreign key constraint
            $table->foreign('institution_id')
                  ->references('id')
                  ->on('institutions')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['institution_id']);

            // Revert column type if needed (change back to string if it was originally)
            $table->string('institution_id')->nullable()->change();

        });
    }
};
