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
 * Only string-literal arguments are checked: a call like __($status) can't
 * be resolved without executing the code, so those are skipped rather than
 * guessed at. As of writing, the only such call in the app is __($status)
 * in ResetPassword.php, resolved against Laravel's line-based `passwords`
 * namespace rather than lang/en.json.
 */

/**
 * Elke letterlijke __() / trans_choice()-sleutel in views en app-code,
 * met per sleutel het bestand waar hij vandaan komt.
 *
 * @return array<string, string>
 */
function literalTranslationKeys(): array
{
    $pattern = '/(__|trans_choice)\(\s*([\'"])((?:\\\\.|(?!\2).)*)\2/s';

    $files = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
        ->merge(collect(File::allFiles(app_path()))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.php')));

    $keys = [];

    foreach ($files as $file) {
        if (! preg_match_all($pattern, $file->getContents(), $matches, PREG_SET_ORDER)) {
            continue;
        }

        foreach ($matches as [, , $quote, $raw]) {
            $key = $quote === "'"
                ? str_replace(['\\\'', '\\\\'], ['\'', '\\'], $raw)
                : str_replace(['\\"', '\\\\'], ['"', '\\'], $raw);

            $keys[$key] = $file->getRelativePathname();
        }
    }

    return $keys;
}

/** @return array<string, string> */
function englishTranslations(): array
{
    return json_decode(
        (string) file_get_contents(lang_path('en.json')),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );
}

it('has an English translation for every literal __() and trans_choice() key used in the app', function () {
    $translations = englishTranslations();

    $missing = [];
    foreach (literalTranslationKeys() as $key => $file) {
        if (! array_key_exists($key, $translations)) {
            $missing[] = $file.': "'.$key.'"';
        }
    }

    expect($missing)->toBe([]);
});

/*
 * De andere kant op. Verwijder je een scherm of een veld, dan blijft de
 * vertaling achter en gaat de volgende lezer ervan uit dat de tekst nog
 * ergens gebruikt wordt. Dat gebeurde bij het gebruikersnaamveld van de
 * koper: twee sleutels bleven staan nadat het veld eruit was.
 *
 * Dit is opruimwerk, geen bug — een Engelse bezoeker ziet er niets van.
 * Maar het is gratis te bewaken zolang alle sleutels letterlijk zijn.
 */
it('has no leftover translations for keys the app no longer uses', function () {
    $used = literalTranslationKeys();

    $dead = array_values(array_filter(
        array_keys(englishTranslations()),
        fn (string $key) => ! array_key_exists($key, $used),
    ));

    expect($dead)->toBe([]);
});
