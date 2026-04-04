<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Reflection;

use Grpc\Reflection\V1\ServerReflectionServerRegistry;
use Thesis\Grpc\Server\ServerRunning;
use Thesis\Grpc\ServiceRegistrar;

/**
 * @api
 * @throws ServerRunning
 */
function registerV1(
    ServiceRegistrar $server,
    ?ServiceInfoProvider $serviceInfoProvider = null,
    ?DescriptorInfoProvider $descriptorInfoProvider = null,
): void {
    $reflection = new Server(
        serviceInfoProvider: $serviceInfoProvider ?? new ServiceInfoProvider\FromServerRegistryServiceInfoProvider($server),
        descriptorInfoProvider: $descriptorInfoProvider ?? new DescriptorInfoProvider\FromPoolDescriptorInfoProvider(),
    );

    $server->register(...new ServerReflectionServerRegistry($reflection)->services());
}
