@extends('content.layout')
@section('title', $page['title'])
@section('content')
<article class="card" style="padding:32px;margin-top:32px"><h1>{{ $page['title'] }}</h1><div style="white-space:pre-wrap;line-height:1.8">{{ $page['body'] }}</div></article>
@endsection
