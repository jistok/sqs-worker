<?php

require 'sqs_common.php';

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

echo date("c - ") . "Worker started\n";
$hadJob = true;

// Main loop
while(true)
{
  try { 
    if($hadJob)
    {
      echo date("c - ") . "Waiting for work\n";
      $hadJob = false;
    }
    $result = $client->receiveMessage(array(
      'QueueUrl'        => $queueUrl,
      // One at a time
      'MaxNumberOfMessages' => 1,
      // Long poll
      'WaitTimeSeconds'     => 20,
    ));
    // Meaning long poll timed out
    if(!($result instanceof \Guzzle\Service\Resource\Model))
    {
      // Crashing intentionally
      throw new \Exception("Received something very unexpected");
    }
    elseif(!isset($result['Messages']) or !is_array($result['Messages']) or !count($result['Messages']))
    {
      // Long poll timed out (...most likely... or at least hopefully)
      // echo date("c - ") . "-> Timeout, polling again\n";
      continue;
    }
  } catch (\Exception $e) {
    // Just rethrow to see what comes
    file_put_contents("php://stderr", date("c - ") . "-> FATAL: Message receive failed, worker dying on ".get_class($e). ": ".$e->getMessage()."\n");
    exit(1);
  }

  $hadJob = true;

  // Looping in case we one day change MaxNumberOfMessages
  foreach($result['Messages'] as $message) {
    // Do something with the message
    $body = $message['Body'];
    $handle = $message['ReceiptHandle'];

    echo date("c - ") . "-> Picked up message: {$message['MessageId']}\n";

    // Delete straight away to avoid retry. If execution fails we have other ways
    try {
      $client->deleteMessage([
          'QueueUrl'      => $queueUrl,
          'ReceiptHandle' => $handle,
      ]);
    } catch(\Exception $e) {
      file_put_contents("php://stderr", date("c - ") . "-> FATAL: Message deletion failed, worker dying on ".get_class($e). ": ".$e->getMessage()."\n");
      exit(2);
    }

    $data = json_decode($body, true);
    if(!$data)
    {
      file_put_contents("php://stderr", date("c - ") . "-> ERROR: Invalid data, json_decode failed\n");
      continue;
    }

    if(!isset($data['Function']))
    {
      file_put_contents("php://stderr", date("c - ") . "-> ERROR: Invalid data, no function defined\n");
      continue;
    }

    $function = "executor_" . $data['Function'];
    if(!is_callable($function))
    {
      file_put_contents("php://stderr", date("c - ") . "-> ERROR: Invalid data, function is not callable\n");
      continue;
    }

    $parms = isset($data['Parameters']) ? $data['Parameters'] : [];

    if(!is_array($parms))
    {
      file_put_contents("php://stderr", date("c - ") . "-> ERROR: Parameters must be an array\n");
      continue;
    }

    // We don't care about the return value
    echo date("c - ") . "-> Executing {$data['Function']}\n";
    try {
      $function($parms);
    } catch(\Exception $e) {
      file_put_contents("php://stderr", date("c - ") . "-> ERROR: Executor threw an ".get_class($e). ": ".$e->getMessage()."\n");
    }
  }   
}

function executor_pw_generic($parameters = [])
{
  echo date("c - ") . "\t-> PW execution with parameters: " . json_encode($parameters) . "\n";
  sleep(5); // Simulate hard work
}