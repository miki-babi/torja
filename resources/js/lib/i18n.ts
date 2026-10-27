import { usePage } from '@inertiajs/react';

export type LanguageOption = {
    code: string;
    name: string;
    native_name: string;
};

type SharedI18n = {
    locale: string;
    languages: LanguageOption[];
    translations: Record<string, string>;
};

/**
 * Client-side translations driven by the shared Inertia props
 * (locale, languages, translations) injected by HandleInertiaRequests.
 *
 * t('member.exercise.title') → translated string, or the key when absent.
 * t('member.today.last_trained_days_ago', { days: 3 }) replaces :placeholders.
 */
export function useTranslation(): {
    t: (key: string, replacements?: Record<string, string | number>) => string;
    locale: string;
    languages: LanguageOption[];
} {
    const { locale, languages, translations } = usePage<
        { props: SharedI18n } & SharedI18n
    >().props;

    function t(
        key: string,
        replacements?: Record<string, string | number>,
    ): string {
        let value = translations[key] ?? key;

        if (replacements !== undefined) {
            for (const [name, replacement] of Object.entries(replacements)) {
                value = value.replace(`:${name}`, String(replacement));
            }
        }

        return value;
    }

    return { t, locale, languages };
}
