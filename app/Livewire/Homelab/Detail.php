<?php

declare(strict_types=1);

namespace App\Livewire\Homelab;

use App\Models\HomelabPost;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Publieke homelab-pagina. Spiegel van Listings\Detail: handmatige ulid-lookup
 * met 301-canonicalisatie op de slug, OG-tags via layoutData().
 *
 * Anonimiteit: de bouwer wordt nooit gerenderd. De view krijgt uitsluitend de
 * post en zijn foto's, geen user.
 */
#[Layout('components.layouts.marketing')]
class Detail extends Component
{
    public HomelabPost $post;

    public function mount(string $ulid, string $slug): void
    {
        abort_unless((bool) config('cloudmarktplaats.features.homelab_feed'), 404);

        $post = HomelabPost::query()
            ->where('ulid', $ulid)
            ->where('status', 'published')
            ->first();

        if ($post === null) {
            abort(404);
        }

        if ($slug !== $post->slug) {
            abort(new RedirectResponse("/homelabs/{$post->ulid}-{$post->slug}", 301));
        }

        $this->post = $post;
    }

    /** Titel voor kop en og:title; zonder titel een fragment van de body. */
    public function heading(): string
    {
        return $this->post->title ?: Str::limit($this->post->body, 60);
    }

    public function render(): View
    {
        return view('livewire.homelab.detail')->layoutData([
            'title' => $this->heading().' — Cloudmarktplaats',
            'description' => Str::limit(strip_tags($this->post->body), 150),
            'ogImage' => $this->post->getOgImageUrl(),
            'canonical' => url("/homelabs/{$this->post->ulid}-{$this->post->slug}"),
        ]);
    }
}
