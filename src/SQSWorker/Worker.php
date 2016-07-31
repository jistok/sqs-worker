<?php

namespace SQSWorker;

use Aws\Sqs\SqsClient;

class Worker
{
    protected $queue_url;
    protected $client;
    protected $executors;

    public function __construct(SqsClient $client, $queue_url)
    {
        $this->client = $client;
        $this->queue_url = $queue_url;
        $this->executors = [];
        echo "Worker waking up\n";
    }

    public function teach($name, $callback)
    {
        if(!is_callable($callback))
            throw new \Exception("Invalid callback");

        if(isset($this->executors[$name]))
            throw new \Exception("Already know how to work with {$name}");

        $this->executors[$name] = $callback;
        echo "-> Learned {$name}\n";
    }

    // Main loop
    public function work()
    {
        if(!$this->executors)
            throw new \Exception("I'd rather be educated first");

        $hadJob = true;

        // Main loop
        while(true)
        {
            try
            { 
                if($hadJob)
                {
                    echo "Waiting for work\n";
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
                    // echo "-> Timeout, polling again\n";
                    continue;
                }
            } catch (\Exception $e) {
                // Just rethrow to see what comes
                file_put_contents("php://stderr", "-> FATAL: Message receive failed, worker dying on ".get_class($e). ": ".$e->getMessage()."\n");
                exit(1);
            }

            $hadJob = true;

            // Looping in case we one day change MaxNumberOfMessages
            foreach($result['Messages'] as $message)
            {
                // Do something with the message
                $body = $message['Body'];
                $handle = $message['ReceiptHandle'];

                echo "-> Picked up message: {$message['MessageId']}\n";

                $data = json_decode($body, true);
                if(!$data)
                {
                    file_put_contents("php://stderr", "-> ERROR: Invalid data, json_decode failed\n");
                    continue;
                }

                if(!isset($data['Function']))
                {
                    file_put_contents("php://stderr", "-> ERROR: Invalid data, no function defined - using default\n");
                    $data['Function'] = 'default';
                    continue;
                }

                if(!isset($this->executors[$data['Function']]))
                {
                    file_put_contents("php://stderr", "-> ERROR: I don't know how to handle {$data['Function']}");
                    continue;
                }

                $callable = $this->executors[$data['Function']];
                if(!is_callable($callable))
                {
                    file_put_contents("php://stderr", "-> ERROR: Function is not callable (anymore)\n");
                    continue;
                }

                $parms = isset($data['Parameters']) ? $data['Parameters'] : [];

                if(!is_array($parms))
                {
                    file_put_contents("php://stderr", "-> ERROR: Parameters must be an array\n");
                    continue;
                }

                // We don't care about the return value
                echo "-> Executing {$data['Function']}\n";
                try
                {
                    unset($data['Function'], $data['Parameters']);
                    // if returns ok
                    if(call_user_func($callable, $parms, $data))
                    {
                        try {
                            $this->client->deleteMessage([
                                'QueueUrl'      => $this->queue_url,
                                'ReceiptHandle' => $handle,
                            ]);
                        } catch(\Exception $e) {
                            file_put_contents("php://stderr", "-> FATAL: Message deletion failed, worker dying on ".get_class($e). ": ".$e->getMessage()."\n");
                            exit(2);
                        }
                    }
                } catch(\Exception $e) {
                    file_put_contents("php://stderr", "-> ERROR: Executor threw an ".get_class($e). ": ".$e->getMessage()."\n");
                }
            }
        }
    }
}
