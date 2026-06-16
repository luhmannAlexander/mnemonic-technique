<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * BackendSchema §2.1: users gets a deleted_at column. NOTE this is NOT the
     * 30-day trash soft-delete — account deletion is immediate and permanent
     * (AppFlow §2.14). The column exists for tooling parity; the User model does
     * not use the SoftDeletes trait.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
