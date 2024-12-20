<?php

declare(strict_types=1);

namespace Tests\Tempest\Fixtures\Controllers;

use function Tempest\defer;
use Tempest\Http\Get;
use Tempest\Http\Response;
use Tempest\Http\Responses\Ok;

final class DeferController
{
    public static bool $executed = false;

    #[Get('/defer')]
    public function __invoke(): Response
    {
        defer(function (): void {
            //            ll('defer start');
            //            sleep(2);
            //            ll('defer done');
            self::$executed = true;
        });

        return new Ok('ok');
    }
}
