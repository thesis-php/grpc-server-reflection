<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Reflection;

use Grpc\Reflection\V1;
use Grpc\Reflection\V1alpha;
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
    $reflectionServer = createReflectionServer(
        $server,
        $serviceInfoProvider,
        $descriptorInfoProvider,
    );

    $server->register(...new V1\ServerReflectionServerRegistry($reflectionServer)->services());
}

/**
 * @api
 * @throws ServerRunning
 */
function registerV1Alpha(
    ServiceRegistrar $server,
    ?ServiceInfoProvider $serviceInfoProvider = null,
    ?DescriptorInfoProvider $descriptorInfoProvider = null,
): void {
    $reflectionServer = createReflectionServer(
        $server,
        $serviceInfoProvider,
        $descriptorInfoProvider,
    );

    $server->register(...new V1\ServerReflectionServerRegistry($reflectionServer)->services());
    $server->register(...new V1alpha\ServerReflectionServerRegistry(new ServerV1Alpha($reflectionServer))->services());
}

/**
 * @internal
 */
function createReflectionServer(
    ServiceRegistrar $server,
    ?ServiceInfoProvider $serviceInfoProvider = null,
    ?DescriptorInfoProvider $descriptorInfoProvider = null,
): Server {
    return new Server(
        serviceInfoProvider: $serviceInfoProvider ?? new ServiceInfoProvider\FromServerRegistryServiceInfoProvider($server),
        descriptorInfoProvider: $descriptorInfoProvider ?? new DescriptorInfoProvider\FromPoolDescriptorInfoProvider(),
    );
}
