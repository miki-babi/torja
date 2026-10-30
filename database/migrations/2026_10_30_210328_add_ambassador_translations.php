<?php

use App\Models\LanguageLine;
use App\Support\Translator;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Lines for the member Ambassador wallet page and the profile card.
     * Existing locale text is never overwritten.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    private const LINES = [
        'ambassador' => [
            'title' => ['en' => 'Ambassador', 'am' => 'አምባሳደር'],
            'hero_body' => ['en' => 'Earn :amount ETB for every friend who unlocks Pro.', 'am' => 'ጓደኛዎ Pro ሲከፍት :amount ብር ያገኛሉ።'],
            'share_title' => ['en' => 'Join me on Toraj', 'am' => 'በToraj ተቀላቀሉኝ'],
            'share_text' => ['en' => 'Sign up with my invite link and start training.', 'am' => 'በግብዣ ሊንኬ ይመዝገቡ እና ልምምድ ይጀምሩ።'],
            'copy_link' => ['en' => 'Copy link', 'am' => 'ሊንክ ቅዳ'],
            'copied' => ['en' => 'Copied!', 'am' => 'ተቀድቷል!'],
            'share' => ['en' => 'Share', 'am' => 'አጋራ'],
            'card_title' => ['en' => 'Ambassador wallet', 'am' => 'የአምባሳደር ዋሌት'],
            'card_body' => ['en' => 'Invite friends — earn :amount ETB each', 'am' => 'ጓደኞችን ይጋብዙ — እያንዳንዱ :amount ብር'],
            'available' => ['en' => 'Available balance', 'am' => 'የሚከፈል ቀሪ'],
            'pending' => ['en' => 'Pending approval', 'am' => 'በጸድቆ ላይ'],
            'paid_total' => ['en' => 'Paid out', 'am' => 'የተከፈለ'],
            'request_payout' => ['en' => 'Request payout', 'am' => 'ክፍያ ጠይቅ'],
            'payout_in_review' => ['en' => 'Payout request in review', 'am' => 'የክፍያ ጥያቄ በጸድቆ ላይ'],
            'payout_min_hint' => ['en' => 'Minimum payout is :amount ETB', 'am' => 'ዝቅተኛ ክፍያ :amount ብር ነው'],
            'stat_invited' => ['en' => 'friends invited', 'am' => 'የተጋበዙ ጓደኞች'],
            'stat_pro' => ['en' => 'went Pro', 'am' => 'Pro የሆኑ'],
            'ledger_title' => ['en' => 'Transactions', 'am' => 'ዝውውሮች'],
            'ledger_empty' => ['en' => 'No transactions yet — share your invite link to start earning.', 'am' => 'እስካሁን ዝውውር የለም — ለማትረፍ ግብዣ ሊንክዎን ያጋሩ።'],
            'credit_label' => ['en' => 'Commission — :name', 'am' => 'ኮሚሽን — :name'],
            'debit_label' => ['en' => 'Payout', 'am' => 'ክፍያ'],
            'referrals_title' => ['en' => 'Your invites', 'am' => 'የእርስዎ ግብዣዎች'],
            'referrals_empty' => ['en' => 'No friends joined yet.', 'am' => 'እስካሁን ምንም ጓደኛ አልተቀላቀለም።'],
            'joined' => ['en' => 'Joined', 'am' => 'ተቀላቅሏል'],
            'pro_badge' => ['en' => 'Pro', 'am' => 'Pro'],
            'free_badge' => ['en' => 'Free', 'am' => 'ነጻ'],
            'payouts_title' => ['en' => 'Payouts', 'am' => 'ክፍያዎች'],
            'payout_dialog_title' => ['en' => 'Request payout', 'am' => 'ክፍያ ጠይቅ'],
            'payout_method' => ['en' => 'Payout method', 'am' => 'የክፍያ መንገድ'],
            'payout_account' => ['en' => 'Phone or account number', 'am' => 'ስልክ ወይም የባንክ ቁጥር'],
            'payout_account_hint' => ['en' => 'e.g. 0911 223 344', 'am' => 'ለምሳሌ 0911 223 344'],
            'payout_note' => ['en' => 'Note (optional)', 'am' => 'ማስታወሻ (አማራጭ)'],
            'payout_amount_hint' => ['en' => 'You will receive :amount ETB', 'am' => ':amount ብር ይደርስዎታል'],
            'payout_submit' => ['en' => 'Send request', 'am' => 'ጥያቄ ላክ'],
            'status.pending' => ['en' => 'Pending', 'am' => 'በጠባበቆ ላይ'],
            'status.approved' => ['en' => 'Approved', 'am' => 'ጸድቋል'],
            'status.paid' => ['en' => 'Paid', 'am' => 'ተከፍሏል'],
            'status.void' => ['en' => 'Void', 'am' => 'ተሰርዟል'],
            'status.requested' => ['en' => 'Requested', 'am' => 'ተጠይቋል'],
            'status.rejected' => ['en' => 'Rejected', 'am' => 'ውድቅ ተደርጓል'],
            'method.telebirr' => ['en' => 'Telebirr', 'am' => 'ቴሌብር'],
            'method.mpesa' => ['en' => 'M-Pesa', 'am' => 'ኤም-ፔሳ'],
            'method.cbe' => ['en' => 'CBE Birr', 'am' => 'ሲቢኢ ብር'],
            'method.bank' => ['en' => 'Bank transfer', 'am' => 'የባንክ ማስተላለፍ'],
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
