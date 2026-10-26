<?php

namespace App\Http\Controllers;

use App\Commerce\CartService;
use App\Commerce\CatalogService;
use App\Commerce\CommerceUnavailable;
use App\Commerce\ShopifyConnectionService;
use App\Models\ShopifyConnection;
use Illuminate\Http\Request;

class CommerceController extends Controller
{
    public function index(Request $request, CatalogService $catalog)
    {
        $products = ['nodes' => [], 'pageInfo' => []];
        $storeError = null;
        try {
            $products = $catalog->products($request->ip());
        } catch (CommerceUnavailable $exception) {
            $storeError = $exception->getMessage();
        }
        $connection = ShopifyConnection::first();

        return view('store', compact('products', 'storeError', 'connection'));
    }

    public function connect(Request $request, ShopifyConnectionService $connections)
    {
        abort_unless($request->user()->hasRole('owner'), 403);
        $data = $request->validate(['shop_domain' => 'required|string|max:255', 'token' => 'required|string|max:1000']);
        try {
            $connections->connect($data['shop_domain'], $data['token'], $request->ip());
        } catch (CommerceUnavailable $exception) {
            return back()->withErrors(['store' => $exception->getMessage()]);
        }

        return back()->with('success', 'Clinic store connected. Credentials remain private.');
    }

    public function disconnect(Request $request, ShopifyConnectionService $connections)
    {
        abort_unless($request->user()->hasRole('owner'), 403);
        $connections->disconnect();

        return back()->with('success', 'Store disconnected. Previous carts have been invalidated.');
    }

    public function add(Request $request, CartService $carts)
    {
        $data = $request->validate(['variant_id' => 'required|string|max:255', 'quantity' => 'required|integer|min:1|max:20']);
        try {
            $carts->add($request->session()->getId(), $data['variant_id'], $data['quantity'], $request->ip());
        } catch (CommerceUnavailable $exception) {
            return back()->withErrors(['store' => $exception->getMessage()]);
        }

        return redirect('/store/cart');
    }

    public function cart(Request $request, CartService $carts)
    {
        $cart = null;
        $storeError = null;
        try {
            $cart = $carts->get($request->session()->getId(), $request->ip());
        } catch (CommerceUnavailable $exception) {
            $storeError = $exception->getMessage();
        }

        return view('cart', compact('cart', 'storeError'));
    }

    public function update(Request $request, CartService $carts)
    {
        $data = $request->validate(['line_id' => 'required|string|max:1000', 'quantity' => 'required|integer|min:0|max:20']);
        try {
            if ($data['quantity'] === 0) {
                $carts->remove($request->session()->getId(), $data['line_id'], $request->ip());
            } else {
                $carts->update($request->session()->getId(), $data['line_id'], $data['quantity'], $request->ip());
            }
        } catch (CommerceUnavailable $exception) {
            return back()->withErrors(['store' => $exception->getMessage()]);
        }

        return back()->with('success', 'Cart updated.');
    }

    public function checkout(Request $request, CartService $carts)
    {
        try {
            return redirect()->away($carts->checkoutUrl($request->session()->getId(), $request->ip()));
        } catch (CommerceUnavailable $exception) {
            return back()->withErrors(['store' => $exception->getMessage()]);
        }
    }
}
