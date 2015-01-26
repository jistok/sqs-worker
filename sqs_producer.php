<?php

require 'sqs_common.php';

$queueUrl = "https://sqs.eu-central-1.amazonaws.com/467142362545/pw-testing";

$result = $client->sendMessage(array(
    'QueueUrl'    => $queueUrl,
    'MessageBody' => json_encode([
      'Function'   => 'pw_generic',
      'Parameters' => [
        'method'=>'Yadiyadiyadi'
      ],
    ]),
));

echo "Sent message: " . $result['MessageId'] ." ({$queueUrl})\n";