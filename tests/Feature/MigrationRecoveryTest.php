<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationRecoveryTest extends TestCase
{
    private const MIGRATION = '2026_09_30_000000_create_platform_tables';

    protected function setUp(): void
    {
        parent::setUp();

        // An isolated database: never modify the application's configured database.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $users = require database_path('migrations/0001_01_01_000000_create_users_table.php');
        $users->up();
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function resume(): void
    {
        $this->artisan('migrate', [
            '--path' => 'database/migrations/'.self::MIGRATION.'.php',
            '--force' => true,
        ])->assertExitCode(0);
    }

    public function test_resumes_after_admin_column_was_already_added(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_admin')->default(false));
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();

        $this->resume();

        $this->assertTrue((bool) $admin->fresh()->is_admin);
        foreach (['articles', 'debates', 'participants', 'votes', 'comments'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertTrue(Schema::hasIndex('votes', ['debate_id', 'user_id'], 'unique'));
        $this->assertDatabaseHas('migrations', ['migration' => self::MIGRATION]);
    }

    public function test_preserves_articles_when_resuming_after_partial_table_creation(): void
    {
        $migration = require database_path('migrations/'.self::MIGRATION.'.php');
        $migration->up();
        $user = User::factory()->create();
        $article = Article::create([
            'user_id' => $user->id, 'title' => 'Bestaand artikel', 'slug' => 'bestaand',
            'category' => 'Opinie', 'summary' => 'Bewaren', 'body' => 'Bestaande tekst',
        ]);
        // Reconstruct an interrupted installation that only finished users and articles.
        foreach (['comments', 'votes', 'participants', 'debates'] as $table) {
            Schema::drop($table);
        }

        $this->resume();

        $this->assertDatabaseHas('articles', ['id' => $article->id, 'body' => 'Bestaande tekst']);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertTrue(Schema::hasTable('comments'));
        $this->assertTrue(Schema::hasTable('debates'));

        $this->resume();
        $this->assertDatabaseCount('articles', 1);
        $this->assertSame(1, DB::table('migrations')->where('migration', self::MIGRATION)->count());
    }

    public function test_complete_schema_without_migration_record_can_be_recorded_safely(): void
    {
        $migration = require database_path('migrations/'.self::MIGRATION.'.php');
        $migration->up();
        $this->resume();
        $this->assertDatabaseHas('migrations', ['migration' => self::MIGRATION]);
        $this->assertTrue(Schema::hasIndex('votes', ['debate_id', 'user_id'], 'unique'));
    }
}
