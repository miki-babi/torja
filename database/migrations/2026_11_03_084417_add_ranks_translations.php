<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Lines for the Ranks page (nav tab, opt-in card, scope tabs,
     * friends/groups/gym boards and dialogs).
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'nav' => [
            'ranks' => ['en' => 'Ranks', 'am' => 'ደረጃዎች'],
        ],
        'member.ranks' => [
            'title' => ['en' => 'Ranks', 'am' => 'ደረጃዎች'],
            'join_title' => ['en' => 'Compete with other members', 'am' => 'ከሌሎች አባላት ጋር ይፎካከሩ'],
            'join_hint' => ['en' => 'Join the leaderboards — friends, squads and your gym ranked by total volume lifted.', 'am' => 'ወደ ደረጃዎች ይቀላቀሉ — ጓደኞች፣ ቡድኖች እና ጂምዎ በጠቅላላ ክብደት ይመደባሉ።'],
            'join' => ['en' => 'Join the leaderboards', 'am' => 'ደረጃዎችን ይቀላቀሉ'],
            'leave' => ['en' => 'Leave ranks', 'am' => 'ደረጃዎችን ውለይ'],
            'empty' => ['en' => 'No one on the board yet.', 'am' => 'እስካሁን ሰሌዳው ላይ ማንም የለም።'],
            'scope.friends' => ['en' => 'Friends', 'am' => 'ጓደኞች'],
            'scope.groups' => ['en' => 'Groups', 'am' => 'ቡድኖች'],
            'scope.gym' => ['en' => 'Gym', 'am' => 'ጂም'],
            'requests' => ['en' => 'Friend requests', 'am' => 'የጓደኝነት ጥያቄዎች'],
            'pending' => ['en' => 'pending', 'am' => 'በመጠባበቅ ላይ'],
            'friends_board' => ['en' => 'Friends leaderboard', 'am' => 'የጓደኞች ደረጃ'],
            'add_friend' => ['en' => 'Add friend', 'am' => 'ጓደኛ ጨምር'],
            'search_users' => ['en' => 'Search by username or name', 'am' => 'በተጠቃሚ ስም ወይም በስም ይፈልጉ'],
            'groups' => ['en' => 'Groups', 'am' => 'ቡድኖች'],
            'create_group' => ['en' => 'Create group', 'am' => 'ቡድን ፍጠር'],
            'group_name' => ['en' => 'Group name', 'am' => 'የቡድን ስም'],
            'join_group' => ['en' => 'Join', 'am' => 'ቀላቀል'],
            'code' => ['en' => 'CODE', 'am' => 'ኮድ'],
            'copied' => ['en' => 'Invite code copied', 'am' => 'ኮድ ተቀድቷል'],
            'bad_code' => ['en' => 'Invalid invite code', 'am' => 'ልክ ያልሆነ ኮድ'],
            'gym_placeholder' => ['en' => 'Your gym name', 'am' => 'የጂምዎ ስም'],
        ],
        'common' => [
            'save' => ['en' => 'Save', 'am' => 'አስቀምጥ'],
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

    public function down(): void
    {
        foreach (self::LINES as $group => $lines) {
            LanguageLine::where('group', $group)
                ->whereIn('key', array_keys($lines))
                ->delete();
        }

        Translator::flush();
    }
};
