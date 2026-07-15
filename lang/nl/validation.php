<?php

declare(strict_types=1);

/*
 * Dutch validation messages.
 *
 * `app.locale` is `nl` and so is `fallback_locale`, but this file did not
 * exist — so Laravel served every validation message from the framework's own
 * English file. On a Dutch site, sellers were told "The photos field is
 * required" and "The email field must be a valid email address". Yvan reported
 * one of those as a bug, which is exactly right: it reads like a broken page.
 *
 * Only the rules this app actually uses are translated. Anything missing falls
 * back to the framework's English, same as before — so this can never be worse
 * than what it replaces, and it can grow as new rules get used.
 *
 * `:attribute` is filled from the `attributes` array at the bottom; without an
 * entry there Laravel prints the raw field name (`region_postcode`), which is
 * why the fields users actually see are named.
 */

return [
    'accepted' => ':Attribute moet geaccepteerd worden.',
    'active_url' => ':Attribute is geen geldige URL.',
    'array' => ':Attribute moet een lijst zijn.',
    'boolean' => ':Attribute moet ja of nee zijn.',
    'confirmed' => 'De bevestiging van :attribute komt niet overeen.',
    'date' => ':Attribute is geen geldige datum.',
    'different' => ':Attribute en :other moeten verschillen.',
    'digits' => ':Attribute moet :digits cijfers bevatten.',
    'email' => ':Attribute moet een geldig e-mailadres zijn.',
    'exists' => 'De gekozen :attribute bestaat niet.',
    'file' => ':Attribute moet een bestand zijn.',
    'image' => ':Attribute moet een afbeelding zijn.',
    'in' => 'De gekozen :attribute is ongeldig.',
    'integer' => ':Attribute moet een getal zijn.',
    'mimes' => ':Attribute moet een bestand zijn van het type: :values.',
    'numeric' => ':Attribute moet een getal zijn.',
    'regex' => ':Attribute heeft een ongeldig formaat.',
    'required' => ':Attribute is verplicht.',
    'required_if' => ':Attribute is verplicht als :other :value is.',
    'same' => ':Attribute en :other moeten overeenkomen.',
    'string' => ':Attribute moet tekst zijn.',
    'unique' => ':Attribute is al in gebruik.',
    'uploaded' => 'Het uploaden van :attribute is mislukt.',
    'url' => ':Attribute moet een geldige URL zijn.',

    'between' => [
        'array' => ':Attribute moet tussen :min en :max items bevatten.',
        'file' => ':Attribute moet tussen :min en :max kilobytes zijn.',
        'numeric' => ':Attribute moet tussen :min en :max liggen.',
        'string' => ':Attribute moet tussen :min en :max tekens bevatten.',
    ],
    'max' => [
        'array' => ':Attribute mag niet meer dan :max items bevatten.',
        'file' => ':Attribute mag niet groter zijn dan :max kilobytes.',
        'numeric' => ':Attribute mag niet groter zijn dan :max.',
        'string' => ':Attribute mag niet meer dan :max tekens bevatten.',
    ],
    'min' => [
        'array' => ':Attribute moet minstens :min items bevatten.',
        'file' => ':Attribute moet minstens :min kilobytes zijn.',
        'numeric' => ':Attribute moet minstens :min zijn.',
        'string' => ':Attribute moet minstens :min tekens bevatten.',
    ],
    'size' => [
        'array' => ':Attribute moet :size items bevatten.',
        'file' => ':Attribute moet :size kilobytes zijn.',
        'numeric' => ':Attribute moet :size zijn.',
        'string' => ':Attribute moet :size tekens bevatten.',
    ],

    /*
     * Without these, Laravel prints the property name verbatim —
     * "region_postcode is verplicht" reads like a stack trace, not a form.
     */
    'attributes' => [
        'category_id' => 'categorie',
        'condition' => 'staat',
        'description' => 'beschrijving',
        'email' => 'e-mailadres',
        'invite_code' => 'uitnodigingscode',
        'password' => 'wachtwoord',
        'password_confirmation' => 'wachtwoordbevestiging',
        'photos' => 'foto\'s',
        'price_cents' => 'prijs',
        'region_postcode' => 'postcode',
        'title' => 'titel',
        'username' => 'gebruikersnaam',
        'waitlist_email' => 'e-mailadres',
    ],
];
