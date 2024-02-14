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

  use IdentityTrait {
    init as initIdentity;
  }

  /**
   * Optionally delegate to "CRM_Myext_Upgrader" or "Civi\Myext\Upgrader".
   *
   * @var \CRM_Extension_Upgrader_Interface|null
   */
  private $customUpgrader;

  /**
   * @var \CRM_Extension_Upgrader_Interface|null
   */
  private $sqlInstaller;

  public function init(array $params) {
    $this->initIdentity($params);
    if ($info = $this->getInfo()) {
      $this->sqlInstaller = new SqlInstaller();
      $this->sqlInstaller->init($params);

      if ($class = $this->getDelegateUpgraderClass($info)) {
        $this->customUpgrader = new $class();
        $this->customUpgrader->init($params);
      }
    }
  }

  public function notify(string $event, array $params = []) {
    $info = $this->getInfo();
    if (!$info) {
      return;
    }

    $sqlEarlyEvents = ['install', 'enable'];
    if (in_array($event, $sqlEarlyEvents)) {
      $this->sqlInstaller->notify($event, $params);
    }

    if ($this->customUpgrader) {
      $this->customUpgrader->notify($event, $params);
    }

    if (!in_array($event, $sqlEarlyEvents)) {
      $this->sqlInstaller->notify($event, $params);
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
