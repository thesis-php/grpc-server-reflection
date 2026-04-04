<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Reflection;

/**
 * @api
 */
interface ServiceInfoProvider
{
    /**
     * @return list<string>
     */
    public function services(): array;
}
