<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Debate;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demodata is alleen voor lokaal gebruik.');
        }
        $author = User::firstOrCreate(['email' => 'demo@nodara.invalid'], ['name' => 'Nodara demo', 'password' => Str::random(64)]);
        foreach ([
            ['Wie beslist over de toekomst van onze stad?', 'Politiek', 'Over zeggenschap, lokale keuzes en de ruimte voor inwoners.'],
            ['Een samenleving begint bij het gesprek', 'Samenleving', 'Wat gebeurt er als we beter luisteren naar andere perspectieven?'],
            ['Cultuur maakt ruimte voor nieuwe stemmen', 'Cultuur', 'Waarom verhalen van anderen ons blikveld kunnen vergroten.'],
            ['Een goed argument verdient aandacht', 'Opinie', 'Een uitnodiging om overtuigingen te onderzoeken en bespreekbaar te maken.'],
        ] as [$title,$category,$summary]) {
            Article::firstOrCreate(['slug' => Str::slug($title)], ['user_id' => $author->id, 'title' => $title, 'category' => $category, 'summary' => $summary, 'body' => "DEMOARTIKEL — dit is voorbeeldinhoud, geen nieuwsbericht.\n\n".$summary."\n\nNodara biedt ruimte voor verschillende perspectieven. Deel je eigen artikel en begin een gesprek.", 'published_at' => now()]);
        }
        $debate = Debate::firstOrCreate(['slug' => 'demo-wie-bepaalt-de-toekomst-van-de-wijk'], ['user_id' => $author->id, 'title' => 'Wie bepaalt de toekomst van de wijk?', 'category' => 'Samenleving', 'description' => 'DEMODEBAT — fictieve deelnemers. Meer directe inspraak voor bewoners of een grotere rol voor gekozen vertegenwoordigers?', 'starts_at' => now()->subDay(), 'ends_at' => now()->addWeek(), 'published_at' => now()]);
        if (! $debate->participants()->exists()) {
            $debate->participants()->createMany([
                ['name' => 'Sam (fictief)', 'position' => 'Meer directe inspraak', 'argument' => 'Bewoners kennen hun wijk. Zij moeten meer invloed krijgen op keuzes die hun dagelijks leven raken.'],
                ['name' => 'Alex (fictief)', 'position' => 'Vertegenwoordiging met verantwoording', 'argument' => 'Gekozen vertegenwoordigers kunnen belangen van de hele gemeente afwegen. Laat hen besluiten uitleggen en bewoners vooraf raadplegen.'],
            ]);
        }
    }
}
