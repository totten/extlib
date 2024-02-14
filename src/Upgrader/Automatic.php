<?php

namespace Civi\Extlib\Upgrader;

/**
 * The "Automatic" extension upgrader has built-in support for the XML schema files.
 * During installation and uninstallation, it will automatically generate and execute suitable SQL.
 *
 * Additionally, extensions have their own "Upgrader" classes to define the schema-revisions.
 * This will delegate to the existing upgrader and apply the same schema-revisions.
 */
class Automatic implements \CRM_Extension_Upgrader_Interface {

  /**
   * @var string|null
   */
  private $key;

  /**
   * Optionally delegate to "CRM_Myext_Upgrader" or "Civi\Myext\Upgrader".
   *
   * @var \CRM_Extension_Upgrader_Interface|null
   */
  private $delegate;

  public function init(array $params) {
    $this->key = $params['key'];
    if ($info = $this->getInfo()) {
      if ($class = $this->getDelegateUpgraderClass($info)) {
        $this->delegate = new $class();
        $this->delegate->init($params);
      }
    }
  }

  public function notify(string $event, array $params = []) {
    $info = $this->getInfo();
    if (!$info) {
      return;
    }

    // if ($event === 'install') {
    //    TODO: Check the XML files. Generate and evaluate the SQL.
    // }

    if ($this->delegate) {
      $this->delegate->notify($event, $params);
    }
  }

  public function getInfo(): ?\CRM_Extension_Info {
    try {
      return \CRM_Extension_System::singleton()->getMapper()->keyToInfo($this->key);
    }
    catch (\CRM_Extension_Exception_ParseException $e) {
      \Civi::log()->error("Parse error in extension " . $this->key . ": " . $e->getMessage());
      return NULL;
    }
  }

  /**
   * Civix-based extensions have a conventional name for their upgrader class ("CRM_Myext_Upgrader"
   * or "Civi\Myext\Upgrader"). Figure out if this class exists.
   *
   * @param \CRM_Extension_Info $info
   * @return string|null
   *   Ex: 'CRM_Myext_Upgrader' or 'Civi\Myext\Upgrader'
   */
  public function getDelegateUpgraderClass(\CRM_Extension_Info $info): ?string {
    $candidates = [];

    if (!empty($info->civix['namespace'])) {
      $namespace = $info->civix['namespace'];
      $candidates[] = sprintf('%s_Upgrader', str_replace('/', '_', $namespace));
      $candidates[] = sprintf('%s\\Upgrader', str_replace('/', '\\', $namespace));
    }

    foreach ($candidates as $candidate) {
      if (class_exists($candidate)) {
        return $candidate;
      }
    }

    return NULL;
  }

}
