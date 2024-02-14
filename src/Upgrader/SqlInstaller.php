<?php

namespace Civi\Extlib\Upgrader;

/**
 * Target: CiviCRM v5.38+
 *
 */
class SqlInstaller implements \CRM_Extension_Upgrader_Interface {

  use IdentityTrait;

  public function notify(string $event, array $params = []) {
    switch ($event) {
      case 'install':
        $schema = $this->createSchema();
        $sqls = $schema ? $schema->generateCreateSql() : [];
        break;

      case 'uninstall':
        $schema = $this->createSchema();
        $sqls = $schema ? $schema->generateDropSql() : [];
        break;

      default:
        $sqls = [];
    }

    foreach ($sqls as $sql) {
      \CRM_Utils_File::runSqlQuery(CIVICRM_DSN, $sql);
    }
  }

  /**
   * @return \CRM_Core_CodeGen_Schema|null
   * @throws \CRM_Core_Exception
   * @throws \CRM_Extension_Exception
   */
  protected function createSchema() {
    $info = $this->getInfo();
    $namespace = $info->civix['namespace'];
    $extensionDir = $this->getExtensionDir();

    $xmlSchemaGlob = "xml/schema/$namespace/*.xml";
    $xmlSchemas = glob($extensionDir . '/' . $xmlSchemaGlob);
    if (empty($xmlSchemas)) {
      return NULL;
    }

    $specification = new \CRM_Core_CodeGen_Specification();
    $specification->buildVersion = \CRM_Utils_System::majorVersion();
    $config = new \stdClass();
    $config->phpCodePath = $extensionDir;
    $config->sqlCodePath = $extensionDir . '/sql/';
    $config->database = $this->getDefaultDatabase();

    foreach ($xmlSchemas as $xmlSchema) {
      $dom = new \DomDocument();
      $xmlString = file_get_contents($xmlSchema);
      $dom->loadXML($xmlString);
      $xml = simplexml_import_dom($dom);
      if (!$xml) {
        throw new \CRM_Core_Exception('There is an error in the XML for ' . $xmlSchema);
      }
      /** @var array $tables */
      $specification->getTable($xml, $config->database, $tables);
      $name = (string) $xml->name;
      $tables[$name]['name'] = $name;
      $sourcePath = strstr($xmlSchema, "/xml/schema/$namespace/");
      $tables[$name]['sourceFile'] = $this->getExtensionKey() . $sourcePath;
    }

    $config->tables = $tables;
    $this->orderTables($tables);
    $this->resolveForeignKeys($tables);
    $config->tables = $tables;

    return new \CRM_Core_CodeGen_Schema($config);
  }

  private function orderTables(&$tables): void {
    $ordered = [];
    $abort = count($tables);

    while (count($tables)) {
      // Safety valve
      if ($abort-- == 0) {
        \Civi::log()->error("<error>Cannot determine FK ordering of tables.</error>  Do you have circular Foreign Keys?  Change your FK's or fix your auto_install.sql");
        break;
      }
      // Consider each table
      foreach ($tables as $k => $table) {
        // No FK's? Easy - add now
        if (!isset($table['foreignKey'])) {
          $ordered[$k] = $table;
          unset($tables[$k]);
        }
        if (isset($table['foreignKey'])) {
          // If any FK references a table still in our list (but is not a self-reference),
          // skip this table for now
          foreach ($table['foreignKey'] as $fKey) {
            if (in_array($fKey['table'], array_keys($tables)) && $fKey['table'] != $table['name']) {
              continue 2;
            }
          }
          // If we get here, all FK's reference already added tables or external tables so add now
          $ordered[$k] = $table;
          unset($tables[$k]);
        }
      }
    }
    $tables = $ordered;
  }

  private function resolveForeignKeys(&$tables): void {
    foreach ($tables as &$table) {
      if (isset($table['foreignKey'])) {
        foreach ($table['foreignKey'] as &$key) {
          if (isset($tables[$key['table']])) {
            $key['className'] = $tables[$key['table']]['className'];
            $key['fileName'] = $tables[$key['table']]['fileName'];
            $table['fields'][$key['name']]['FKClassName'] = $key['className'];
          }
          else {
            $key['className'] = \CRM_Core_DAO_AllCoreTables::getClassForTable($key['table']);
            $key['fileName'] = $key['className'] . '.php';
            $table['fields'][$key['name']]['FKClassName'] = $key['className'];
          }
        }
      }
    }
  }

  /**
   * Get general/default database options (eg character set, collation).
   *
   * In civicrm-core, the `database` definition comes from
   * `xml/schema/Schema.xml` and `$spec->getDatabase($dbXml)`.
   *
   * Civix uses different defaults. Explanations are inlined below.
   *
   * @return array
   */
  private function getDefaultDatabase(): array {
    return [
      'name' => '',
      'attributes' => '',
      'tableAttributes_modern' => 'ENGINE=InnoDB',
      'tableAttributes_simple' => 'ENGINE=InnoDB',
      // ^^ Set very limited defaults.
      // Existing deployments may be inconsistent with respect to charsets and collations, and
      // it's hard to attune with static code. This represents a compromise (until we can
      // rework the process in a way that clearly addresses the inconsistencies among deployments).
      'comment' => '',
    ];
  }

}
