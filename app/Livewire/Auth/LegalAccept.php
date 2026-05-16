<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\LegalAcceptance;
use App\Models\LegalDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Re-acceptance page surfaced by {@see \App\Http\Middleware\LegalAcceptance}
 * whenever the user has not yet accepted the latest ToS/privacy revision.
 *
 * Lists the *un-accepted* current documents (markdown-rendered) and writes
 * one `legal_acceptances` row per document on submit. Once the user has
 * accepted every outstanding revision they are redirected back to where
 * the middleware caught them (or `/` as a safe fallback).
 *
 * Note: this page is mounted under the `auth` middleware only — applying
 * the `legal` middleware to it would create a redirect loop.
 */
#[Layout('layouts.app')]
class LegalAccept extends Component
{
    /** @var array<int, array{id:int,type:string,version:string,markdown:string}> */
    public array $documents = [];

    public function mount(): void
    {
        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }

        $pending = [];
        foreach (['tos', 'privacy'] as $type) {
            $doc = LegalDocument::current($type, app()->getLocale());
            if ($doc === null) {
                continue;
            }
            $accepted = $user->legalAcceptances()
                ->where('legal_document_id', $doc->id)
                ->exists();
            if ($accepted) {
                continue;
            }
            $pending[] = [
                'id' => $doc->id,
                'type' => (string) $doc->type,
                'version' => (string) $doc->version,
                'markdown' => (string) $doc->markdown_content,
            ];
        }

        // Nothing pending — back to the safe default landing page so the
        // user isn't stranded on an empty acceptance form.
        if ($pending === []) {
            $this->redirect('/');

            return;
        }

        $this->documents = $pending;
    }

    public function accept(): void
    {
        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }

        DB::transaction(function () use ($user): void {
            foreach ($this->documents as $doc) {
                LegalAcceptance::create([
                    'user_id' => $user->id,
                    'legal_document_id' => $doc['id'],
                    'accepted_at' => now(),
                    'ip_hash' => hash('sha256', (string) request()->ip().(string) config('app.key')),
                ]);
            }
        });

        $this->redirect('/');
    }

    public function render(): View
    {
        return view('livewire.auth.legal-accept');
    }
}
