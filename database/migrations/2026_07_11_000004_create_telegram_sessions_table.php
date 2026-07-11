<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('telegram_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('telegram_chat_id')->unique();
            $table->string('telegram_user_id')->nullable()->index();
            $table->string('flow')->nullable();
            $table->string('step')->nullable();
            $table->json('payload')->nullable();
            $table->string('last_message_id')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_sessions');
    }
};
