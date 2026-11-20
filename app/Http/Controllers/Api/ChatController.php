<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $conversations = Conversation::query()
            ->with(['lowUser', 'highUser'])
            ->where(function ($query) use ($user) {
                $query->where('user_low_id', $user->id)->orWhere('user_high_id', $user->id);
            })
            ->latest('updated_at')
            ->get();

        return response()->json([
            'data' => $conversations->map(fn (Conversation $conversation) => $this->summary($conversation, $user)),
        ]);
    }

    public function open(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
        ]);

        abort_if((int) $data['user_id'] === $request->user()->id, 422, 'You cannot message yourself.');

        [$low, $high] = Conversation::pair($request->user()->id, (int) $data['user_id']);
        $conversation = Conversation::query()->firstOrCreate(
            ['user_low_id' => $low, 'user_high_id' => $high],
            ['booking_id' => $data['booking_id'] ?? null],
        );

        if (! empty($data['booking_id']) && $conversation->booking_id === null) {
            $conversation->booking_id = $data['booking_id'];
            $conversation->save();
        }

        $conversation->load(['lowUser', 'highUser']);

        return response()->json(['data' => $this->summary($conversation, $request->user())], 201);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);
        $conversation->load(['lowUser', 'highUser']);

        $conversation->messages()
            ->where('sender_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'data' => [
                'conversation' => $this->summary($conversation, $request->user()),
                'messages' => $conversation->messages()->oldest()->get()->map(fn (Message $message) => $this->message($message)),
            ],
        ]);
    }

    public function storeMessage(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->involves($request->user()), 403);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:2000'],
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
        ]);
        $conversation->touch();

        return response()->json(['data' => $this->message($message)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Conversation $conversation, User $viewer): array
    {
        $other = $conversation->otherUserId($viewer) === $conversation->user_low_id
            ? $conversation->lowUser
            : $conversation->highUser;
        $last = $conversation->messages()->latest()->first();
        $unread = $conversation->messages()
            ->where('sender_id', '!=', $viewer->id)
            ->whereNull('read_at')
            ->count();

        return [
            'id' => $conversation->id,
            'booking_id' => $conversation->booking_id,
            'updated_at' => $conversation->updated_at,
            'unread' => $unread,
            'other_user' => $other ? [
                'id' => $other->id,
                'name' => $other->name,
                'area' => $other->area,
                'role' => $other->role?->value ?? $other->role,
            ] : null,
            'last_message' => $last ? $this->message($last) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function message(Message $message): array
    {
        return [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'body' => $message->body,
            'read_at' => $message->read_at,
            'created_at' => $message->created_at,
        ];
    }
}
