<?php

namespace Civi\Extlib\Upgrader;

trait IdentityTrait {

  /**
   * @var string
   *   eg 'com.example.myextension'
   */
  protected $extensionName;

  /**
   * @var string
   *   full path to the extension's source tree
   */
  protected $extensionDir;

  /**
   * {@inheritDoc}
   */
  public function init(array $params) {
    $this->extensionName = $params['key'];
    $system = \CRM_Extension_System::singleton();
    $mapper = $system->getMapper();
    $this->extensionDir = $mapper->keyToBasePath($this->extensionName);
  }

  /**
   * @return string
   *   Ex: 'org.example.foobar'
   */
  public function getExtensionKey() {
    return $this->extensionName;
  }

  /**
   * @return string
   *   Ex: '/var/www/sites/default/ext/org.example.foobar'
   */
  public function getExtensionDir() {
    return $this->extensionDir;
  }

  /**
   * @return \CRM_Extension_Info|null
   * @throws \CRM_Extension_Exception
   */
  public function getInfo() {
    try {
      return \CRM_Extension_System::singleton()->getMapper()->keyToInfo($this->extensionName);
    }
    catch (\CRM_Extension_Exception_ParseException $e) {
      \Civi::log()->error("Parse error in extension " . $this->extensionName . ": " . $e->getMessage());
      return NULL;
    }
  }

}
