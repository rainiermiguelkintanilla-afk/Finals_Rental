<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/contact')]
class ContactController extends AbstractController
{
    #[Route('', name: 'app_contact_index', methods: ['GET'])]
    public function index(): Response
    {
        $raw = (string) $this->getParameter('contact.google_form_embed_url');

        return $this->render('contact/index.html.twig', [
            'googleFormEmbedUrl' => self::normalizeGoogleFormEmbedUrl($raw),
        ]);
    }

    /**
     * docs.google.com form URLs should include embedded=true for responsive embed layout.
     * forms.gle short links are passed through unchanged.
     */
    private static function normalizeGoogleFormEmbedUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (!preg_match('#^https?://docs\.google\.com/forms/#', $url)) {
            return $url;
        }

        if (str_contains($url, 'embedded=true')) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'embedded=true';
    }
}
