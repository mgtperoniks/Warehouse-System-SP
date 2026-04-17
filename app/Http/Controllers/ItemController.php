<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ItemVariant;

class ItemController extends Controller
{
    public function index()
    {
        return view('items.index');
    }

    public function show(ItemVariant $variant)
    {
        // Eager load everything needed for the rich detail page
        $variant->load(['item', 'suppliers', 'images', 'barcodes', 'bins']);
        return view('items.show', compact('variant'));
    }

    public function create()
    {
        return view('items.form', ['mode' => 'create']);
    }

    public function store(Request $request)
    {
        // To be handled/validated by Livewire primarily, or standard request
        abort(501, 'Not implemented');
    }

    public function edit(ItemVariant $variant)
    {
        return view('items.form', ['mode' => 'edit', 'variant' => $variant]);
    }

    public function update(Request $request, ItemVariant $variant)
    {
        // To be handled/validated by Livewire primarily, or standard request
        abort(501, 'Not implemented');
    }

    public function import(Request $request)
    {
        // Handle excel upload
        abort(501, 'Not implemented');
    }
}
