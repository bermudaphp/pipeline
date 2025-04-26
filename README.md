# Bermuda Pipeline

**Bermuda Pipeline** is a lightweight and flexible implementation of the pipeline pattern for PHP applications. It provides a robust mechanism for processing HTTP requests through a chain of middleware components compliant with PSR-7 and PSR-15 standards.

## Overview

Bermuda Pipeline enables you to create a processing pipeline where each middleware receives a ServerRequest, performs its specific handling, and then delegates control to the next middleware in the chain. When the middleware chain is exhausted, a fallback handler is used to generate a response.

The key benefits of using Bermuda Pipeline include:

- **Immutability**: Methods such as `pipe()` return a new pipeline instance with the added middleware, keeping the original pipeline unchanged. This makes it safe for use in complex, multi-threaded, or reentrant scenarios.
- **PSR Compliance**: The implementation is based on PSR-7 and PSR-15 standards, ensuring seamless interoperability with other components in the PHP ecosystem.
- **Deep Cloning**: When cloning a pipeline, each middleware is deeply cloned to avoid shared mutable state, ensuring that changes to one instance do not affect others.
- **Flexible Fallback Handler**: A fallback handler is invoked once all middleware have been executed, ensuring that a default response is always available.

## Features

- **IteratorAggregate and Countable Interfaces**: Easily iterate over the middleware chain and retrieve the number of middleware components.
- **Immutability**: The pipeline is designed to be immutable; all modifications (e.g., adding middleware) return a new instance.
- **Deep Cloning**: When a pipeline is cloned, every middleware is cloned as well to prevent shared state issues.
- **Factory Method**: The static method `createFromIterable()` simplifies the instantiation of a pipeline from a list of middleware components.
- **Customizable Fallback Handler**: Use `withFallbackHandler()` to replace the default fallback handler without altering the original pipeline instance.

## Requires

- **PHP:** >= 8.4

## Installation

You can install Bermuda Pipeline via Composer. Run the following command in your project directory:

```bash
composer require bermudaphp/pipeline
```

## Usage

```php

// Create the pipeline with an array of middleware
$pipeline = new Pipeline([new MyFirstMiddleware(), new MySecondMiddleware]);

// Process the request through the pipeline, resulting in a ResponseInterface instance
$response = $pipeline->handle($request);
```



