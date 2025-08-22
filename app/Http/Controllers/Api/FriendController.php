<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GardenFriend;
use App\Models\User;
use App\Models\UserGarden;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class FriendController extends Controller
{
    /**
     * Get current user ID (for testing, use a default user)
     */
    private function getCurrentUserId(): string
    {
        // For testing purposes, use a default test user ID
        // In production, this should use Auth::id()
        return Auth::id() ?? '0198b246-1b0e-7cd6-8f5e-8a0a5b787402'; // Test user ID from seeder
    }
    /**
     * Get friends list for the authenticated user
     */
    public function getFriendsList(): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $friends = GardenFriend::getFriendsList($userId);
            
            // Enrich friends data with garden info
            $enrichedFriends = collect($friends)->map(function($friend) {
                $garden = UserGarden::where('user_id', $friend['id'])->first();
                return [
                    'id' => $friend['id'],
                    'name' => $friend['name'],
                    'email' => $friend['email'],
                    'garden' => $garden ? [
                        'level' => $garden->level,
                        'xp' => $garden->xp,
                        'star_seeds' => $garden->star_seeds,
                        'total_plants' => $garden->plants()->count(),
                        'theme' => $garden->theme ?? 'default'
                    ] : null,
                    'last_active' => $friend['updated_at'] ?? null
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'friends' => $enrichedFriends,
                    'total_friends' => $enrichedFriends->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถดึงรายชื่อเพื่อนได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get pending friend requests
     */
    public function getPendingRequests(): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $pendingRequests = GardenFriend::getPendingRequests($userId);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'pending_requests' => $pendingRequests,
                    'total_pending' => count($pendingRequests)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถดึงคำขอเป็นเพื่อนได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send friend request
     */
    public function sendFriendRequest(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'friend_email' => 'required|email|exists:users,email',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'ข้อมูลไม่ถูกต้อง',
                    'errors' => $validator->errors()
                ], 400);
            }

            $userId = $this->getCurrentUserId();
            $friendUser = User::where('email', $request->friend_email)->first();

            if (!$friendUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบผู้ใช้ที่มีอีเมลนี้'
                ], 404);
            }

            if ($friendUser->id === $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่สามารถส่งคำขอเป็นเพื่อนให้ตัวเองได้'
                ], 400);
            }

            // Check if already friends or request exists
            if (GardenFriend::areFriends($userId, $friendUser->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'คุณเป็นเพื่อนกับผู้ใช้คนนี้อยู่แล้ว'
                ], 400);
            }

            $existingRequest = GardenFriend::where(function($query) use ($userId, $friendUser) {
                $query->where('user_id', $userId)->where('friend_id', $friendUser->id);
            })->orWhere(function($query) use ($userId, $friendUser) {
                $query->where('user_id', $friendUser->id)->where('friend_id', $userId);
            })->first();

            if ($existingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'มีคำขอเป็นเพื่อนอยู่แล้ว'
                ], 400);
            }

            $friendRequest = GardenFriend::sendRequest($userId, $friendUser->id);

            if (!$friendRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่สามารถส่งคำขอเป็นเพื่อนได้'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'ส่งคำขอเป็นเพื่อนเรียบร้อยแล้ว',
                'data' => [
                    'request_id' => $friendRequest->id,
                    'friend_name' => $friendUser->name,
                    'status' => 'pending'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการส่งคำขอ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Accept friend request
     */
    public function acceptFriendRequest(string $requestId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $friendRequest = GardenFriend::where('id', $requestId)
                ->where('friend_id', $userId)
                ->where('status', 'pending')
                ->first();

            if (!$friendRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบคำขอเป็นเพื่อนหรือคำขอไม่ถูกต้อง'
                ], 404);
            }

            if ($friendRequest->accept()) {
                $requester = User::find($friendRequest->user_id);
                
                return response()->json([
                    'success' => true,
                    'message' => 'ยอมรับคำขอเป็นเพื่อนเรียบร้อยแล้ว',
                    'data' => [
                        'friend_name' => $requester->name,
                        'friend_id' => $requester->id,
                        'accepted_at' => $friendRequest->accepted_at
                    ]
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถยอมรับคำขอได้'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการยอมรับคำขอ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject friend request
     */
    public function rejectFriendRequest(string $requestId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            $friendRequest = GardenFriend::where('id', $requestId)
                ->where('friend_id', $userId)
                ->where('status', 'pending')
                ->first();

            if (!$friendRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบคำขอเป็นเพื่อนหรือคำขอไม่ถูกต้อง'
                ], 404);
            }

            if ($friendRequest->reject()) {
                return response()->json([
                    'success' => true,
                    'message' => 'ปฏิเสธคำขอเป็นเพื่อนเรียบร้อยแล้ว'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถปฏิเสธคำขอได้'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการปฏิเสธคำขอ: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove friend
     */
    public function removeFriend(string $friendId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            
            $friendship = GardenFriend::where(function($query) use ($userId, $friendId) {
                $query->where('user_id', $userId)->where('friend_id', $friendId);
            })->orWhere(function($query) use ($userId, $friendId) {
                $query->where('user_id', $friendId)->where('friend_id', $userId);
            })->where('status', 'accepted')->first();

            if (!$friendship) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบความสัมพันธ์เพื่อนหรือไม่ได้เป็นเพื่อนกัน'
                ], 404);
            }

            $friend = User::find($friendId);
            $friendName = $friend ? $friend->name : 'ผู้ใช้';

            if ($friendship->delete()) {
                return response()->json([
                    'success' => true,
                    'message' => "ลบ {$friendName} ออกจากรายชื่อเพื่อนเรียบร้อยแล้ว"
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถลบเพื่อนได้'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดในการลบเพื่อน: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Visit friend's garden
     */
    public function visitFriendGarden(string $friendId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // Check if they are friends
            if (!GardenFriend::areFriends($userId, $friendId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'คุณไม่ได้เป็นเพื่อนกับผู้ใช้คนนี้'
                ], 403);
            }

            $friend = User::find($friendId);
            if (!$friend) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบผู้ใช้'
                ], 404);
            }

            $garden = UserGarden::where('user_id', $friendId)->first();
            if (!$garden) {
                return response()->json([
                    'success' => false,
                    'message' => 'เพื่อนของคุณยังไม่มีสวน'
                ], 404);
            }

            $plants = $garden->plants()->with('plantType')->get()->map(function($plant) {
                return [
                    'id' => $plant->id,
                    'name' => $plant->name,
                    'type' => $plant->plantType->name,
                    'category' => $plant->plantType->category,
                    'stage' => $plant->stage,
                    'health' => $plant->health,
                    'position' => json_decode($plant->position ?? '{}'),
                    'planted_at' => $plant->created_at,
                    'last_watered' => $plant->last_watered_at,
                    'can_help_water' => !$plant->last_watered_at || $plant->last_watered_at->diffInHours(now()) >= 12
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'friend' => [
                        'id' => $friend->id,
                        'name' => $friend->name,
                        'avatar' => null // Add avatar field if available
                    ],
                    'garden' => [
                        'id' => $garden->id,
                        'level' => $garden->level,
                        'xp' => $garden->xp,
                        'star_seeds' => $garden->star_seeds,
                        'theme' => $garden->theme ?? 'default',
                        'layout' => json_decode($garden->garden_layout ?? '{}'),
                        'last_active' => $garden->updated_at
                    ],
                    'plants' => $plants,
                    'stats' => [
                        'total_plants' => $plants->count(),
                        'healthy_plants' => $plants->where('health', '>', 80)->count(),
                        'plants_need_water' => $plants->where('can_help_water', true)->count()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถเยี่ยมชมสวนได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Help water friend's plant
     */
    public function helpWaterPlant(string $friendId, string $plantId): JsonResponse
    {
        try {
            $userId = $this->getCurrentUserId();
            
            // Check if they are friends
            if (!GardenFriend::areFriends($userId, $friendId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'คุณไม่ได้เป็นเพื่อนกับผู้ใช้คนนี้'
                ], 403);
            }

            $plant = \App\Models\UserPlant::where('id', $plantId)
                ->where('user_id', $friendId)
                ->first();

            if (!$plant) {
                return response()->json([
                    'success' => false,
                    'message' => 'ไม่พบพืชที่ต้องการช่วยรดน้ำ'
                ], 404);
            }

            // Check if plant was recently watered
            if ($plant->last_watered_at && $plant->last_watered_at->diffInHours(now()) < 12) {
                return response()->json([
                    'success' => false,
                    'message' => 'พืชนี้เพิ่งได้รับการรดน้ำแล้ว'
                ], 400);
            }

            // Help water the plant
            $plant->last_watered_at = now();
            $plant->health = min(100, $plant->health + 5); // Small health boost
            $plant->save();

            // Give helper some XP bonus
            $helperGarden = UserGarden::where('user_id', $userId)->first();
            if ($helperGarden) {
                $xpBonus = 5; // Small XP reward for helping
                $helperGarden->xp += $xpBonus;
                $helperGarden->save();
            }

            $friend = User::find($friendId);

            return response()->json([
                'success' => true,
                'message' => "ช่วยรดน้ำพืชของ {$friend->name} เรียบร้อยแล้ว! ได้รับ 5 XP",
                'data' => [
                    'plant' => [
                        'id' => $plant->id,
                        'name' => $plant->name,
                        'health' => $plant->health,
                        'last_watered_at' => $plant->last_watered_at
                    ],
                    'helper_xp_gained' => $xpBonus ?? 0
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถช่วยรดน้ำได้: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search users to add as friends
     */
    public function searchUsers(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:2|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'กรุณากรอกคำค้นหาอย่างน้อย 2 ตัวอักษร',
                    'errors' => $validator->errors()
                ], 400);
            }

            $userId = $this->getCurrentUserId();
            $query = $request->query;

            $users = User::where('id', '!=', $userId)
                ->where(function($q) use ($query) {
                    $q->where('name', 'LIKE', "%{$query}%")
                      ->orWhere('email', 'LIKE', "%{$query}%");
                })
                ->limit(10)
                ->get();

            // Check friendship status for each user
            $searchResults = $users->map(function($user) use ($userId) {
                $friendshipStatus = 'none';
                
                if (GardenFriend::areFriends($userId, $user->id)) {
                    $friendshipStatus = 'friends';
                } else {
                    $existingRequest = GardenFriend::where(function($query) use ($userId, $user) {
                        $query->where('user_id', $userId)->where('friend_id', $user->id);
                    })->orWhere(function($query) use ($userId, $user) {
                        $query->where('user_id', $user->id)->where('friend_id', $userId);
                    })->first();

                    if ($existingRequest) {
                        if ($existingRequest->user_id === $userId) {
                            $friendshipStatus = 'request_sent';
                        } else {
                            $friendshipStatus = 'request_received';
                        }
                    }
                }

                $garden = UserGarden::where('user_id', $user->id)->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'friendship_status' => $friendshipStatus,
                    'garden_level' => $garden ? $garden->level : 0,
                    'joined_at' => $user->created_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'users' => $searchResults,
                    'total_found' => $searchResults->count(),
                    'query' => $query
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถค้นหาผู้ใช้ได้: ' . $e->getMessage()
            ], 500);
        }
    }
}