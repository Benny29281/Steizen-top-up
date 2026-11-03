<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\GuestOwner;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoreController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Game::where('active', true)->select('category')->distinct()->orderBy('category')->pluck('category');
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:80'], 'category' => ['nullable', 'string', Rule::in($categories->all())]]);
        $query = trim($validated['q'] ?? '');
        $selectedCategory = $validated['category'] ?? '';
        $allGames = Game::where('active', true)->orderBy('id')->get();
        $games = $allGames->filter(fn (Game $game) => ($query === '' || mb_stripos($game->name, $query) !== false) && ($selectedCategory === '' || $game->category === $selectedCategory));
        $featuredGames = $allGames->take(3);
        return view('store.index', compact('games', 'query', 'categories', 'selectedCategory', 'featuredGames'));
    }

    public function show(Request $request, Game $game, GuestOwner $owner): View
    {
        abort_unless($game->active, 404);
        // Tetapkan pemilik sebelum form ditampilkan agar request paralel memakai identitas sama.
        $owner->hash($request);
        $products = $game->products()->available()->orderBy('price')->get();
        return view('store.show', compact('game', 'products'));
    }
}
