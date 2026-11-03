<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Lines for the chats inbox (`member.chats`), the chat thread
     * (`member.chat`) and the new gym picker strings (`member.ranks`).
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'member.chats' => [
            'title' => ['en' => 'Chats', 'am' => 'ውይይቶች'],
            'back' => ['en' => 'Back', 'am' => 'ተመለስ'],
            'empty' => ['en' => 'No chats yet. Message a friend, join a squad or pick your gym on the Ranks page.', 'am' => 'እስካሁን ውይይት የለም። ጓደኛ ይምቱ፣ ቡድን ይቀላቀሉ ወይም ጂምዎን በደረጃዎች ገጽ ላይ ይምረጡ።'],
            'open' => ['en' => 'Chat', 'am' => 'ውይይት'],
            'dm_failed' => ['en' => 'Could not open the chat', 'am' => 'ውይይቱን መክፈት አልተቻለም'],
        ],
        'member.chat' => [
            'placeholder' => ['en' => 'Message…', 'am' => 'መልእክት…'],
            'send' => ['en' => 'Send', 'am' => 'ላክ'],
            'attach' => ['en' => 'Attach files', 'am' => 'ፋይሎች አያይዝ'],
            'attachment' => ['en' => 'Attachment', 'am' => 'አባሪ'],
            'reply' => ['en' => 'Reply', 'am' => 'መለስ'],
            'copy' => ['en' => 'Copy text', 'am' => 'ጽሑፍ ቅዳ'],
            'edit' => ['en' => 'Edit', 'am' => 'አስተካክል'],
            'delete' => ['en' => 'Delete', 'am' => 'ሰርዝ'],
            'actions' => ['en' => 'Message actions', 'am' => 'የመልእክት እርምጃዎች'],
            'copied' => ['en' => 'Copied', 'am' => 'ተቀድቷል'],
            'edited' => ['en' => 'edited', 'am' => 'ተስተካክሏል'],
            'editing' => ['en' => 'Editing message', 'am' => 'መልእክት በማስተካከል ላይ'],
            'deleted' => ['en' => 'This message was deleted', 'am' => 'ይህ መልእክት ተሰርዟል'],
            'typing' => ['en' => 'typing…', 'am' => 'በመጻፍ ላይ…'],
            'online' => ['en' => 'Online', 'am' => 'መስመር ላይ'],
            'offline' => ['en' => 'Offline', 'am' => 'መስመር ውጪ'],
            'online_count' => ['en' => 'online', 'am' => 'መስመር ላይ'],
            'loading' => ['en' => 'Loading…', 'am' => 'በመጫን ላይ…'],
            'start' => ['en' => 'This is the start of the conversation', 'am' => 'የውይይቱ መጀመሪያ ይህ ነው'],
            'mute' => ['en' => 'Mute notifications', 'am' => 'ማሳወቂያዎችን አጥፋ'],
            'unmute' => ['en' => 'Unmute notifications', 'am' => 'ማሳወቂያዎችን አብራ'],
            'send_failed' => ['en' => 'Message failed to send', 'am' => 'መልእክቱ መላክ አልተቻለም'],
        ],
        'member.ranks' => [
            'gym_placeholder' => ['en' => 'Search or create your gym', 'am' => 'ጂምዎን ይፈልጉ ወይም ይፍጠሩ'],
            'gym_create' => ['en' => 'Create ":name"', 'am' => '":name" ፍጠር'],
            'gym_members' => ['en' => 'members', 'am' => 'አባላት'],
            'gym_leave' => ['en' => 'Leave gym', 'am' => 'ጂም ውለይ'],
            'gym_error' => ['en' => 'Could not save the gym', 'am' => 'ጂሙን ማስቀመጥ አልተቻለም'],
        ],
    ];

    public function up(): void
    {
        foreach (self::LINES as $group => $lines) {
            foreach ($lines as $key => $text) {
                $line = LanguageLine::firstOrNew(['group' => $group, 'key' => $key]);
                $line->text = array_merge($text, $line->text ?? []);
                $line->save();
            }
        }

        Translator::flush();
    }
};
