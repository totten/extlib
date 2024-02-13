<?php

namespace Civi\Extlib;

class Greeter {

  public static function greet(): void {
    printf("Hello from extlib@1 (%s)\n", __FILE__);
  }

}
