<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->boolean('is_admin')->default(false));
        Schema::create('articles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('category', 80);
            $t->text('summary');
            $t->longText('body');
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamps();
        });
        Schema::create('debates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('category', 80);
            $t->text('description');
            $t->timestamp('starts_at');
            $t->timestamp('ends_at')->index();
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamps();
        });
        Schema::create('participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('debate_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('position');
            $t->text('argument');
            $t->timestamps();
        });
        Schema::create('votes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('debate_id')->constrained()->cascadeOnDelete();
            $t->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unique(['debate_id', 'user_id']);
            $t->timestamps();
        });
        Schema::create('comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('article_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['comments', 'votes', 'participants', 'debates', 'articles'] as $name) {
            Schema::dropIfExists($name);
        } Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
    }
};
