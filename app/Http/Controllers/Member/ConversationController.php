<?php

namespace App\Http\Controllers\Member;

use App\Enums\ConversationType;
use App\Events\MessagesRead;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\User;
use App\Support\Chat\MessagePayload;
use App\Support\Social\FriendSuggestions;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    /**
     * The inbox — every room the member sits in, newest activity first,
     * with the last message and the unread count for each.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $memberships = ConversationParticipant::where('user_id', $user->id)
            ->get()
            ->keyBy('conversation_id');

        /** @var Collection<int, Conversation> $conversations */
        $conversations = Conversation::whereIn('id', $memberships->keys()->all() ?: [0])
            ->with(['group:id,name', 'gym:id,name'])
            ->withCount('participants')
            ->get();

        $ids = $conversations->modelKeys() ?: [0];

        $lastIds = Message::whereIn('conversation_id', $ids)
            ->whereNull('deleted_at')
            ->groupBy('conversation_id')
            ->selectRaw('conversation_id, MAX(id) as mid')
            ->pluck('mid', 'conversation_id');

        $lastMessages = Message::whereIn('id', $lastIds->values()->all() ?: [0])
            ->with('sender:id,name,username,avatar_path')
            ->get()
            ->keyBy('conversation_id')
            ->all();

        // The "other side" of each DM for titles and avatars.
        $dmOthers = ConversationParticipant::whereIn(
            'conversation_id',
            $conversations->where('type', ConversationType::Dm)->modelKeys() ?: [0],
        )
            ->where('user_id', '!=', $user->id)
            ->with('user:id,name,username,avatar_path')
            ->get()
            ->keyBy('conversation_id')
            ->all();

        // Unread per room: messages newer than the member's read cursor,
        // excluding their own.
        $unread = Message::where(function ($query) use ($memberships): void {
            foreach ($memberships as $membership) {
                $query->orWhere(function ($q) use ($membership): void {
                    $q->where('conversation_id', $membership->conversation_id)
                        ->where('id', '>', $membership->last_read_message_id ?? 0);
                });
            }
        })
            ->whereNull('deleted_at')
            ->where('user_id', '!=', $user->id)
            ->groupBy('conversation_id')
            ->selectRaw('conversation_id, COUNT(*) as unread')
            ->pluck('unread', 'conversation_id');

        $pendingIn = Friendship::where('friend_id', $user->id)
            ->where('status', 'pending')
            ->with('user:id,name,username,avatar_path')
            ->get()
            ->map(fn (Friendship $f): array => [
                'id' => $f->id,
                'name' => $f->user->username ?: $f->user->name,
                'avatar' => $f->user->avatar,
            ])
            ->all();

        $pendingOut = Friendship::where('user_id', $user->id)
            ->where('status', 'pending')
            ->with('friend:id,name,username,avatar_path')
            ->get()
            ->map(fn (Friendship $f): array => [
                'id' => $f->id,
                'name' => $f->friend->username ?: $f->friend->name,
                'avatar' => $f->friend->avatar,
            ])
            ->all();

        return Inertia::render('member/messages', [
            'pendingIn' => $pendingIn,
            'pendingOut' => $pendingOut,
            'suggestions' => app(FriendSuggestions::class)->for($user, 10),
            'hasLocation' => $user->latitude !== null && $user->longitude !== null,
            'conversations' => $conversations
                ->map(function (Conversation $conversation) use ($user, $memberships, $lastMessages, $dmOthers, $unread): array {
                    $membership = $memberships[$conversation->id];
                    $last = $lastMessages[$conversation->id] ?? null;
                    $other = $dmOthers[$conversation->id]->user ?? null;

                    return [
                        'id' => $conversation->id,
                        'type' => $conversation->type->value,
                        'title' => match ($conversation->type) {
                            ConversationType::Dm => $other !== null
                                ? ($other->username ?: $other->name)
                                : 'Chat',
                            ConversationType::Group => $conversation->group->name,
                            ConversationType::Gym => $conversation->gym->name,
                        },
                        'avatar' => $conversation->type === ConversationType::Dm ? $other?->avatar : null,
                        'members' => $conversation->participants_count,
                        'muted' => $membership->muted_at !== null,
                        'unread' => (int) $unread->get($conversation->id, 0),
                        'last_message' => $last !== null ? [
                            'sender' => $last->sender->username ?: $last->sender->name,
                            'mine' => $last->user_id === $user->id,
                            'snippet' => $last->body !== null && $last->body !== ''
                                ? mb_strimwidth($last->body, 0, 60, '…')
                                : '📎',
                            'created_at' => $last->created_at->toIso8601String(),
                        ] : null,
                        'activity' => ($last instanceof Message
                            ? $last->created_at
                            : $conversation->created_at)->toIso8601String(),
                    ];
                })
                ->sortByDesc('activity')
                ->values()
                ->all(),
        ]);
    }

    /**
     * Open (or lazily create) the DM room with a friend. An accepted
     * friendship is required to start the room; once it exists it stays
     * usable even if the friendship is later removed.
     */
    public function dm(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $me = $request->user();
        $other = User::findOrFail((int) $data['user_id']);

        abort_if($other->id === $me->id, 422);

        $isFriend = Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q
                ->where(fn ($qq) => $qq->where('user_id', $me->id)->where('friend_id', $other->id))
                ->orWhere(fn ($qq) => $qq->where('user_id', $other->id)->where('friend_id', $me->id)))
            ->exists();

        abort_unless($isFriend || Conversation::where('dm_key', collect([$me->id, $other->id])->sort()->implode(':'))->exists(), 403);

        $conversation = Conversation::dmBetween($me, $other);

        return response()->json(['conversation_id' => $conversation->id]);
    }

    /**
     * The chat thread — newest page of messages plus the participant
     * roster with read cursors for seen state.
     */
    public function show(Request $request, Conversation $conversation): Response
    {
        $user = $request->user();
        $membership = $conversation->membershipFor($user);

        abort_unless($membership !== null, 403);

        $messages = $conversation->messages()
            ->withTrashed()
            ->latest('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        $other = $conversation->otherParticipant($user);

        return Inertia::render('member/messages-show', [
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type->value,
                'title' => match ($conversation->type) {
                    ConversationType::Dm => $other !== null ? ($other->username ?: $other->name) : 'Chat',
                    ConversationType::Group => $conversation->group->name,
                    ConversationType::Gym => $conversation->gym->name,
                },
                'avatar' => $conversation->type === ConversationType::Dm ? $other?->avatar : null,
                'can_moderate' => $conversation->canModerate($user),
                'muted' => $membership->muted_at !== null,
            ],
            'participants' => $conversation->memberships()
                ->with('user:id,name,username,avatar_path')
                ->get()
                ->map(fn (ConversationParticipant $m): array => [
                    'id' => $m->user_id,
                    'name' => $m->user->username ?: $m->user->name,
                    'avatar' => $m->user->avatar,
                    'last_read_message_id' => $m->last_read_message_id,
                ])
                ->all(),
            'messages' => $messages->map(fn (Message $m): array => MessagePayload::make($m))->all(),
            'next_cursor' => $messages->first()?->id,
        ]);
    }

    /**
     * The previous page of messages for infinite scroll-up.
     */
    public function older(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->isParticipant($request->user()), 403);

        $before = (int) $request->query('before_id', PHP_INT_MAX);

        $messages = $conversation->messages()
            ->withTrashed()
            ->where('id', '<', $before)
            ->latest('id')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'messages' => $messages->map(fn (Message $m): array => MessagePayload::make($m))->all(),
            'next_cursor' => $messages->first()?->id,
        ]);
    }

    /**
     * Advance the member's read cursor — drives unread counts and the
     * "seen" indicator for everyone else in the room.
     */
    public function read(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $membership = $conversation->membershipFor($user);

        abort_unless($membership !== null, 403);

        $data = $request->validate(['message_id' => ['required', 'integer']]);
        $latest = (int) $conversation->messages()->withTrashed()->max('id');
        $cursor = min($data['message_id'], $latest);

        if ($cursor > ($membership->last_read_message_id ?? 0)) {
            $membership->update(['last_read_message_id' => $cursor]);
            broadcast(new MessagesRead($conversation, $user, $cursor))->toOthers();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Mute Telegram pushes for this room (badge counts still accrue).
     */
    public function mute(Request $request, Conversation $conversation): JsonResponse
    {
        $membership = $conversation->membershipFor($request->user());

        abort_unless($membership !== null, 403);

        $membership->update(['muted_at' => now()]);

        return response()->json(['muted' => true]);
    }

    /**
     * Unmute the room.
     */
    public function unmute(Request $request, Conversation $conversation): JsonResponse
    {
        $membership = $conversation->membershipFor($request->user());

        abort_unless($membership !== null, 403);

        $membership->update(['muted_at' => null]);

        return response()->json(['muted' => false]);
    }
}
