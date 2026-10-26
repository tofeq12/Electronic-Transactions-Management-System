<?php

namespace App\Support;

/**
 * Frontend localization bridge. Arabic is the source language; every
 * Arabic string used by the Blade views / JS is a key in lang/{locale}.json.
 * The full translation map is injected as window.ITQAN_T and the frontend
 * translates at display time via ITQAN.t() — server payloads (statuses,
 * roles, transaction types) keep their canonical values untouched.
 */
class I18n
{
    /**
     * Translation map for the current locale (empty for Arabic — source
     * strings already ARE Arabic).
     *
     * @return array<string, string>
     */
    public static function jsMap(): array
    {
        $locale = app()->getLocale();

        if ($locale === 'ar') {
            return [];
        }

        $file = lang_path($locale.'.json');

        if (! is_file($file)) {
            return [];
        }

        $map = json_decode((string) file_get_contents($file), true);

        return is_array($map) ? $map : [];
    }
}
