<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Debate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    private function article(bool $published = true): Article
    {
        return Article::create(['user_id' => User::factory()->create()->id, 'title' => 'Wonen en politiek', 'slug' => 'wonen', 'category' => 'Politiek', 'summary' => 'Een samenvatting', 'body' => 'Een betoog <script>alert(1)</script>', 'published_at' => $published ? now() : null]);
    }

    private function debate(): Debate
    {
        $d = Debate::create(['user_id' => User::factory()->create()->id, 'title' => 'Wie overtuigt?', 'slug' => 'test-debat', 'category' => 'Samenleving', 'description' => 'Twee standpunten', 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'published_at' => now()]);
        $d->participants()->createMany([['name' => 'Sam', 'position' => 'Voor', 'argument' => 'Betoog A'], ['name' => 'Alex', 'position' => 'Tegen', 'argument' => 'Betoog B']]);

        return $d;
    }

    public function test_public_pages_render_and_content_is_escaped(): void
    {
        $a = $this->article();
        $d = $this->debate();
        $this->get('/')->assertOk()->assertSee($a->title);
        $this->get('/artikelen/wonen')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('NewsArticle');
        $this->get('/debatten')->assertOk()->assertSee($d->title);
        $this->get('/debatten/test-debat')->assertOk()->assertSee('Sam');
        $this->get('/login')->assertOk();
        $this->get('/registreren')->assertOk();
    }

    public function test_drafts_and_future_publications_are_private_and_excluded_from_sitemap(): void
    {
        $a = $this->article(false);
        $this->get('/artikelen/wonen')->assertNotFound();
        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/artikelen/wonen');
        $a->update(['published_at' => now()->addDay()]);
        $this->get('/artikelen/wonen')->assertNotFound();
        $this->get('/')->assertDontSee($a->title);
        $this->get('/sitemap.xml')->assertDontSee('/artikelen/wonen');
    }

    public function test_registration_does_not_allow_admin_privileges(): void
    {
        $this->post('/registreren', ['name' => 'Lezer', 'email' => 'lezer@example.com', 'password' => 'password12345', 'password_confirmation' => 'password12345', 'is_admin' => true])->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertFalse((bool) User::first()->is_admin);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->post('/login', ['email' => 'lezer@example.com', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'lezer@example.com', 'password' => 'password12345'])->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_article_requires_auth_and_non_admin_submission_is_a_draft(): void
    {
        $payload = ['title' => 'Nieuw artikel', 'category' => 'Opinie', 'summary' => 'Kort', 'body' => 'Tekst', 'published_at' => now()];
        $this->post('/artikelen', $payload)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->post('/artikelen', $payload)->assertRedirect('/dashboard');
        $this->assertDatabaseHas('articles', ['title' => 'Nieuw artikel', 'published_at' => null]);
        $this->get('/dashboard')->assertOk()->assertSee('Nieuw artikel');
        $this->get('/schrijven')->assertOk();
    }

    public function test_only_admin_can_publish_and_author_can_edit_with_re_review(): void
    {
        $a = $this->article(false);
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post('/artikelen/'.$a->id.'/publiceren')->assertForbidden();
        $this->get('/dashboard/artikelen/'.$a->id.'/voorbeeld')->assertForbidden();
        $this->get('/dashboard/artikelen/'.$a->id.'/bewerken')->assertForbidden();
        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->get('/dashboard/artikelen/'.$a->id.'/voorbeeld')->assertOk();
        $this->post('/artikelen/'.$a->id.'/publiceren')->assertRedirect();
        $this->get('/artikelen/wonen')->assertOk();
        $this->actingAs($a->author)->get('/dashboard/artikelen/'.$a->id.'/bewerken')->assertOk();
        $this->put('/dashboard/artikelen/'.$a->id, ['title' => 'Gewijzigd', 'category' => 'Politiek', 'summary' => 'Kort', 'body' => 'Nieuwe tekst'])->assertRedirect('/dashboard');
        $this->assertNull($a->fresh()->published_at);
        $this->assertSame('wonen', $a->fresh()->slug);
    }

    public function test_comments_require_public_article_and_moderation_is_authorized(): void
    {
        $a = $this->article();
        $u = User::factory()->create();
        $this->post('/artikelen/wonen/reacties', ['body' => 'Hallo'])->assertRedirect('/login');
        $this->actingAs($u)->post('/artikelen/wonen/reacties', ['body' => 'Hallo'])->assertRedirect();
        $c = Comment::first();
        $this->assertSame($u->id, $c->user_id);
        $this->actingAs(User::factory()->create())->delete('/reacties/'.$c->id)->assertForbidden();
        $this->actingAs($u)->delete('/reacties/'.$c->id)->assertRedirect();
        $this->assertDatabaseCount('comments', 0);
        $a->update(['published_at' => null]);
        $this->post('/artikelen/wonen/reacties', ['body' => 'Hallo'])->assertNotFound();
    }

    public function test_one_vote_per_account_can_change_and_other_debate_participant_is_rejected(): void
    {
        $d = $this->debate();
        $ids = $d->participants->pluck('id');
        $u = User::factory()->create();
        $this->post('/debatten/test-debat/stem', ['participant_id' => $ids[0]])->assertRedirect('/login');
        $this->actingAs($u)->post('/debatten/test-debat/stem', ['participant_id' => $ids[0]])->assertRedirect();
        $this->post('/debatten/test-debat/stem', ['participant_id' => $ids[1]])->assertRedirect();
        $this->assertDatabaseCount('votes', 1);
        $this->assertDatabaseHas('votes', ['user_id' => $u->id, 'participant_id' => $ids[1]]);
        $foreign = $d->replicate();
        $foreign->slug = 'other';
        $foreign->save();
        $participant = $foreign->participants()->create(['name' => 'Other', 'position' => 'No', 'argument' => 'No']);
        $this->post('/debatten/test-debat/stem', ['participant_id' => $participant->id])->assertSessionHasErrors('participant_id');
        $this->assertDatabaseCount('votes', 1);
    }

    public function test_voting_outside_window_or_unpublished_is_rejected(): void
    {
        $d = $this->debate();
        $id = $d->participants->first()->id;
        $this->actingAs(User::factory()->create());
        $d->update(['ends_at' => now()->subMinute()]);
        $this->post('/debatten/test-debat/stem', ['participant_id' => $id])->assertStatus(422);
        $d->update(['starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
        $this->post('/debatten/test-debat/stem', ['participant_id' => $id])->assertStatus(422);
        $d->update(['published_at' => null]);
        $this->post('/debatten/test-debat/stem', ['participant_id' => $id])->assertNotFound();
        $this->assertDatabaseCount('votes', 0);
    }

    public function test_only_admin_can_create_debates_and_dates_are_validated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/debat-maken')->assertForbidden();
        $this->post('/debatten', [])->assertForbidden();
        $user->is_admin = true;
        $user->save();
        $this->get('/debat-maken')->assertOk();
        $data = ['title' => 'Nieuw debat', 'category' => 'Politiek', 'description' => 'Een vraag', 'starts_at' => now()->toDateTimeString(), 'ends_at' => now()->subHour()->toDateTimeString(), 'participants' => [['name' => 'A', 'position' => 'Voor', 'argument' => 'Ja'], ['name' => 'B', 'position' => 'Tegen', 'argument' => 'Nee']]];
        $this->post('/debatten', $data)->assertSessionHasErrors('ends_at');
        $data['ends_at'] = now()->addDay()->toDateTimeString();
        $this->post('/debatten', $data)->assertRedirect('/debatten');
        $this->assertDatabaseCount('debates', 1);
        $this->assertDatabaseCount('participants', 2);
    }

    public function test_search_category_and_sitemap_work(): void
    {
        $a = $this->article();
        $d = $this->debate();
        $this->get('/?q=Wonen&category=Politiek')->assertOk()->assertSee($a->title);
        $this->get('/?q=absent')->assertOk()->assertDontSee($a->title);
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee('/artikelen/wonen')->assertSee('/debatten/test-debat');
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap:');
    }
}
