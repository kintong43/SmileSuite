@extends('layouts.app')
@section('title','Shopping cart')
@section('content')
<div class="page-heading"><div><a class="back-link" href="/store">← Back to clinic store</a><h1>A little everyday care.</h1><p>Product availability and final totals are confirmed by Shopify.</p></div></div><section class="panel form-panel">
@if($storeError)<div class="alert error">{{ $storeError }}</div>@endif
@forelse(data_get($cart,'lines.nodes',[]) as $line)<div class="cart-line"><div><h2>{{ data_get($line,'merchandise.product.title') }}</h2><p>{{ data_get($line,'merchandise.title') }}</p></div><form action="/store/cart/update" method="POST">@csrf<input type="hidden" name="line_id" value="{{ $line['id'] }}"><label>Quantity<input name="quantity" type="number" value="{{ $line['quantity'] }}" min="0" max="20"></label><button class="button secondary">Update</button></form></div>@empty<div class="empty-state"><x-icon name="bag"/><strong>A little space for something good.</strong><p>Your cart is empty, or no store is connected.</p><a class="button secondary" href="/store">Explore clinic store</a></div>@endforelse
@if(data_get($cart,'lines.nodes'))<div class="form-footer"><strong>Estimated total: {{ data_get($cart,'cost.totalAmount.currencyCode') }} {{ data_get($cart,'cost.totalAmount.amount') }}</strong><form action="/store/checkout" method="POST">@csrf<button class="button primary">Continue to Shopify <x-icon name="arrow"/></button></form></div>@endif</section>
@endsection
