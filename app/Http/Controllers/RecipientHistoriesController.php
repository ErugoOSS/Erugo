<?php

namespace App\Http\Controllers;

use App\Models\RecipientHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecipientHistoriesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $q = $request->query('q');
        $q = is_string($q) ? trim($q) : '';

        $limit = (int) $request->query('limit', 10);
        if ($limit <= 0) {
            $limit = 10;
        }
        if ($limit > 20) {
            $limit = 20;
        }

        $query = RecipientHistory::query()->where('user_id', $user->id);

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('email', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            });
        }

        $items = $query
            ->orderByRaw('last_used_at IS NULL ASC')
            ->orderByDesc('last_used_at')
            ->orderByDesc('use_count')
            ->limit($limit)
            ->get(['email', 'name', 'last_used_at', 'use_count']);

        return response()->json($items);
    }

    public function getAll(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = RecipientHistory::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get(['id', 'email', 'name', 'created_at', 'last_used_at', 'use_count']);

        return response()->json($items);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $item = RecipientHistory::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$item) {
            return response()->json(['message' => 'Recipient history not found'], 404);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'nullable|string|max:255'
        ]);

        $item->email = $validated['email'];
        if (isset($validated['name'])) {
            $item->name = $validated['name'];
        }
        $item->save();

        return response()->json($item);
    }

    public function delete(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $item = RecipientHistory::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$item) {
            return response()->json(['message' => 'Recipient history not found'], 404);
        }

        $item->delete();

        return response()->json(['message' => 'Recipient history deleted']);
    }
}
