### gRPC server reflection protocol implementation

## Installation

```bash
composer require thesis/grpc-server-reflection
```

## Usage

### Reflection v1 only (`registerV1`)

Use this when you only need reflection `v1`.

```php
<?php

declare(strict_types=1);

use Thesis\Grpc\Server;
use Thesis\Grpc\Server\Reflection;

$server = new Server\Builder()
    ->withServices(/* your service registries */)
    ->build();

Reflection\registerV1($server);

$server->start();
```

### Reflection v1 + v1alpha (`registerV1Alpha`)

Use this when you need both reflection endpoints: `v1` and `v1alpha` (deprecated).

```php
<?php

declare(strict_types=1);

use Thesis\Grpc\Server;
use Thesis\Grpc\Server\Reflection;

$server = new Server\Builder()
    ->withServices(/* your service registries */)
    ->build();

Reflection\registerV1Alpha($server);

$server->start();
```

## Why

Server Reflection lets gRPC clients discover services and schemas at runtime, without local `.proto` files.

This is useful for:
- ad-hoc debugging and smoke checks when proto files are not locally available;
- interactive API exploration;
- fast integration checks in CI and local development.

With reflection enabled you can use tools like `grpcurl` and `grpcui`.

## grpcurl

```bash
# List all services
grpcurl -plaintext localhost:50051 list

# Show methods for a service
grpcurl -plaintext localhost:50051 describe thesis.echos.api.v1.EchoService
```

## grpcui

```bash
grpcui -plaintext localhost:50051
```

Then open the URL printed by `grpcui` in your browser.
