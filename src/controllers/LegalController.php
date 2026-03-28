<?php

declare(strict_types=1);

final class LegalController
{
    public function showPublicDoc(): void
    {
        $doc = trim((string) ($_GET['doc'] ?? 'terms'));
        if (!in_array($doc, ['terms', 'privacy'], true)) {
            $doc = 'terms';
        }

        $legal = new LegalService();
        $versions = $legal->currentVersions();
        $links = $legal->legalLinks();

        View::render('legal/doc', [
            'doc' => $doc,
            'versions' => $versions,
            'links' => $links,
        ]);
    }
}