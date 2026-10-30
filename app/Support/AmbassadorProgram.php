<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Admin-editable Ambassador (affiliate) program settings. Defaults ship in
 * code; overrides live in the `ambassador` setting row.
 */
class AmbassadorProgram
{
    public const SETTING_KEY = 'ambassador';

    /**
     * @return array{enabled: bool, commission_etb: int, min_payout_etb: int, referrals_for_pro: int}
     */
    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'commission_etb' => 100,
            'min_payout_etb' => 200,
            'referrals_for_pro' => 5,
        ];
    }

    /**
     * Defaults overlaid with whatever the admin saved.
     *
     * @return array{enabled: bool, commission_etb: int, min_payout_etb: int, referrals_for_pro: int}
     */
    public static function settings(): array
    {
        $stored = Setting::get(self::SETTING_KEY, []);

        return array_replace(self::defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * Whether the program accepts new commissions. Invite links keep
     * working either way; existing rows stay manageable when disabled.
     */
    public static function isEnabled(): bool
    {
        return (bool) self::settings()['enabled'];
    }

    /**
     * The fixed ETB commission per referred Pro purchase.
     */
    public static function commissionEtb(): int
    {
        return (int) self::settings()['commission_etb'];
    }

    /**
     * The minimum approved balance required before a payout can be
     * requested.
     */
    public static function minPayoutEtb(): int
    {
        return (int) self::settings()['min_payout_etb'];
    }

    /**
     * Referred signups needed to earn Pro for free. `0` disables the
     * referral-reward milestone entirely.
     */
    public static function referralsForPro(): int
    {
        return (int) self::settings()['referrals_for_pro'];
    }

    /**
     * Validation rules for the admin settings form.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'commission_etb' => ['required', 'integer', 'min:1', 'max:1000000'],
            'min_payout_etb' => ['required', 'integer', 'min:1', 'max:1000000'],
            'referrals_for_pro' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }
}
