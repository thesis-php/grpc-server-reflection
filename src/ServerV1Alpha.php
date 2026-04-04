<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Reflection;

use Amp\Cancellation;
use Grpc\Reflection\V1alpha;
use Thesis\Grpc;
use Thesis\Grpc\Server\BidirectionalStreamChannel;

/**
 * @api
 */
final readonly class ServerV1Alpha implements V1alpha\ServerReflectionServer
{
    public function __construct(
        private Server $server,
    ) {}

    #[\Override]
    public function serverReflectionInfo(
        BidirectionalStreamChannel $stream,
        Grpc\Metadata $md,
        Cancellation $cancellation,
    ): void {
        $this->server->serverReflectionInfo(
            new BidirectionalStreamChannel(new Internal\AdaptV1AlphaBidirectionalStreamChannel($stream)),
            $md,
            $cancellation,
        );
    }
}
