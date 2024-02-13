<?php
namespace Civi\Extlib;

\pathload()->activatePackage('extlib@1' , __DIR__, [
  'autoload' => [
    'psr-4' => [
      'Civi\\Extlib\\' => ['src/'],
    ]
  ]
]);
