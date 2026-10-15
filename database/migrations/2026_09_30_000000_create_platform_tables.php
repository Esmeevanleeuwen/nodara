<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL can keep completed DDL when a later statement fails.
        // Resume an interrupted migration without recreating existing objects.
        if (! Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('is_admin')->default(false));
        }

        if (! Schema::hasTable('articles')) {
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
        }
        if (! Schema::hasTable('debates')) {
            Schema::create('debates', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->string('title');
                $t->string('slug')->unique();
                $t->string('category', 80);
                $t->text('description');
                // Avoid implicit TIMESTAMP defaults on older MariaDB/XAMPP.
                $t->dateTime('starts_at');
                $t->dateTime('ends_at')->index();
                $t->timestamp('published_at')->nullable()->index();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('participants')) {
            Schema::create('participants', function (Blueprint $t) {
                $t->id();
                $t->foreignId('debate_id')->constrained()->cascadeOnDelete();
                $t->string('name');
                $t->string('position');
                $t->text('argument');
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('votes')) {
            Schema::create('votes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('debate_id')->constrained()->cascadeOnDelete();
                $t->foreignId('participant_id')->constrained()->cascadeOnDelete();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->unique(['debate_id', 'user_id']);
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('comments')) {
            Schema::create('comments', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->foreignId('article_id')->constrained()->cascadeOnDelete();
                $t->text('body');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['comments', 'votes', 'participants', 'debates', 'articles'] as $name) {
            Schema::dropIfExists($name);
        }

        if (Schema::hasColumn('users', 'is_admin')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('is_admin'));
        }
    }
};
