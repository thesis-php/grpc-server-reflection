<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Reflection;

use Amp\Cancellation;
use Google\Rpc\Code;
use Grpc\Reflection\V1;
use Grpc\Reflection\V1\ServerReflectionRequest;
use Grpc\Reflection\V1\ServerReflectionResponse;
use Thesis\Grpc;
use Thesis\Grpc\Server\BidirectionalStreamChannel;
use Thesis\Protobuf;

/**
 * @api
 */
final readonly class Server implements V1\ServerReflectionServer
{
    public function __construct(
        private ServiceInfoProvider $serviceInfoProvider,
        private DescriptorInfoProvider $descriptorInfoProvider,
    ) {}

    #[\Override]
    public function serverReflectionInfo(
        BidirectionalStreamChannel $stream,
        Grpc\Metadata $md,
        Cancellation $cancellation,
    ): void {
        /** @var Protobuf\Map<string, true> $sent */
        $sent = new Protobuf\Map();

        foreach ($stream as $request) {
            $message = $request->messageRequest;

            $response = new ServerReflectionResponse(
                validHost: $request->host,
                originalRequest: $request,
                messageResponse: match (true) {
                    $message instanceof ServerReflectionRequest\MessageRequestListServices => new ServerReflectionResponse\MessageResponseListServicesResponse(
                        new V1\ListServiceResponse($this->listServices()),
                    ),
                    $message instanceof ServerReflectionRequest\MessageRequestFileByFilename,
                    $message instanceof ServerReflectionRequest\MessageRequestFileContainingSymbol => $this->descriptorResponse(
                        $message,
                        $sent,
                    ),
                    default => new ServerReflectionResponse\MessageResponseErrorResponse(
                        new V1\ErrorResponse(Code::UNIMPLEMENTED->value, 'extensions are not implemented'),
                    ),
                },
            );

            $stream->send($response);
        }

        $stream->close();
    }

    /**
     * @param Protobuf\Map<string, true> $sent
     */
    private function descriptorResponse(
        ServerReflectionRequest\MessageRequestFileByFilename|ServerReflectionRequest\MessageRequestFileContainingSymbol $request,
        Protobuf\Map $sent,
    ): ServerReflectionResponse\MessageResponse {
        if ($request instanceof ServerReflectionRequest\MessageRequestFileByFilename) {
            $filename = $request->fileByFilename;

            if ($filename === '') {
                return new ServerReflectionResponse\MessageResponseErrorResponse(
                    new V1\ErrorResponse(Code::INVALID_ARGUMENT->value, 'filename is empty'),
                );
            }

            $file = $this->descriptorInfoProvider->findByName($filename);
        } else {
            $symbol = $request->fileContainingSymbol;

            if ($symbol === '') {
                return new ServerReflectionResponse\MessageResponseErrorResponse(
                    new V1\ErrorResponse(Code::INVALID_ARGUMENT->value, 'symbol is empty'),
                );
            }

            $file = $this->descriptorInfoProvider->findBySymbol($symbol);
        }

        if ($file === null) {
            return new ServerReflectionResponse\MessageResponseErrorResponse(
                new V1\ErrorResponse(Code::NOT_FOUND->value, 'file not found'),
            );
        }

        return new ServerReflectionResponse\MessageResponseFileDescriptorResponse(
            new V1\FileDescriptorResponse($this->fileWithDependencies(
                $file,
                $sent,
            )),
        );
    }

    /**
     * @param Protobuf\Map<string, true> $sent
     * @return list<string>
     */
    private function fileWithDependencies(
        DescriptorInfoProvider\File $file,
        Protobuf\Map $sent,
    ): array {
        $descriptors = [];

        /** @var \SplQueue<DescriptorInfoProvider\File> $queue */
        $queue = new \SplQueue();
        $queue->enqueue($file);

        while (!$queue->isEmpty()) {
            $fd = $queue->dequeue();

            if ($descriptors === [] || !isset($sent[$fd->name])) {
                $sent[$fd->name] = true;
                $descriptors[] = $fd->bytes;
            }

            foreach ($fd->imports as $import) {
                $dependency = $this->descriptorInfoProvider->findByName($import);
                if ($dependency !== null) {
                    $queue->enqueue($dependency);
                }
            }
        }

        return $descriptors;
    }

    /**
     * @return list<V1\ServiceResponse>
     */
    private function listServices(): array
    {
        $services = $this->serviceInfoProvider->services();

        usort($services, \strcmp(...));

        return array_map(
            static fn(string $service) => new V1\ServiceResponse($service),
            $services,
        );
    }
}
