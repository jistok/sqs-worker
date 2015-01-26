<?php

// TODO: Add monolog stuff, then disable these
error_reporting(E_ALL);
ini_set("display_errors", true);

require 'vendor/autoload.php';

use Aws\Sqs\SqsClient;

$client = SqsClient::factory([
  // These are fine for now, but really need to consider whether we want to
  // store them inside the container's environment. We could at least create
  // an IAM profile that has only access to SQS
  'key'    => getenv('AWS_ACCESS_KEY_ID'), 
  'secret' => getenv('AWS_SECRET_ACCESS_KEY'), 
  'region' => getenv('AWS_REGION'),
]);
