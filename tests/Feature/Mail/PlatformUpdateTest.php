<?php

declare(strict_types=1);

use App\Mail\PlatformUpdateMail;
use App\Models\MailSubscription;
use App\Models\User;
use App\Services\Profile\AccountRemovalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    Mail::fake();
    Storage::fake('local');
    Storage::disk('local')->put('update.md', "# Wat er is gebeurd\n\nFoto's kun je nu ordenen.");
    config()->set('cloudmarktplaats.features.mail_list', true);
});

it('mails the update to everyone who asked for updates', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md')->assertExitCode(0);

    Mail::assertQueued(PlatformUpdateMail::class);
});

it('leaves the offers-only subscribers alone', function () {
    MailSubscription::factory()->create(['wants_updates' => false, 'wants_offers' => true]);

    $this->artisan('mail:update update.md');

    Mail::assertNothingQueued();
});

/*
 * De rem staat in code en niet in een voornemen. Dit platform verkoopt dat elke
 * claim in code te controleren is, dus "ik ga niet spammen" hoort hier te staan
 * en niet alleen in een gesprek.
 *
 * Elke remtest hieronder stuurt eerst een echte editie en reist daarna in de
 * tijd. Dat is met opzet: de test zegt niets over wáár de datum staat, alleen
 * dat er iets uitging. Verhuist die datum ooit naar een andere plek, dan blijft
 * de test het gedrag bewaken in plaats van de plek.
 */
it('refuses to send again within 30 days', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md')->assertExitCode(0);
    $this->travel(10)->days();

    $this->artisan('mail:update update.md')->assertExitCode(1);

    Mail::assertQueuedCount(1);
});

it('sends again once 30 days have passed', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md')->assertExitCode(0);
    $this->travel(31)->days();

    $this->artisan('mail:update update.md')->assertExitCode(0);

    Mail::assertQueuedCount(2);
});

/*
 * De grens zelf, in twee richtingen. Zonder deze twee is `<=` versus `<` een
 * vrije keuze: het verslag claimt dat precies 30 dagen mag, en dan hoort die
 * seconde ervoor en erna in een test te staan. De klok gaat eerst naar een hele
 * seconde, want de kolom bewaart geen microseconden en zou anders afronden.
 */
it('allows the next edition at exactly 30 days', function () {
    $this->travelTo(now()->startOfSecond());
    MailSubscription::factory()->create(['wants_updates' => true]);
    $this->artisan('mail:update update.md')->assertExitCode(0);

    $this->travelTo(now()->addDays(30));

    $this->artisan('mail:update update.md')->assertExitCode(0);

    Mail::assertQueuedCount(2);
});

it('refuses the next edition 1 second before 30 days', function () {
    $this->travelTo(now()->startOfSecond());
    MailSubscription::factory()->create(['wants_updates' => true]);
    $this->artisan('mail:update update.md')->assertExitCode(0);

    $this->travelTo(now()->addDays(30)->subSecond());

    $this->artisan('mail:update update.md')->assertExitCode(1);

    Mail::assertQueuedCount(1);
});

it('sends nothing on a dry run', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md --dry-run')->assertExitCode(0);

    Mail::assertNothingQueued();
});

/* Hoeveel dagen er nog te gaan zijn, want anders is de rem een dichte deur. */
it('says how many days are left', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);
    $this->artisan('mail:update update.md')->assertExitCode(0);
    $this->travel(10)->days();

    $this->artisan('mail:update update.md')->expectsOutputToContain('20 dagen');
});

/*
 * Een proefdraai verstuurt niets en stempelt niets, dus er is geen reden hem te
 * weigeren. Stond de rem ervóór, dan moest je --force typen om de volgende
 * editie na te lezen, en dat is precies de vlag die nooit routine mag worden.
 */
it('runs a dry run while the brake is still shut', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);
    $this->artisan('mail:update update.md')->assertExitCode(0);
    $this->travel(10)->days();

    $this->artisan('mail:update update.md --dry-run')
        ->expectsOutputToContain('20 dagen')
        ->assertExitCode(0);

    Mail::assertQueuedCount(1);
});

