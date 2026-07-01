<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('token', 64)->nullable()->unique(); // hashed bearer token
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_admins');
    }
};
