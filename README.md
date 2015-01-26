SQS Worker that executes PW jobs
================================

To use this you need to have these in your composer.json

```json
{
  "require": {
    "sforsman/SQSWorker": "dev-master"
  },
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/sforsman/SQSWorker"
    }
  ]
}
```

Also you need to define the worker in your Procfile. Here's an example with the default web process 
type as well.

```
web: vendor/bin/heroku-php-apache2
worker: php vendor/bin/sqs_worker.php
```

And finally, but most importantly, you need to define a custom buildpack for your app:

```bash
deis create <myapp> -b https://github.com/sforsman/heroku-buildpack-php.git

# After the application has been created, bring the worker(s) upp with
deis scale worker=1
```

Or if the app is already running

```bash
deis config:set BUILDPACK_URL=https://github.com/sforsman/heroku-buildpack-php.git
```