/*
 * De rem geldt per editie en niet per ontvanger: 1 verse abonnee opent hem niet.
 * Anders zou de nieuwsbrief van vorige week alsnog uitgaan naar wie zich sinds
 * dinsdag aanmeldde, en dan is de grens geen grens meer maar een gemiddelde.
 * Hij wacht op de volgende editie, en die komt vanzelf.
 */
it('makes a fresh subscriber wait for the next edition', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);
    $this->artisan('mail:update update.md')->assertExitCode(0);
    $this->travel(10)->days();
    MailSubscription::factory()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md')->assertExitCode(1);

    Mail::assertQueuedCount(1);
});

/*
 * De vier aanvallen van de review. Ze delen 1 vorm: de editie ging uit, daarna
 * verdwijnen de rijen die eraan meededen, en dan komt er iemand nieuw bij. Leest
 * de rem uit de abonneetabel, dan opent hij zichzelf en gaat er binnen 30 dagen
 * een tweede mail uit. De datum van een editie hoort dus niet aan een abonnee te
 * hangen: wie er op de lijst staat is een andere vraag dan wat er verstuurd is.
 */
it('keeps the brake shut after the stamped row is deleted', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);
    $this->artisan('mail:update update.md')->assertExitCode(0);

    MailSubscription::query()->delete();
    MailSubscription::factory()->create(['wants_updates' => true]);
    $this->travel(10)->days();

    $this->artisan('mail:update update.md')->assertExitCode(1);

    Mail::assertQueuedCount(1);
});

it('keeps the brake shut when a member deletes their account', function () {
    $user = User::factory()->create();
    MailSubscription::factory()->create(['user_id' => $user->id, 'wants_updates' => true]);
    $this->artisan('mail:update update.md')->assertExitCode(0);

    // De inschrijving hangt met ON DELETE CASCADE aan het account, dus dit wist
    // de rij hard, niet zacht.
    app(AccountRemovalService::class)->remove($user);
    MailSubscription::factory()->create(['wants_updates' => true]);
    $this->travel(10)->days();

    $this->artisan('mail:update update.md')->assertExitCode(1);

    Mail::assertQueuedCount(1);
});

it('keeps the brake shut when someone deletes their account and signs up again', function () {
    $user = User::factory()->create(['email' => 'lid@example.test']);
    MailSubscription::factory()->create([
        'user_id' => $user->id, 'email' => 'lid@example.test', 'wants_updates' => true,
    ]);
    $this->artisan('mail:update update.md')->assertExitCode(0);

    app(AccountRemovalService::class)->remove($user);
    MailSubscription::factory()->create(['email' => 'lid@example.test', 'wants_updates' => true]);
    $this->travel(3)->days();

    $this->artisan('mail:update update.md')->assertExitCode(1);

    Mail::assertQueuedCount(1);
});

it('keeps the brake shut when the list is emptied and a fresh subscriber arrives', function () {
    MailSubscription::factory()->count(3)->create(['wants_updates' => true]);
    $this->artisan('mail:update update.md')->assertExitCode(0);

    DB::table('mail_subscriptions')->delete();
    MailSubscription::factory()->create(['wants_updates' => true]);
    $this->travel(20)->days();

    $this->artisan('mail:update update.md')->assertExitCode(1);

    Mail::assertQueuedCount(3);
});

/*
 * Afmelden wist de rij niet, maar het antwoord hoort hetzelfde te zijn: de
 * editie ging uit, en dat feit verandert niet doordat iemand weg is. Zou de rem
 * alleen naar de huidige ontvangers kijken, dan opent een lijst die zich massaal
 * afmeldt de deur voor een tweede mail in dezelfde week.
 */
it('keeps the brake shut when everyone has since unsubscribed', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);
    $this->artisan('mail:update update.md')->assertExitCode(0);

    MailSubscription::query()->update([
        'wants_updates' => false, 'wants_offers' => false, 'unsubscribed_at' => now(),
    ]);
    MailSubscription::factory()->create(['wants_updates' => true]);
    $this->travel(10)->days();

    $this->artisan('mail:update update.md')->assertExitCode(1);

    Mail::assertQueuedCount(1);
});

