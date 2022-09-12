Development web server for serverless-native PHP web apps.

## Why?

This web server is meant for HTTP applications implemented without framework, using API Gateway as the router and PSR-15 controllers.

## Installation

```bash
composer require --dev bref/dev-server
```

## Usage

Run the webserver with:

```bash
vendor/bin/bref-dev-server
```

The application will be available at [http://localhost:8000/](http://localhost:8000/).

Routes will be parsed from `serverless.yml` in the current directory.

### Assets

By default, static assets are served from the current directory.

To customize that, use the `--assets` option. For example to serve static files from the `public/` directory:

```bash
vendor/bin/bref-dev-server --assets=public
```
