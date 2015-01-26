<?php

require 'vendor/autoload.php';

use SQSWorker\Worker;
use Aws\Sqs\SqsClient;

// TODO: Add monolog stuff, then disable these
error_reporting(E_ALL);
ini_set("display_errors", true);

$client = SqsClient::factory([
  // These are fine for now, but really need to consider whether we want to
  // store them inside the container's environment. We could at least create
  // an IAM profile that has only access to SQS
  'key'    => getenv('AWS_ACCESS_KEY_ID'), 
  'secret' => getenv('AWS_SECRET_ACCESS_KEY'), 
  'region' => getenv('AWS_REGION'),
]);

// Note: Settings do not change after creation
$queue = $client->createQueue([
  'QueueName'  => 'pw-' . getenv('PW_DB_NAME'),
  'Attributes' => [
    // 'DelaySeconds'       => 5, // Default is zero
    // 'MaximumMessageSize' => 4096, // The default is 256 KB
    // 'MessageRetentionPeriod' => 60 * 60 * 24 // Default is 4 days

    // 60 seconds is more than enough for a worker to pick up the job, mark it as started and remove the message
    'VisibilityTimeout'  => 60, // Default is 30
  ],
]);

$queueUrl = $queue->get('QueueUrl');

$worker = new Worker($client, $queueUrl);
$worker->work(); //Enter main loop