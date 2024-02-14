<?php

namespace Civi\Extlib\Upgrader;

/**
 * SqlInstaller can be used during installation+uninstallation. It reads the
 * `xml/schema/*` files and generates suitable SQL at runtime.
 *
 * Details such as character-sets and collations are determined dynamically,
 * to match the local system.
 *
 * To simplify backport considerations, `SqlInstaller` does not support subclassing.
 *
 * Target: CiviCRM v5.38+
 */
final class SqlInstaller implements \CRM_Extension_Upgrader_Interface {

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
   * @return array
   */
  private function getDefaultDatabase(): array {
    // What character-set is used for CiviCRM core schema? What collation?
    // This depends on when the DB was *initialized*:
    // - civicrm-core >= 5.33 has used `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`
    // - civicrm-core 4.3-5.32 has used `CHARACTER SET utf8 COLLATE utf8_unicode_ci`
    // - civicrm-core <= 4.2 -- I haven't checked, but it's probably the same.
    // Some systems have migrated (eg APIv3's `System.utf8conversion`), but (as of Feb 2024)
    // we haven't made any effort to push to this change.
    $collation = \CRM_Core_BAO_SchemaHandler::getInUseCollation();
    $characterSet = (stripos($collation, 'utf8mb4') !== FALSE) ? 'utf8mb4' : 'utf8';
    return [
      'name' => '',
      'attributes' => '',
      'tableAttributes_modern' => "ENGINE=InnoDB DEFAULT CHARACTER SET {$characterSet} COLLATE {$collation}",
      'tableAttributes_simple' => 'ENGINE=InnoDB',
      'comment' => '',
    ];
  }

}