/*
 * --force is er voor het geval er echt iets misgaat en er een correctie uit
 * moet, niet voor gewone nieuwsbrieven. Wie hem gebruikt hoort te lezen hoeveel
 * mensen kort geleden al iets kregen.
 */
it('sends within 30 days when forced, and says how many were mailed recently', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);
    $this->artisan('mail:update update.md')->assertExitCode(0);
    $this->travel(10)->days();

    $this->artisan('mail:update update.md --force')
        ->expectsOutputToContain('afgelopen 30 dagen')
        ->assertExitCode(0);

    Mail::assertQueuedCount(2);
});

/* De vlag is de noodrem. Dit commando draait met de hand, dus een uitgezette
 * vlag is hier wél een mislukte opdracht: je vroeg om te versturen en er ging
 * niets uit. Vandaar exitcode 1, anders dan bij de geplande aanbodmail. */
it('sends nothing while the feature flag is off', function () {
    config()->set('cloudmarktplaats.features.mail_list', false);
    MailSubscription::factory()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md')->assertExitCode(1);

    Mail::assertNothingQueued();
});

/*
 * Het hele pad in de melding, niet alleen de bestandsnaam. De local-disk is
 * storage/app/private en dat is nergens aan af te lezen; wie het commando draait
 * hoort te weten waar hij het bestand had moeten neerzetten.
 */
it('stops on a file that is not there, and names the path', function () {
    // Eén stuk tekst, geen twee: twee verwachtingen op dezelfde regel kan
    // Mockery niet los van elkaar afvinken en dan slaagt de test op de helft.
    $this->artisan('mail:update bestaatniet.md')
        ->expectsOutputToContain('niet gevonden: '.Storage::disk('local')->path('bestaatniet.md'))
        ->assertExitCode(1);

    Mail::assertNothingQueued();
});

it('stops on an empty file', function () {
    Storage::disk('local')->put('leeg.md', "\n   \n");

    $this->artisan('mail:update leeg.md')
        ->expectsOutputToContain('leeg')
        ->assertExitCode(1);

    Mail::assertNothingQueued();
});

it('never mails an address that has not confirmed', function () {
    MailSubscription::factory()->unconfirmed()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md');

    Mail::assertNothingQueued();
});

/*
 * Een geparkeerde wijziging laat een levend `confirm_token` op een bevestigde
 * rij staan. Dat is geen "nog niet bevestigd" en mag de mail niet tegenhouden.
 */
it('still mails a confirmed row that carries a pending change', function () {
    $sub = MailSubscription::factory()->create(['wants_updates' => true]);
    $sub->forceFill([
        'confirm_token' => Str::random(48),
        'pending_changes' => ['wants_updates' => false],
    ])->save();

    $this->artisan('mail:update update.md');

    Mail::assertQueued(PlatformUpdateMail::class);
});

/*
 * Stempelen mag de klok van de rij niet vooruitzetten. `updated_at` zegt hier
 * wanneer de vóórkeuren wijzigden, en een mail versturen is geen wijziging van
 * de voorkeuren. Beide metingen gaan langs `DB::table()`: Eloquent zou zijn
 * eigen zojuist geschreven waarde teruggeven en dat is precies de fout die we
 * zoeken.
 */
it('stamps the send without touching updated_at', function () {
    $sub = MailSubscription::factory()->create(['wants_updates' => true]);
    // Een week terug, anders valt een modelupdate binnen dezelfde seconde en
    // ziet de vergelijking het verschil niet.
    DB::table('mail_subscriptions')->where('id', $sub->id)->update(['updated_at' => now()->subWeek()]);
    $voor = DB::table('mail_subscriptions')->where('id', $sub->id)->value('updated_at');

    $this->artisan('mail:update update.md');

    $na = DB::table('mail_subscriptions')->where('id', $sub->id)->first();
    expect($na?->updated_at)->toBe($voor)
        ->and($na?->updates_sent_at)->toBeGreaterThan($voor);
});

/*
 * Het logboek van de edities: wanneer ging er iets uit, naar hoeveel mensen, uit
 * welk bestand. Het aantal en het bestand zitten er niet in om te meten maar om
 * achteraf te kunnen nalezen wat er precies verstuurd is; zonder die twee is een
 * regel in dit logboek een datum zonder inhoud.
 */
