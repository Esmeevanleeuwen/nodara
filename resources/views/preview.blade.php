@extends('layout')
@push('head')<meta name="robots" content="noindex">@endpush
@section('content')<div class="reading"><a href="/dashboard">← Mijn Nodara</a><div class="notice">Privévoorbeeld voor auteur en redactie</div><h1>{{ $article->title }}</h1><p class="lead">{{ $article->summary }}</p><p>Door {{ $article->author->name }}</p><div class="prose">{{ $article->body }}</div></div>@endsection
