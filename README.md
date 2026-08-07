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

### Function Example
You can use this template as a sample for a simple function of your application.
Context will be mapped from `lambda-context` attribute of the request as stated [here](https://bref.sh/docs/use-cases/http/advanced-use-cases#lambda-event-and-context). 

This function is giving the output as per [Lambda Proxy Integration Response spec](https://docs.aws.amazon.com/apigateway/latest/developerguide/set-up-lambda-proxy-integrations.html#api-gateway-simple-proxy-for-lambda-output-format)

```php
<?php

namespace App;

use Bref\Event\Handler;
use Bref\Context\Context;

require 'vendor/autoload.php';

class HelloHandler implements Handler
{
    /**
     * @param $event
     * @param Context|null $context
     * @return array
     */
    public function handle($event, ?Context $context): array
    {
        return [
            "statusCode"=>200,
            "headers"=>[
                'Access-Control-Allow-Origin'=> '*',
                'Access-Control-Allow-Credentials'=> true,
                'Access-Control-Allow-Headers'=> '*',
            ],
            "body"=>json_encode([
                "message" =>'Bref! Your function executed successfully!',
                "context" => $context,
                "input" => $event
            ])
        ];

    }
}

return new HelloHandler();


```

And its related `serverless.yaml` part under `functions`

```yaml
hello:
  runtime: provided.al2
  layers:
    - ${bref:layer.php-81}
  handler: src/function/hello/index.php #function handler
  package: #package patterns
    include:
      - "!**/*"
      - vendor/**
      - src/function/hello/**
  events: #events
    #keep warm event
    - schedule:
        rate: rate(5 minutes)
        enabled: true
        input:
          warmer: true
    #api gateway event
    - http:
        path: /hello #api endpoint path
        method: 'GET' #api endpoint method
        cors: true
        
```

### Assets

By default, static assets are served from the current directory.

To customize that, use the `--assets` option. For example to serve static files from the `public/` directory:

```bash
vendor/bin/bref-dev-server --assets=public
```
