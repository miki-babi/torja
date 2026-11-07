<?php

namespace App\Http\Controllers\Member;

use App\Events\ConversationActivity;
use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Events\MessageUpdated;
use App\Events\ReactionChanged;
use App\Http\Controllers\Controller;
use App\Jobs\NotifyChatMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\MessageReaction;
use App\Support\Chat\MessagePayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class MessageController extends Controller
{
    /**
     * Send a message — body and/or up to five attachments. Broadcasts to
     * the room, pings each other participant's inbox channel, and queues
     * their Telegram push.
     */
    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $membership = $conversation->membershipFor($user);

        abort_unless($membership !== null, 403);
        abort_unless($user->isAdmin() || $user->isTrainer() || $conversation->isCoachThreadFor($user), 403);

        $data = $request->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf,text/plain,application/zip,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ]);

        $body = trim((string) ($data['body'] ?? ''));

        abort_if($body === '' && ! $request->hasFile('attachments'), 422);

        if (isset($data['reply_to_id'])) {
            abort_unless(
                Message::where('id', $data['reply_to_id'])
                    ->where('conversation_id', $conversation->id)
                    ->exists(),
                422,
            );
        }

        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => $body === '' ? null : $body,
            'reply_to_id' => $data['reply_to_id'] ?? null,
        ]);

        foreach ($request->file('attachments', []) as $file) {
            $this->storeAttachment($message, $conversation, $file);
        }

        $membership->update(['last_read_message_id' => $message->id]);

        broadcast(new MessageSent($message))->toOthers();

        $this->fanOut($conversation, $message);

        return response()->json(['message' => MessagePayload::make($message)], 201);
    }

    /**
     * Edit a sent body's text — sender only.
     */
    public function update(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $user = $request->user();

        abort_unless($conversation->isParticipant($user), 403);
        abort_unless($user->isAdmin() || $user->isTrainer() || $conversation->isCoachThreadFor($user), 403);
        abort_unless($message->user_id === $user->id, 403);
        abort_if($message->trashed(), 404);

        $data = $request->validate(['body' => ['required', 'string', 'max:4000']]);

        $message->update([
            'body' => trim($data['body']),
            'edited_at' => now(),
        ]);

        broadcast(new MessageUpdated($message))->toOthers();

        return response()->json(['message' => MessagePayload::make($message)]);
    }

    /**
     * Soft-delete a message — the sender, or an app admin.
     */
    public function destroy(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->isAdmin() || $user->isTrainer() || $conversation->isCoachThreadFor($user), 403);
        abort_unless(
            $message->user_id === $user->id || $conversation->canModerate($user),
            403,
        );
        abort_if($message->trashed(), 404);

        $message->delete();

        broadcast(new MessageDeleted($message))->toOthers();

        return response()->json(['ok' => true]);
    }

    /**
     * Toggle an emoji reaction on a message.
     */
    public function react(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $user = $request->user();

        abort_unless($conversation->isParticipant($user), 403);
        abort_unless($user->isAdmin() || $user->isTrainer() || $conversation->isCoachThreadFor($user), 403);
        abort_if($message->trashed(), 404);

        $data = $request->validate(['emoji' => ['required', 'string', 'max:16']]);

        $existing = MessageReaction::where('message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $data['emoji'])
            ->first();

        if ($existing !== null) {
            $existing->delete();
        } else {
            $message->reactions()->create([
                'user_id' => $request->user()->id,
                'emoji' => $data['emoji'],
            ]);
        }

        $message->refresh();

        broadcast(new ReactionChanged($message))->toOthers();

        return response()->json(['reactions' => MessagePayload::reactionsFor($message)]);
    }

    /**
     * Store one uploaded file as a message attachment on the public disk.
     */
    private function storeAttachment(Message $message, Conversation $conversation, UploadedFile $file): void
    {
        $mime = $file->getMimeType() ?? 'application/octet-stream';
        $isImage = str_starts_with($mime, 'image/');
        $width = null;
        $height = null;

        if ($isImage) {
            [$width, $height] = @getimagesize($file->getRealPath()) ?: [null, null];
        }

        $message->attachments()->create([
            'path' => $file->store("chat/{$conversation->id}", 'public'),
            'original_name' => $file->getClientOriginalName(),
            'mime' => $mime,
            'size' => $file->getSize(),
            'kind' => $isImage ? 'image' : 'file',
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * Inbox ping per other participant plus their Telegram push — one
     * broadcast each, one queued job each.
     */
    private function fanOut(Conversation $conversation, Message $message): void
    {
        $snippet = $message->body !== null && $message->body !== ''
            ? mb_strimwidth($message->body, 0, 60, '…')
            : '📎';

        $senderName = $message->sender->username ?: $message->sender->name;

        $conversation->memberships()
            ->with('user')
            ->where('user_id', '!=', $message->user_id)
            ->get()
            ->each(function ($membership) use ($conversation, $message, $snippet, $senderName): void {
                $recipient = $membership->user;

                broadcast(new ConversationActivity($recipient->id, [
                    'conversation_id' => $conversation->id,
                    'type' => $conversation->type->value,
                    'title' => $senderName,
                    'sender' => $senderName,
                    'snippet' => $snippet,
                    'created_at' => $message->created_at?->toIso8601String(),
                    'unread_total' => $recipient->unreadChatCount(),
                ]));

                NotifyChatMessage::dispatch($message->id, $recipient->id);
            });
    }
}
