<?php

include __DIR__.'/vendor/autoload.php';

$capsule = new \Illuminate\Database\Capsule\Manager;
$capsule->addConnection([ 'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'prfx_' ]);
$capsule->setEventDispatcher(new \Illuminate\Events\Dispatcher);
$capsule->bootEloquent();
$capsule->setAsGlobal();
