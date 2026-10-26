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
        Schema::table('kategori_masalah', function (Blueprint $table) {
            if (! Schema::hasColumn('kategori_masalah', 'kode_label')) {
                $table->integer('kode_label')->nullable()->unique();
            }
            if (! Schema::hasColumn('kategori_masalah', 'deskripsi')) {
                $table->string('deskripsi', 255)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kategori_masalah', function (Blueprint $table) {
            $table->dropColumn(['kode_label', 'deskripsi']);
        });
    }
};
