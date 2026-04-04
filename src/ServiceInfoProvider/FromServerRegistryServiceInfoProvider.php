<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Reflection\ServiceInfoProvider;

use Thesis\Grpc\Server;
use Thesis\Grpc\Server\Reflection\ServiceInfoProvider;
use Thesis\Grpc\ServiceRegistrar;

/**
 * @api
 */
final readonly class FromServerRegistryServiceInfoProvider implements ServiceInfoProvider
{
    public function __construct(
        private ServiceRegistrar $server,
    ) {}

    #[\Override]
    public function services(): array
    {
        return array_map(
            static fn(Server\Service $service) => $service->name,
            $this->server->services(),
        );
    }
}
