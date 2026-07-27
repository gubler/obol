<?php

// ABOUTME: Invokable controller for the product tour: a dedicated dashboard staged with a sample subscription.
// ABOUTME: Renders the real homepage template in demo mode so every tour step has a target; nothing persists.

declare(strict_types=1);

namespace App\Controller\Tour;

use App\Controller\AbstractBaseController;
use App\Enum\SubscriptionSort;
use App\Tour\SampleDashboardFactory;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TourController extends AbstractBaseController
{
    public function __construct(private readonly SampleDashboardFactory $sampleDashboardFactory)
    {
    }

    #[Route(path: '/app/tour', name: 'subscription_tour', methods: ['GET'])]
    public function __invoke(): Response
    {
        $sample = $this->sampleDashboardFactory->forOwner($this->currentUser());

        // The tour always presents the same guided demo, so the listing controls render at their
        // defaults - grouped tiles, active only, default sort - rather than reading query toggles.
        return $this->render(view: 'subscription/index.html.twig', parameters: [
            'demo' => true,
            'listing' => $sample->listing,
            'capstone' => $sample->capstone,
            'capstoneMode' => 'totals',
            'view' => 'tiles',
            'grouped' => true,
            'includeArchived' => false,
            'sort' => SubscriptionSort::fromQuery(''),
            'sortOptions' => SubscriptionSort::cases(),
        ]);
    }
}
