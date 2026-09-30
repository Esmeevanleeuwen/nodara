<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

Artisan::command('nodara:admin {email}', function () {
    $user = User::where('email', $this->argument('email'))->first();
    if (! $user) {
        $this->error('Maak eerst een account aan op Nodara.');

        return 1;
    } $user->is_admin = true;
    $user->save();
    $this->info('Beheerder ingesteld: '.$user->email);

    return 0;
})->purpose('Maak een bestaand account beheerder');
