<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LandingSectionController extends AbstractController
{
    #[Route('/experience/craft-roasts', name: 'experience_craft')]
    public function craftRoasts(): Response
    {
        return $this->renderExperience(
            'Craft Roasts',
            'Traceable beans roasted in-house every morning with tasting notes pinned to the wall.',
            [
                'Single-origin lots from Benguet, Kalinga, and Batangas are roasted on site for brightness and clarity.',
                'The roasting team cues the drum every 8–10 minutes to capture sweet caramelization before the first cup.',
                'Live cuppings happen at 9:00 AM so we pour the same batch you will taste during the day.',
            ],
            'Fresh batches leave the drum by 6:30 AM so the team can dial in every pour before the doors open.',
            'Browse today\'s menu',
            'menu',
            'https://images.unsplash.com/photo-1466978913421-dad2ebd01d17?auto=format&fit=crop&w=1500&q=80'
        );
    }

    #[Route('/experience/pastries-bakes', name: 'experience_pastries')]
    public function pastries(): Response
    {
        return $this->renderExperience(
            'Pastries & Bakes',
            'Buttery layers, sourdough rounds, and seasonal tarts arrive from trusted bakers daily.',
            [
                'Flaky croissants, kouign-amann, and danishes are laminated, shaped, and baked each dawn.',
                'Our pastry team collaborates with neighborhood bakers so you have variety beyond the showcase case.',
                'Pair any pastry with a pour-over or hot latte for a classic cafe pairing.',
            ],
            'Brown butter crumb, seasonal preserves, and house-made curd change weekly.',
            'View the weekly pastry list',
            'menu',
            'https://images.unsplash.com/photo-1504754524776-8f4f37790ca0?auto=format&fit=crop&w=1500&q=80'
        );
    }

    #[Route('/experience/gatherings-events', name: 'experience_gatherings')]
    public function gatherings(): Response
    {
        return $this->renderExperience(
            'Gatherings & Events',
            'Book a long table, join acoustic nights, or host a private tasting for your crew.',
            [
                'The loft opens for private tastings, book clubs, and acoustic shows with curated playlists.',
                'Plug-in stations, climate control, and ambient lighting keep your group comfortable.',
                'Staff will guide tasting flights and walk you through pairings so every guest feels at home.',
            ],
            'Ping the team for group reservations, weekend takeovers, or craft workshops.',
            'Reserve the space',
            'contact',
            'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?auto=format&fit=crop&w=1500&q=80'
        );
    }

    #[Route('/experience/brew-bar', name: 'experience_brew')]
    public function brewBar(): Response
    {
        return $this->renderExperience(
            'Brew Bar Rituals',
            'Manual brews, layered cold taps, and tasting flights that honor each bean.',
            [
                'Pour-over flights highlight trace aromas, clarity, and sweetness from different roasts.',
                'Cold brew lab steeps for 16 hours with citrus and cacao notes on tap.',
                'Our baristas teach brewing fundamentals twice a week so you can recreate the ritual at home.',
            ],
            'The brew bar is tucked beside the roastery, so you can watch the roast-to-pour journey in one shot.',
            'Meet the brew bar',
            'contact',
            'https://images.unsplash.com/photo-1470337458703-46ad1756a187?auto=format&fit=crop&w=1500&q=80'
        );
    }

    private function renderExperience(
        string $title,
        string $summary,
        array $details,
        string $sideNote,
        string $ctaLabel,
        string $ctaRoute,
        string $heroImage
    ): Response {
        return $this->render('home/experience_detail.html.twig', [
            'title' => $title,
            'summary' => $summary,
            'details' => $details,
            'sideNote' => $sideNote,
            'ctaLabel' => $ctaLabel,
            'ctaRoute' => $ctaRoute,
            'heroImage' => $heroImage,
        ]);
    }
}