it('logs the edition with the recipient count and the file', function () {
    MailSubscription::factory()->count(2)->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md')->assertExitCode(0);

    $editie = DB::table('mail_editions')->first();
    expect(DB::table('mail_editions')->count())->toBe(1)
        ->and($editie?->recipient_count)->toBe(2)
        ->and($editie?->source_file)->toBe('update.md')
        ->and($editie?->sent_at)->not->toBeNull();
});

it('logs no edition on a dry run', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md --dry-run')->assertExitCode(0);

    expect(DB::table('mail_editions')->count())->toBe(0);
});

it('leaves the stamp alone on a dry run', function () {
    $sub = MailSubscription::factory()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md --dry-run')
        ->expectsOutputToContain('1 ontvanger')
        ->expectsOutputToContain('Wat er is gebeurd');

    expect(DB::table('mail_subscriptions')->where('id', $sub->id)->value('updates_sent_at'))->toBeNull();
});

/*
 * Een echte ronde kan in een logbestand landen. E-mailadressen horen daar niet
 * in: dat is een lijst persoonsgegevens die niemand daar zocht. Bij --dry-run
 * kijkt er een mens mee die juist wil zien wie er aan de beurt is.
 */
it('keeps addresses out of the output of a real run', function () {
    MailSubscription::factory()->create(['email' => 'abonnee@example.test', 'wants_updates' => true]);

    // De proefdraai eerst: die stempelt niet, dus de echte ronde erna mag nog.
    $this->artisan('mail:update update.md --dry-run')->expectsOutputToContain('abonnee@example.test');
    $this->artisan('mail:update update.md')->doesntExpectOutputToContain('abonnee@example.test');
});

/* Art. 11.7 lid 4 Tw: elke mail draagt een werkende afmeldweg. `wat=updates`,
 * want deze mail gaat niet over het aanbod. */
it('carries the unsubscribe link and the one-click headers', function () {
    $sub = MailSubscription::factory()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md');

    Mail::assertQueued(PlatformUpdateMail::class, function (PlatformUpdateMail $mail) use ($sub) {
        $mail->assertSeeInHtml(route('mail.unsubscribe', ['token' => $sub->unsubscribe_token, 'wat' => 'updates']), false);
        $headers = $mail->headers()->text;

        return $headers['List-Unsubscribe'] === '<'.route('mail.unsubscribe', $sub->unsubscribe_token).'>'
            && $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
    });
});

/* Art. 3:15d BW: wie de afzender is, hoort in de mail zelf te staan. */
it('names the sender with address and chamber of commerce number', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md');

    Mail::assertQueued(PlatformUpdateMail::class, function (PlatformUpdateMail $mail) {
        $mail->assertSeeInHtml('Aldewereld Consultancy');
        $mail->assertSeeInHtml('Nieuwe Hemweg 26');
        $mail->assertSeeInHtml('1013 CX Amsterdam');
        $mail->assertSeeInHtml('61862533');

        return true;
    });
});

it('renders the markdown and takes the first heading as the subject', function () {
    MailSubscription::factory()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md');

    Mail::assertQueued(PlatformUpdateMail::class, function (PlatformUpdateMail $mail) {
        $mail->assertSeeInHtml('<h1>Wat er is gebeurd</h1>', false);

        return $mail->envelope()->subject === 'Wat er is gebeurd';
    });
});

/*
 * De tekst komt uit een bestand, en een bestand is geen bladetemplate. Ruwe
 * HTML gaat eruit: dat scheelt een mail die per client uit elkaar valt, en het
 * houdt de weg dicht als er ooit iets anders dan Nicks eigen tekst in belandt.
 */
it('strips raw html from the markdown', function () {
    Storage::disk('local')->put('update.md', "# Kop\n\n<script>alert(1)</script>\n\nGewone tekst.");
    MailSubscription::factory()->create(['wants_updates' => true]);

    $this->artisan('mail:update update.md');

    Mail::assertQueued(PlatformUpdateMail::class, function (PlatformUpdateMail $mail) {
        $mail->assertDontSeeInHtml('<script>', false);
        $mail->assertSeeInHtml('Gewone tekst.');

        return true;
    });
});
