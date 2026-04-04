<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Reflection\Internal;

use Grpc\Reflection\V1;
use Grpc\Reflection\V1alpha;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server\BidirectionalStreamChannel;
use Thesis\Grpc\ServerStream;

/**
 * @internal
 * @template-implements ServerStream<V1\ServerReflectionRequest, V1\ServerReflectionResponse>
 */
final class AdaptV1AlphaBidirectionalStreamChannel implements ServerStream
{
    public Metadata $trailers { get => new Metadata(); }

    public Metadata $headers { get => new Metadata(); }

    /**
     * @param BidirectionalStreamChannel<V1alpha\ServerReflectionRequest, V1alpha\ServerReflectionResponse> $channel
     */
    public function __construct(
        private readonly BidirectionalStreamChannel $channel,
    ) {}

    #[\Override]
    public function send(object $message): void
    {
        $this->channel->send(self::toV1AlphaResponse($message));
    }

    #[\Override]
    public function receive(): object
    {
        $message = $this->channel->receive();

        return self::toV1Request($message);
    }

    #[\Override]
    public function close(): void
    {
        $this->channel->close();
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        foreach ($this->channel as $message) {
            yield self::toV1Request($message);
        }
    }

    private static function toV1Request(V1alpha\ServerReflectionRequest $request): V1\ServerReflectionRequest
    {
        $messageRequest = $request->messageRequest;

        return new V1\ServerReflectionRequest(
            host: $request->host,
            messageRequest: match (true) {
                $messageRequest instanceof V1alpha\ServerReflectionRequest\MessageRequestListServices => new V1\ServerReflectionRequest\MessageRequestListServices(
                    $messageRequest->listServices,
                ),
                $messageRequest instanceof V1alpha\ServerReflectionRequest\MessageRequestFileByFilename => new V1\ServerReflectionRequest\MessageRequestFileByFilename(
                    $messageRequest->fileByFilename,
                ),
                $messageRequest instanceof V1alpha\ServerReflectionRequest\MessageRequestFileContainingSymbol => new V1\ServerReflectionRequest\MessageRequestFileContainingSymbol(
                    $messageRequest->fileContainingSymbol,
                ),
                $messageRequest instanceof V1alpha\ServerReflectionRequest\MessageRequestFileContainingExtension => new V1\ServerReflectionRequest\MessageRequestFileContainingExtension(
                    $messageRequest->fileContainingExtension !== null
                        ? new V1\ExtensionRequest($messageRequest->fileContainingExtension->containingType, $messageRequest->fileContainingExtension->extensionNumber)
                        : null,
                ),
                $messageRequest instanceof V1alpha\ServerReflectionRequest\MessageRequestAllExtensionNumbersOfType => new V1\ServerReflectionRequest\MessageRequestAllExtensionNumbersOfType(
                    $messageRequest->allExtensionNumbersOfType,
                ),
                default => null,
            },
        );
    }

    private static function toV1AlphaResponse(V1\ServerReflectionResponse $response): V1alpha\ServerReflectionResponse
    {
        $messageRequest = $response->originalRequest?->messageRequest;
        $messageResponse = $response->messageResponse;

        return new V1alpha\ServerReflectionResponse(
            validHost: $response->validHost,
            originalRequest: new V1alpha\ServerReflectionRequest(
                host: $response->originalRequest->host ?? '',
                messageRequest: match (true) {
                    $messageRequest instanceof V1\ServerReflectionRequest\MessageRequestListServices => new V1alpha\ServerReflectionRequest\MessageRequestListServices(
                        $messageRequest->listServices,
                    ),
                    $messageRequest instanceof V1\ServerReflectionRequest\MessageRequestFileByFilename => new V1alpha\ServerReflectionRequest\MessageRequestFileByFilename(
                        $messageRequest->fileByFilename,
                    ),
                    $messageRequest instanceof V1\ServerReflectionRequest\MessageRequestFileContainingSymbol => new V1alpha\ServerReflectionRequest\MessageRequestFileContainingSymbol(
                        $messageRequest->fileContainingSymbol,
                    ),
                    $messageRequest instanceof V1\ServerReflectionRequest\MessageRequestFileContainingExtension => new V1alpha\ServerReflectionRequest\MessageRequestFileContainingExtension(
                        $messageRequest->fileContainingExtension !== null
                            ? new V1alpha\ExtensionRequest(
                                $messageRequest->fileContainingExtension->containingType,
                                $messageRequest->fileContainingExtension->extensionNumber,
                            )
                            : null,
                    ),
                    $messageRequest instanceof V1\ServerReflectionRequest\MessageRequestAllExtensionNumbersOfType => new V1alpha\ServerReflectionRequest\MessageRequestAllExtensionNumbersOfType(
                        $messageRequest->allExtensionNumbersOfType,
                    ),
                    default => null,
                },
            ),
            messageResponse: match (true) {
                $messageResponse instanceof V1\ServerReflectionResponse\MessageResponseFileDescriptorResponse => new V1alpha\ServerReflectionResponse\MessageResponseFileDescriptorResponse(
                    $messageResponse->fileDescriptorResponse !== null
                        ? new V1alpha\FileDescriptorResponse($messageResponse->fileDescriptorResponse->fileDescriptorProto)
                        : null,
                ),
                $messageResponse instanceof V1\ServerReflectionResponse\MessageResponseAllExtensionNumbersResponse => new V1alpha\ServerReflectionResponse\MessageResponseAllExtensionNumbersResponse(
                    $messageResponse->allExtensionNumbersResponse !== null
                        ? new V1alpha\ExtensionNumberResponse(
                            $messageResponse->allExtensionNumbersResponse->baseTypeName,
                            $messageResponse->allExtensionNumbersResponse->extensionNumber,
                        )
                        : null,
                ),
                $messageResponse instanceof V1\ServerReflectionResponse\MessageResponseListServicesResponse => new V1alpha\ServerReflectionResponse\MessageResponseListServicesResponse(
                    $messageResponse->listServicesResponse !== null
                        ? new V1alpha\ListServiceResponse(array_map(
                            static fn(V1\ServiceResponse $service) => new V1alpha\ServiceResponse($service->name),
                            $messageResponse->listServicesResponse->service,
                        ))
                        : null,
                ),
                $messageResponse instanceof V1\ServerReflectionResponse\MessageResponseErrorResponse => new V1alpha\ServerReflectionResponse\MessageResponseErrorResponse(
                    $messageResponse->errorResponse !== null
                        ? new V1alpha\ErrorResponse(
                            $messageResponse->errorResponse->errorCode,
                            $messageResponse->errorResponse->errorMessage,
                        )
                        : null,
                ),
                default => null,
            },
        );
    }
}
