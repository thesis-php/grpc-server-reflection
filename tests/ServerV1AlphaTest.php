<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server\Reflection;

use Amp\Cancellation;
use Echos\Api\V1\EchoRequest;
use Echos\Api\V1\EchoResponse;
use Echos\Api\V1\EchoServiceServer;
use Echos\Api\V1\EchoServiceServerRegistry;
use Google\Rpc\Code;
use Grpc\Reflection\V1;
use Grpc\Reflection\V1alpha;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Thesis\Grpc\Client;
use Thesis\Grpc\Metadata;
use Thesis\Grpc\Server;
use Thesis\Protobuf\Registry\Pool;

#[CoversClass(ServerV1Alpha::class)]
final class ServerV1AlphaTest extends TestCase
{
    private Server $server;

    protected function setUp(): void
    {
        $this->server = new Server\Builder()
            ->withServices(new EchoServiceServerRegistry(new class implements EchoServiceServer {
                #[\Override]
                public function echo(EchoRequest $request, Metadata $md, Cancellation $cancellation): EchoResponse
                {
                    return new EchoResponse();
                }
            }))
            ->build();

        registerV1Alpha($this->server);

        $this->server->start();
    }

    protected function tearDown(): void
    {
        $this->server->stop();
    }

    public function testV1AlphaReflectionFlow(): void
    {
        $client = new V1alpha\ServerReflectionClient(new Client\Builder()->build());

        $stream = $client->serverReflectionInfo();

        self::sendExpect(
            $stream,
            new V1alpha\ServerReflectionRequest('localhost:50051', new V1alpha\ServerReflectionRequest\MessageRequestListServices('*')),
            V1alpha\ServerReflectionResponse\MessageResponseListServicesResponse::class,
            static function (V1alpha\ServerReflectionResponse\MessageResponseListServicesResponse $message): void {
                self::assertEquals(
                    [
                        new V1alpha\ServiceResponse('echos.api.v1.EchoService'),
                        new V1alpha\ServiceResponse('grpc.reflection.v1.ServerReflection'),
                        new V1alpha\ServiceResponse('grpc.reflection.v1alpha.ServerReflection'),
                    ],
                    $message->listServicesResponse?->service,
                );
            },
        );

        self::sendExpect(
            $stream,
            new V1alpha\ServerReflectionRequest('localhost:50051', new V1alpha\ServerReflectionRequest\MessageRequestFileContainingSymbol('echos.api.v1.EchoService')),
            V1alpha\ServerReflectionResponse\MessageResponseFileDescriptorResponse::class,
            static function (V1alpha\ServerReflectionResponse\MessageResponseFileDescriptorResponse $message): void {
                self::assertEquals([Pool::get()->descriptorByFilename('tests/protos/echo_v1.proto')?->bytes], $message->fileDescriptorResponse?->fileDescriptorProto);
            },
        );

        self::sendExpect(
            $stream,
            new V1alpha\ServerReflectionRequest('localhost:50051', new V1alpha\ServerReflectionRequest\MessageRequestFileContainingSymbol('grpc.reflection.v1.ServerReflection')),
            V1alpha\ServerReflectionResponse\MessageResponseFileDescriptorResponse::class,
            static function (V1alpha\ServerReflectionResponse\MessageResponseFileDescriptorResponse $message): void {
                self::assertEquals([Pool::get()->descriptorByFilename('grpc/reflection/v1/reflection.proto')?->bytes], $message->fileDescriptorResponse?->fileDescriptorProto);
            },
        );

        self::sendExpect(
            $stream,
            new V1alpha\ServerReflectionRequest('localhost:50051', new V1alpha\ServerReflectionRequest\MessageRequestFileContainingSymbol('grpc.reflection.v1alpha.ServerReflection')),
            V1alpha\ServerReflectionResponse\MessageResponseFileDescriptorResponse::class,
            static function (V1alpha\ServerReflectionResponse\MessageResponseFileDescriptorResponse $message): void {
                self::assertEquals([Pool::get()->descriptorByFilename('grpc/reflection/v1alpha/reflection.proto')?->bytes], $message->fileDescriptorResponse?->fileDescriptorProto);
            },
        );

        self::sendExpect(
            $stream,
            new V1alpha\ServerReflectionRequest('localhost:50051', new V1alpha\ServerReflectionRequest\MessageRequestFileContainingExtension(
                new V1alpha\ExtensionRequest(),
            )),
            V1alpha\ServerReflectionResponse\MessageResponseErrorResponse::class,
            static function (V1alpha\ServerReflectionResponse\MessageResponseErrorResponse $message): void {
                self::assertSame(Code::UNIMPLEMENTED->value, $message->errorResponse?->errorCode);
                self::assertSame('extensions are not implemented', $message->errorResponse->errorMessage);
            },
        );

        self::sendExpect(
            $stream,
            new V1alpha\ServerReflectionRequest('localhost:50051', new V1alpha\ServerReflectionRequest\MessageRequestFileByFilename('a.b.c.proto')),
            V1alpha\ServerReflectionResponse\MessageResponseErrorResponse::class,
            static function (V1alpha\ServerReflectionResponse\MessageResponseErrorResponse $message): void {
                self::assertSame(Code::NOT_FOUND->value, $message->errorResponse?->errorCode);
                self::assertSame('file not found', $message->errorResponse->errorMessage);
            },
        );

        $stream->close();
    }

    public function testReflectionFlow(): void
    {
        $client = new V1\ServerReflectionClient(new Client\Builder()->build());

        $stream = $client->serverReflectionInfo();

        $request = new V1\ServerReflectionRequest('localhost:50051', new V1\ServerReflectionRequest\MessageRequestListServices('*'));

        $stream->send($request);

        $response = $stream->receive();
        $message = $response->messageResponse;

        self::assertInstanceOf(V1\ServerReflectionResponse\MessageResponseListServicesResponse::class, $message);
        self::assertEquals(
            [
                new V1\ServiceResponse('echos.api.v1.EchoService'),
                new V1\ServiceResponse('grpc.reflection.v1.ServerReflection'),
                new V1\ServiceResponse('grpc.reflection.v1alpha.ServerReflection'),
            ],
            $message->listServicesResponse?->service,
        );

        $stream->close();
    }

    /**
     * @template T of V1alpha\ServerReflectionResponse\MessageResponse
     * @param Client\BidirectionalStreamChannel<V1alpha\ServerReflectionRequest, V1alpha\ServerReflectionResponse> $channel
     * @param class-string<T> $messageOf
     * @param \Closure(T): void $expect
     */
    private static function sendExpect(
        Client\BidirectionalStreamChannel $channel,
        V1alpha\ServerReflectionRequest $request,
        string $messageOf,
        \Closure $expect,
    ): void {
        $channel->send($request);

        /** @var V1alpha\ServerReflectionResponse $response */
        $response = $channel->receive();
        self::assertEquals($request, $response->originalRequest);

        $messageResponse = $response->messageResponse;
        self::assertInstanceOf($messageOf, $messageResponse);
        $expect($messageResponse);
    }
}
