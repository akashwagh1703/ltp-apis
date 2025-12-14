<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlayerResource;
use App\Models\Player;
use Illuminate\Http\Request;

class PlayerController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'status' => 'nullable|in:active,inactive,suspended,deleted',
                'search' => 'nullable|string|max:255',
                'per_page' => 'nullable|integer|min:5|max:100',
            ]);

            $query = Player::where('status', '!=', 'deleted')
                ->withCount('bookings')
                ->selectRaw("players.*, 
                    (SELECT SUM(amount) FROM bookings WHERE bookings.player_id = players.id AND bookings.booking_status = 'completed') as total_spent,
                    (SELECT MAX(booking_date) FROM bookings WHERE bookings.player_id = players.id) as last_booking_date
                ");

            if (isset($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            if (isset($validated['search'])) {
                $query->where(function ($q) use ($validated) {
                    $q->where('name', 'like', "%{$validated['search']}%")
                      ->orWhere('phone', 'like', "%{$validated['search']}%")
                      ->orWhere('email', 'like', "%{$validated['search']}%");
                });
            }

            $perPage = $validated['per_page'] ?? 15;
            $players = $query->latest('players.created_at')->paginate($perPage);

            return PlayerResource::collection($players);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch players',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $player = Player::with(['bookings' => function($query) {
                $query->latest()->limit(10);
            }])->withCount('bookings')->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => new PlayerResource($player)
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Player not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch player details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:active,inactive,suspended',
            ]);

            $player = Player::findOrFail($id);
            
            if ($player->status === $validated['status']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Player already has this status'
                ], 400);
            }

            $player->update(['status' => $validated['status']]);
            
            return response()->json([
                'success' => true,
                'message' => 'Player status updated successfully',
                'data' => new PlayerResource($player)
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Player not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update player status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
