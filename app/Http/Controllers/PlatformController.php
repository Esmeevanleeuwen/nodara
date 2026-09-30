<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Debate;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlatformController extends Controller
{
    public function index(Request $r)
    {
        $q = $r->validate(['q' => 'nullable|string|max:100', 'category' => 'nullable|string|max:80']);
        $articles = Article::whereNotNull('published_at')->where('published_at', '<=', now())->with('author')->withCount('comments');
        if (! empty($q['q'])) {
            $articles->where(fn ($b) => $b->where('title', 'like', '%'.$q['q'].'%')->orWhere('summary', 'like', '%'.$q['q'].'%'));
        }
        if (! empty($q['category'])) {
            $articles->where('category', $q['category']);
        }

        return view('home', ['articles' => $articles->latest('published_at')->paginate(9)->withQueryString(), 'debates' => Debate::whereNotNull('published_at')->where('published_at', '<=', now())->with('participants')->withCount('votes')->latest()->limit(4)->get()]);
    }

    public function article(Article $article)
    {
        $this->visible($article);
        $article->load('author');

        return view('article', ['article' => $article, 'comments' => $article->comments()->with('author')->latest()->paginate(20)]);
    }

    private function visible($record): void
    {
        abort_unless($record->published_at && $record->published_at->lte(now()), 404);
    }

    public function debate(Debate $debate)
    {
        $this->visible($debate);
        $debate->load(['participants' => fn ($q) => $q->withCount('votes')]);

        return view('debate', ['debate' => $debate, 'myVote' => auth()->check() ? $debate->votes()->where('user_id', auth()->id())->first() : null]);
    }

    public function debates()
    {
        return view('debates', ['debates' => Debate::whereNotNull('published_at')->where('published_at', '<=', now())->with('participants')->withCount('votes')->latest()->paginate(12)]);
    }

    public function storeArticle(Request $r)
    {
        $data = $r->validate(['title' => 'required|string|max:200', 'category' => 'required|string|max:80', 'summary' => 'required|string|max:500', 'body' => 'required|string|max:100000']);
        $data['user_id'] = $r->user()->id;
        $data['slug'] = Str::slug($data['title']).'-'.Str::lower(Str::random(8));
        $data['published_at'] = $r->user()->is_admin ? now() : null;
        Article::create($data);

        return redirect('/dashboard')->with('status', 'Artikel opgeslagen. De redactie beoordeelt ingezonden artikelen.');
    }

    public function storeDebate(Request $r)
    {
        abort_unless($r->user()->is_admin, 403);
        $data = $r->validate(['title' => 'required|string|max:200', 'category' => 'required|string|max:80', 'description' => 'required|string|max:10000', 'starts_at' => 'required|date', 'ends_at' => 'required|date|after:starts_at', 'participants' => 'required|array|min:2|max:6', 'participants.*.name' => 'required|string|max:100', 'participants.*.position' => 'required|string|max:200', 'participants.*.argument' => 'required|string|max:20000']);
        DB::transaction(function () use ($data, $r) {
            $participants = $data['participants'];
            unset($data['participants']);
            $data['user_id'] = $r->user()->id;
            $data['slug'] = Str::slug($data['title']).'-'.Str::lower(Str::random(8));
            $data['published_at'] = now();
            $debate = Debate::create($data);
            $debate->participants()->createMany($participants);
        });

        return redirect('/debatten')->with('status', 'Debat gepubliceerd.');
    }

    public function comment(Request $r, Article $article)
    {
        $this->visible($article);
        $data = $r->validate(['body' => 'required|string|max:3000']);
        $article->comments()->create($data + ['user_id' => $r->user()->id]);

        return back()->with('status', 'Reactie geplaatst.');
    }

    public function vote(Request $r, Debate $debate)
    {
        $this->visible($debate);
        $r->validate(['participant_id' => ['required', 'integer', Rule::exists('participants', 'id')->where('debate_id', $debate->id)]]);
        DB::transaction(function () use ($r, $debate) {
            $locked = Debate::lockForUpdate()->findOrFail($debate->id);
            abort_unless(now()->between($locked->starts_at, $locked->ends_at), 422, 'Dit debat is niet open voor stemmen.');
            Vote::upsert([['debate_id' => $locked->id, 'user_id' => $r->user()->id, 'participant_id' => (int) $r->participant_id, 'created_at' => now(), 'updated_at' => now()]], ['debate_id', 'user_id'], ['participant_id', 'updated_at']);
        });

        return back()->with('status', 'Je stem is opgeslagen. Je kunt deze wijzigen zolang het debat open is.');
    }

    public function dashboard(Request $r)
    {
        return view('dashboard', ['articles' => Article::with('author')->when(! $r->user()->is_admin, fn ($q) => $q->where('user_id', $r->user()->id))->latest()->paginate(20)]);
    }

    public function publish(Request $r, Article $article)
    {
        abort_unless($r->user()->is_admin, 403);
        $article->update(['published_at' => $article->published_at ? null : now()]);

        return back()->with('status', 'Publicatiestatus aangepast.');
    }

    public function deleteComment(Request $r, Comment $comment)
    {
        abort_unless($r->user()->is_admin || $r->user()->id === $comment->user_id, 403);
        $comment->delete();

        return back()->with('status', 'Reactie verwijderd.');
    }

    public function preview(Request $r, Article $article)
    {
        abort_unless($r->user()->is_admin || $r->user()->id === $article->user_id, 403);
        $article->load('author');

        return view('preview', compact('article'));
    }

    public function edit(Request $r, Article $article)
    {
        abort_unless($r->user()->is_admin || $r->user()->id === $article->user_id, 403);

        return view('write', compact('article'));
    }

    public function update(Request $r, Article $article)
    {
        abort_unless($r->user()->is_admin || $r->user()->id === $article->user_id, 403);
        $data = $r->validate(['title' => 'required|string|max:200', 'category' => 'required|string|max:80', 'summary' => 'required|string|max:500', 'body' => 'required|string|max:100000']);
        if (! $r->user()->is_admin) {
            $data['published_at'] = null;
        } $article->update($data);

        return redirect('/dashboard')->with('status', 'Wijzigingen opgeslagen. Inzendingen worden opnieuw beoordeeld.');
    }

    public function deleteArticle(Request $r, Article $article)
    {
        abort_unless($r->user()->is_admin || $r->user()->id === $article->user_id, 403);
        $article->delete();

        return redirect('/dashboard')->with('status', 'Artikel verwijderd.');
    }

    public function sitemap()
    {
        return response()->view('sitemap', ['articles' => Article::whereNotNull('published_at')->where('published_at', '<=', now())->get(['slug', 'updated_at']), 'debates' => Debate::whereNotNull('published_at')->where('published_at','<=',now())->get(['slug', 'updated_at'])])->header('Content-Type','application/xml');
    }
}
