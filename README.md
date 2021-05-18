# Pipeline
Psr-15 middleware pipeline

## Installation

```bash
composer require bermudaphp/pipeline
```

## Usage

```php

use function Bermuda\Pipeline\pipe;

pipe()->pipe($myFirstMiddlewareInstance)
      ->pipe($mySecondMiddlewareInstance)
      ->process($serverRequest, $requestHandler);
```

## Request handling

```php

use function Bermuda\Pipeline\pipe;

pipe([$myFirstMiddlewareInstance, $mySecondMiddlewareInstance])->handle($serverRequest);
```


