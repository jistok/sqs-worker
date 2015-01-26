<?php

// Make sure nothing is printed by default so we don't get double errors
error_reporting(0);
ini_set("display_errors", false);

set_error_handler("pw_error_handler");

$workload = "";
while(!feof(STDIN))
{
  $workload.= fread(STDIN,1024);
}
fclose(STDIN);

if(!$workload)
{
  file_put_contents("php://stderr", "Empty workload\n");
  exit(1);
}

$workload = @json_decode($workload, true);
if(!is_array($workload))
{
  file_put_contents("php://stderr", "Invalid JSON\n");
  exit(2);
}

if(!isset($workload['module']) or !isset($workload['method']))
{
  file_put_contents("php://stderr", "Module and method are required\n");
  exit(3);
}

try {
  // Boot PW
  require 'index.php';

  echo "Executing {$workload['module']}::{$workload['method']}\n";

  // Make sure nothing is still not printed
  error_reporting(0);
  ini_set("display_errors", false);

  // Switch to admin
  $adminUser = $wire->users->get('name=admin');
  $wire->setFuel('user', $adminUser);

  $module = $wire->modules->get($workload['module']);
  if(!$module)
  {
    file_put_contents("php://stderr", "Unknown module '{$workload['module']}'\n");
    exit(4);
  }

  $method = $workload['method'];

  if(!is_callable($module,$method))
  {
    file_put_contents("php://stderr", "Cannot call method '{$method}' on module '{$workload['module']}'\n");
    exit(5);
  }

  $parms = isset($workload['parameters']) ? $workload['parameters'] : [];

  $module->$method($parms);
} catch(Exception $e) {
  // Never let PW grab the Exception
  file_put_contents("php://stderr", "{$workload['module']}::{$method} threw ".get_class($e).": ".$e->getMessage() . "\n");
  exit(255);
}

function pw_error_handler($errno, $errstr, $errfile, $errline)
{
  // Halt immediteally for any error, even notice
  file_put_contents("php://stderr", "Error {$errno}: {$errstr} @ {$errfile}:{$errline}\n");
  exit(254);
}
