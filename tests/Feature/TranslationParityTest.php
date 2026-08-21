<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/*
 * Cloudmarktplaats is NL-first: __('Dutch sentence') calls — in views and
 * in application code — map through lang/en.json to English. Miss a key
 * and an English visitor silently sees Dutch instead — that's what shipped
 * unnoticed with the claim-link feature (~40 keys, caught only in a
 * manual end review), and again with DealService's exception messages,
 * which weren't wrapped in __() at all and so weren't even reachable by
 * a views-only scan.
 *
 * This test statically collects every literal __() / trans_choice() key
 * used across resources/views and app, and fails if lang/en.json doesn't
 * have a translation for it. Only string-literal arguments are checked: a
 * call like __($status) can't be resolved without executing the code, so
 * those are skipped rather than guessed at. As of writing, the only such
 * call in the app is __($status) in ResetPassword.php, resolved against
 * Laravel's line-based `passwords` namespace rather than lang/en.json.
 */
it('has an English translation for every literal __() and trans_choice() key used in the app', function () {
    $translations = json_decode(
        (string) file_get_contents(lang_path('en.json')),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    $pattern = '/(__|trans_choice)\(\s*([\'"])((?:\\\\.|(?!\2).)*)\2/s';

    $missing = [];

    $files = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
        ->merge(collect(File::allFiles(app_path()))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.php')));

    foreach ($files as $file) {
        if (! preg_match_all($pattern, $file->getContents(), $matches, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as [, , $quote, $raw]) {
            $key = $quote === "'"
                ? str_replace(['\\\'', '\\\\'], ['\'', '\\'], $raw)
                : str_replace(['\\"', '\\\\'], ['"', '\\'], $raw);

            if (! array_key_exists($key, $translations)) {
                $missing[] = $file->getRelativePathname().': "'.$key.'"';
            }
        }
    }

    expect($missing)->toBe([]);
});
