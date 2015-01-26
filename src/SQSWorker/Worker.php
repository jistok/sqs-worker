<?php

// TODO: Remove echo -> Monolog

namespace SQSWorker;

use Aws\Sqs\SqsClient;

class Worker
{
  protected $queue_url;
  protected $client;

  public function __construct(SqsClient $client, $queue_url)
  {
    $this->client = $client;
    $this->queue_url = $queue_url;
  }

  // Main loop
  public function Work()
  {
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
        $result = $this->client->receiveMessage(array(
          'QueueUrl'            => $this->queue_url,
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
          $this->client->deleteMessage([
              'QueueUrl'      => $this->queue_url,
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
        $callable = [$this,$function];
        if(!is_callable($callable))
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
          call_user_func($callable, $parms);
        } catch(\Exception $e) {
          file_put_contents("php://stderr", date("c - ") . "-> ERROR: Executor threw an ".get_class($e). ": ".$e->getMessage()."\n");
        }
      }   
    }
  }

  // TODO: Make these so that they can be registered outside
  protected function executor_pw_generic($parameters = [])
  {
    echo date("c - ") . "\t-> PW execution with parameters: " . json_encode($parameters) . "\n";
    sleep(5); // Simulate hard work
    // TODO: Acquire locks, check that the job has not been started and update so
  }
}