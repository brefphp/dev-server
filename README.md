Development web server for serverless-native PHP web apps.

**This project is currently experimental and the documentation incomplete.**

## Why?

This web server is meant for HTTP applications implemented without framework, using API Gateway as the router and PSR-15 controllers.

## Installation

```bash
composer require --dev bref/dev-server
```

## Usage

Run the webserver with:

```bash
php -S 127.0.0.1:8000 vendor/bin/bref-dev-server
```
