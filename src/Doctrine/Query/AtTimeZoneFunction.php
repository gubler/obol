<?php

// ABOUTME: DQL function AT_TIME_ZONE(value, zone) emitting the Postgres "value AT TIME ZONE zone" operator.
// ABOUTME: Lets owner-scoped finders resolve a user's local date in SQL (payment generation; see ADR-0016).

declare(strict_types=1);

namespace App\Doctrine\Query;

use Doctrine\ORM\Query\AST\Functions\FunctionNode;
use Doctrine\ORM\Query\AST\Node;
use Doctrine\ORM\Query\Parser;
use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query\TokenType;

/**
 * "AT_TIME_ZONE" "(" ArithmeticExpression "," StringPrimary ")".
 *
 * Wraps Postgres' `AT TIME ZONE`. Applied to a `timestamptz` value it yields the wall-clock
 * `timestamp` in the given named zone; applied to a naive `timestamp` it interprets that value as
 * being in the zone. The generation finder binds "now" as a `timestamptz` so the first form applies.
 */
final class AtTimeZoneFunction extends FunctionNode
{
    public Node $value;

    public Node $timezone;

    #[\Override]
    public function parse(Parser $parser): void
    {
        $parser->match(TokenType::T_IDENTIFIER);
        $parser->match(TokenType::T_OPEN_PARENTHESIS);

        $this->value = $parser->ArithmeticExpression();
        $parser->match(TokenType::T_COMMA);
        $this->timezone = $parser->StringPrimary();

        $parser->match(TokenType::T_CLOSE_PARENTHESIS);
    }

    #[\Override]
    public function getSql(SqlWalker $sqlWalker): string
    {
        return \sprintf(
            '(%s AT TIME ZONE %s)',
            $this->value->dispatch($sqlWalker),
            $this->timezone->dispatch($sqlWalker),
        );
    }
}
