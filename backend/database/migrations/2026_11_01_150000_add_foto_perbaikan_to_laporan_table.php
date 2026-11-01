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
        Schema::table('laporan', function (Blueprint $table) {
            if (! Schema::hasColumn('laporan', 'foto_perbaikan')) {
                $table->string('foto_perbaikan', 255)->nullable();
            }
            if (! Schema::hasColumn('laporan', 'catatan_perbaikan')) {
                $table->text('catatan_perbaikan')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laporan', function (Blueprint $table) {
            $table->dropColumn(['foto_perbaikan', 'catatan_perbaikan']);
        });
    }
};
