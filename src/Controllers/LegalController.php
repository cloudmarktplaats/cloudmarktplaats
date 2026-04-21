<?php

namespace App\Controllers;

use App\Core\Session;
use App\Models\LegalDocument;
use App\Services\Auth\LegalDocumentService;

class LegalController extends BaseController
{
    private LegalDocumentService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new LegalDocumentService(new LegalDocument());
    }

    public function tos(): void
    {
        $this->renderDocument('tos', 'Algemene Voorwaarden');
    }

    public function privacy(): void
    {
        $this->renderDocument('privacy', 'Privacybeleid');
    }

    public function showAccept(): void
    {
        $userId = Session::userId();
        if ($userId === null) {
            $this->redirect('/auth/login');
            return;
        }

        $language = $this->language();
        $versions = $this->service->currentVersions($language);
        $tos = $this->service->getDocument('tos', $versions['tos'], $language);
        $privacy = $this->service->getDocument('privacy', $versions['privacy'], $language);

        $this->render('legal/accept', [
            'title' => 'Voorwaarden accepteren',
            'tos' => $tos,
            'privacy' => $privacy,
        ]);
    }

    public function accept(): void
    {
        $userId = Session::userId();
        if ($userId === null) {
            $this->redirect('/auth/login');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->showAccept();
            return;
        }

        if (empty($_POST['accept'])) {
            $this->flash('error', 'Je moet de voorwaarden accepteren om door te gaan.');
            $this->redirect('/legal/accept');
            return;
        }

        $this->service->accept($userId, $this->language());
        $returnTo = Session::get('legal_return_to', '/dashboard');
        Session::remove('legal_return_to');

        if (!preg_match('#^/[A-Za-z0-9/_\-]*$#', $returnTo)) {
            $returnTo = '/dashboard';
        }

        $this->flash('success', 'Bedankt voor het accepteren. Welkom bij Cloudmarkplaats!');
        $this->redirect($returnTo);
    }

    private function renderDocument(string $type, string $title): void
    {
        $language = $this->language();
        $version = $this->service->currentVersions($language)[$type];
        if ($version === 0) {
            http_response_code(404);
            $this->render('errors/404', ['title' => 'Niet gevonden']);
            return;
        }

        $doc = $this->service->getDocument($type, $version, $language);
        $this->render('legal/' . $type, [
            'title' => $title,
            'document' => $doc,
        ]);
    }

    private function language(): string
    {
        $requested = $_GET['lang'] ?? 'nl';
        return in_array($requested, ['nl', 'en'], true) ? $requested : 'nl';
    }
}
