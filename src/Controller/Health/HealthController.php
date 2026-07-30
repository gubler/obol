<?php

// ABOUTME: Public `GET /health` - the endpoint the container healthcheck probes to decide healthy.
// ABOUTME: 200 when the application can serve a request, 503 when its database has gone.

declare(strict_types=1);

namespace App\Controller\Health;

use App\Controller\AbstractBaseController;
use App\Message\Query\System\CheckDatabaseIsReachableQuery;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController extends AbstractBaseController
{
    /**
     * Answering at all already proves most of what a probe wants to know: Caddy is routing, PHP is
     * executing, the kernel booted, and the service container compiled. What that leaves is the
     * database, which every real request touches - sessions and the application cache are tables
     * (reference/adr/0026) - so a container that cannot reach it throws on everything else while
     * still being perfectly able to answer here.
     *
     * It deliberately stops there. It does not check the schema version, which the entrypoint
     * already refuses to boot against and which cannot drift while the process runs; and it does not
     * reach any external service, because a probe that fails on someone else's outage takes this
     * container out over something restarting it cannot fix. Mail is the case in point: it is queued
     * through the worker, so a mail provider being down is not this container being unhealthy.
     *
     * Outside `/app` because it is infrastructure rather than application surface (ADR-0018), and
     * public because the healthcheck runs inside the container with no session to authenticate.
     * The body is a bare token: anything descriptive here is served to the internet.
     */
    #[Route(path: '/health', name: 'health', methods: ['GET'])]
    public function __invoke(): Response
    {
        $reachable = $this->queryBus->query(query: new CheckDatabaseIsReachableQuery());

        return new Response(
            content: true === $reachable ? 'ok' : 'unavailable',
            status: true === $reachable ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
            headers: ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }
}
