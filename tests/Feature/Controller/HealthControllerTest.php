<?php

// ABOUTME: Feature test for GET /health - the endpoint the container healthcheck probes.
// ABOUTME: Public (no firewall), 200 when the application can serve, 503 when the database is gone.

declare(strict_types=1);

namespace App\Tests\Feature\Controller;

use App\Message\Query\System\CheckDatabaseIsReachableQuery;
use App\Message\Query\System\CheckDatabaseIsReachableRunner;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class HealthControllerTest extends WebTestCase
{
    /**
     * Anonymous, because the container healthcheck cannot authenticate. The whole application is
     * behind a login wall (ADR-0014), so this asserts the route is genuinely exempted rather than
     * quietly redirecting to the login page - a 302 would read as "reachable" to a probe that only
     * checks for a response.
     */
    public function testItAnswersAnonymously(): void
    {
        $client = self::createClient();

        $client->request(method: Request::METHOD_GET, uri: '/health');

        self::assertResponseIsSuccessful();
        self::assertResponseStatusCodeSame(expectedCode: Response::HTTP_OK);
    }

    public function testItSaysNothingAboutTheApplication(): void
    {
        $client = self::createClient();

        $client->request(method: Request::METHOD_GET, uri: '/health');

        // The endpoint is public, so the body is a bare token rather than anything describing the
        // stack, its versions, or why a check failed. The status code carries the whole answer.
        self::assertSame('ok', $client->getResponse()->getContent());
        self::assertResponseHeaderSame(headerName: 'Content-Type', expectedValue: 'text/plain; charset=UTF-8');
    }

    /**
     * The reason this endpoint exists. A container whose database has gone must report unhealthy:
     * sessions and the application cache are database tables on the request path, so it can no
     * longer serve a real request even though PHP and Caddy are both fine.
     */
    public function testItIsUnavailableWhenTheDatabaseIsUnreachable(): void
    {
        $client = self::createClient();
        // Swapping the runner rather than simulating an outage: the query bus resolves handlers from
        // the container at dispatch, so this exercises the controller's real dispatch path and maps
        // only the answer. What a lost connection does to the runner is covered by its unit test.
        self::getContainer()->set(CheckDatabaseIsReachableRunner::class, new class {
            public function __invoke(CheckDatabaseIsReachableQuery $query): bool
            {
                return false;
            }
        });

        $client->request(method: Request::METHOD_GET, uri: '/health');

        self::assertResponseStatusCodeSame(expectedCode: Response::HTTP_SERVICE_UNAVAILABLE);
    }
}
