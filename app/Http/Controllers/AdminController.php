<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\AdminAuditLog;
use App\Models\Game;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function storeProduct(StoreProductRequest $request): RedirectResponse
    {
        $input = $request->validated();
        try {
            DB::transaction(function () use ($input, $request) {
                $game = Game::whereKey($input['create_game_id'])->lockForUpdate()->firstOrFail();
                $name = (int) $input['quantity'].' '.$game->unit;
                if ($game->products()->where('source', 'demo')->where('name', $name)->exists()) {
                    throw ValidationException::withMessages(['quantity' => 'Nominal tersebut sudah ada untuk game ini. Ubah harga atau status produk yang sudah ada.']);
                }
                $product = Product::create([
                    'game_id' => $game->id, 'source' => 'demo',
                    'sku' => 'demo-custom-'.$game->id.'-'.(int) $input['quantity'],
                    'name' => $name, 'price' => (int) $input['create_price'], 'active' => $input['create_active'],
                ]);
                AdminAuditLog::create([
                    'user_id' => $request->user()->id, 'action' => 'product.created', 'entity_type' => 'product',
                    'entity_id' => $product->id, 'changes' => ['after' => $product->only(['game_id', 'source', 'sku', 'name', 'price', 'active'])],
                ]);
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['quantity' => 'Nominal tersebut sudah ditambahkan. Muat ulang daftar produk.']);
        }
        return redirect()->route('admin.index')->with('status', 'Nominal demo berhasil ditambahkan. Nominal aktif akan tampil jika game aktif dan sumber katalog adalah demo.');
    }

    public function index(): View
    {
        return view('admin.index', [
            'products' => Product::with('game')->orderBy('game_id')->orderBy('price')->paginate(24, ['*'], 'products_page'),
            'games' => Game::orderBy('id')->get(),
            'orders' => Order::with('payment')->latest('id')->paginate(10, ['*'], 'orders_page'),
            'orderCount' => Order::count(),
            'auditLogs' => AdminAuditLog::latest('id')->limit(10)->get(),
        ]);
    }

    public function updateProduct(Request $request, Product $product): RedirectResponse
    {
        // Harga supplier ditentukan oleh sinkronisasi, bukan formulir manual.
        $rules = ['active' => ['required', 'boolean']];
        if ($product->source === 'demo') $rules['price'] = ['required', 'integer', 'min:1', 'max:100000000'];
        $validated = $request->validate($rules);
        DB::transaction(function () use ($request, $product, $validated) {
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = $locked->only(array_keys($validated));
            $locked->update($validated);
            AdminAuditLog::create(['user_id' => $request->user()->id, 'action' => 'product.updated', 'entity_type' => 'product', 'entity_id' => $locked->id, 'changes' => ['before' => $before, 'after' => $locked->only(array_keys($validated))]]);
        });
        return back()->with('status', 'Produk diperbarui. Sinkronisasi berikutnya akan memperbarui status produk supplier.');
    }

    public function updateGame(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate(['active' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $game, $validated) {
            $locked = Game::whereKey($game->id)->lockForUpdate()->firstOrFail();
            $before = $locked->active;
            $locked->update($validated);
            AdminAuditLog::create(['user_id' => $request->user()->id, 'action' => 'game.updated', 'entity_type' => 'game', 'entity_id' => $locked->id, 'changes' => ['before' => ['active' => $before], 'after' => ['active' => $locked->active]]]);
        });
        return back()->with('status', 'Status game diperbarui.');
    }
}
