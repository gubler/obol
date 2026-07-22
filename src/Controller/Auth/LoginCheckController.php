<?php

// ABOUTME: Magic-link check endpoint. A signed GET renders a "Sign in" interstitial whose POST the
// ABOUTME: login_link authenticator intercepts and consumes; reaching the body means no valid link, so bounce.

declare(strict_types=1);

namespace App\Controller\Auth;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LoginCheckController extends AbstractController
{
    #[Route(path: '/login/check', name: 'app_login_check', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        // A valid link is consumed by the login_link authenticator on POST (check_post_only), so it
        // never reaches this body. A signed GET does reach here: render the interstitial that POSTs the
        // signature back to complete login - a prefetch GET therefore cannot burn a single-use link.
        $hash = $request->query->get('hash');
        $user = $request->query->get('user');
        $expires = $request->query->get('expires');

        if ($request->isMethod(Request::METHOD_GET) && null !== $hash && null !== $user && null !== $expires) {
            return $this->render(view: 'auth/login_link_check.html.twig', parameters: [
                'user' => $user,
                'hash' => $hash,
                'expires' => $expires,
            ]);
        }

        // No signature (a bare hit), or a POST that failed to authenticate: send them back to the login form.
        return $this->redirectToRoute(route: 'app_login');
    }
}
