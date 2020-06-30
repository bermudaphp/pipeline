# Pipeline
Psr-15 middleware pipeline

## Installation

```bash
composer require bermudaphp/pipeline
```

## Usage

```php

$pipeline = new Pipeline();

$pipeline->pipe($MiddlewareInterfaceInstance);

$response = $pipeline->process($request, $handler);

or

$response = $pipeline->handle($request);
```


