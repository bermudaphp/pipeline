# Pipeline
Psr-15 middleware pipeline

## Installation

```bash
composer require lobster-php/pipeline
```

## Usage

```php

$pipeline = (new PipelineFactory)();

$pipeline->pipe($MiddlewareInterfaceInstance);

$response = $pipeline->process($request, $handler);

or

$response = $pipeline->handle($request);
```


