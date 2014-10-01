<?php

final class PhabricatorStorageManagementAdjustWorkflow
  extends PhabricatorStorageManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('adjust')
      ->setExamples('**adjust** [__options__]')
      ->setSynopsis(
        pht(
          'Make schemata adjustments to correct issues with characters sets, '.
          'collations, and keys.'));
  }

  public function execute(PhutilArgumentParser $args) {
    $this->requireAllPatchesApplied();
    $this->adjustSchemata();
    return 0;
  }

  private function requireAllPatchesApplied() {
    $api = $this->getAPI();
    $applied = $api->getAppliedPatches();

    if ($applied === null) {
      throw new PhutilArgumentUsageException(
        pht(
          'You have not initialized the database yet. You must initialize '.
          'the database before you can adjust schemata. Run `storage upgrade` '.
          'to initialize the database.'));
    }

    $applied = array_fuse($applied);

    $patches = $this->getPatches();
    $patches = mpull($patches, null, 'getFullKey');
    $missing = array_diff_key($patches, $applied);

    if ($missing) {
      throw new PhutilArgumentUsageException(
        pht(
          'You have not applied all available storage patches yet. You must '.
          'apply all available patches before you can adjust schemata. '.
          'Run `storage status` to show patch status, and `storage upgrade` '.
          'to apply missing patches.'));
    }
  }

  private function loadSchemata() {
    $query = id(new PhabricatorConfigSchemaQuery())
      ->setAPI($this->getAPI());

    $actual = $query->loadActualSchema();
    $expect = $query->loadExpectedSchema();
    $comp = $query->buildComparisonSchema($expect, $actual);

    return array($comp, $expect, $actual);
  }

  private function adjustSchemata() {
    $console = PhutilConsole::getConsole();

    $console->writeOut(
      "%s\n",
      pht('Verifying database schemata...'));

    $adjustments = $this->findAdjustments();

    if (!$adjustments) {
      $console->writeOut(
        "%s\n",
        pht('Found no issues with schemata.'));
      return;
    }

    $table = id(new PhutilConsoleTable())
      ->addColumn('database', array('title' => pht('Database')))
      ->addColumn('table', array('title' => pht('Table')))
      ->addColumn('name', array('title' => pht('Name')))
      ->addColumn('info', array('title' => pht('Issues')));

    foreach ($adjustments as $adjust) {
      $info = array();
      foreach ($adjust['issues'] as $issue) {
        $info[] = PhabricatorConfigStorageSchema::getIssueName($issue);
      }

      $table->addRow(array(
        'database' => $adjust['database'],
        'table' => idx($adjust, 'table'),
        'name' => idx($adjust, 'name'),
        'info' => implode(', ', $info),
      ));
    }

    $console->writeOut("\n\n");

    $table->draw();

    $console->writeOut(
      "\n%s\n",
      pht(
        "Found %s issues(s) with schemata, detailed above.\n\n".
        "You can review issues in more detail from the web interface, ".
        "in Config > Database Status.\n\n".
        "MySQL needs to copy table data to make some adjustments, so these ".
        "migrations may take some time.".

        // TODO: Remove warning once this stabilizes.
        "\n\n".
        "WARNING: This workflow is new and unstable. If you continue, you ".
        "may unrecoverably destory data. Make sure you have a backup before ".
        "you proceed.",

        new PhutilNumber(count($adjustments))));

    $prompt = pht('Fix these schema issues?');
    if (!phutil_console_confirm($prompt, $default_no = true)) {
      return;
    }

    $console->writeOut(
      "%s\n",
      pht('Fixing schema issues...'));

    $api = $this->getAPI();
    $conn = $api->getConn(null);

    $bar = id(new PhutilConsoleProgressBar())
      ->setTotal(count($adjustments));
    foreach ($adjustments as $adjust) {
      switch ($adjust['kind']) {
        case 'database':
          queryfx(
            $conn,
            'ALTER DATABASE %T CHARACTER SET = %s COLLATE = %s',
            $adjust['database'],
            $adjust['charset'],
            $adjust['collation']);
          break;
        case 'table':
          queryfx(
            $conn,
            'ALTER TABLE %T.%T COLLATE = %s',
            $adjust['database'],
            $adjust['table'],
            $adjust['collation']);
          break;
        default:
          throw new Exception(
            pht('Unknown schema adjustment kind "%s"!', $adjust['kind']));
      }

      $bar->update(1);
    }
    $bar->done();

    $console->writeOut(
      "%s\n",
      pht('Completed fixing all schema issues.'));
  }

  private function findAdjustments() {
    list($comp, $expect, $actual) = $this->loadSchemata();

    $issue_charset = PhabricatorConfigStorageSchema::ISSUE_CHARSET;
    $issue_collation = PhabricatorConfigStorageSchema::ISSUE_COLLATION;

    $adjustments = array();
    foreach ($comp->getDatabases() as $database_name => $database) {
      $expect_database = $expect->getDatabase($database_name);
      $actual_database = $actual->getDatabase($database_name);

      if (!$expect_database || !$actual_database) {
        // If there's a real issue here, skip this stuff.
        continue;
      }

      $issues = array();
      if ($database->hasIssue($issue_charset)) {
        $issues[] = $issue_charset;
      }
      if ($database->hasIssue($issue_collation)) {
        $issues[] = $issue_collation;
      }

      if ($issues) {
        $adjustments[] = array(
          'kind' => 'database',
          'database' => $database_name,
          'issues' => $issues,
          'charset' => $expect_database->getCharacterSet(),
          'collation' => $expect_database->getCollation(),
        );
      }

      foreach ($database->getTables() as $table_name => $table) {
        $expect_table = $expect_database->getTable($table_name);
        $actual_table = $actual_database->getTable($table_name);

        if (!$expect_table || !$actual_table) {
          continue;
        }

        $issues = array();
        if ($table->hasIssue($issue_collation)) {
          $issues[] = $issue_collation;
        }

        if ($issues) {
          $adjustments[] = array(
            'kind' => 'table',
            'database' => $database_name,
            'table' => $table_name,
            'issues' => $issues,
            'collation' => $expect_table->getCollation(),
          );
        }
      }
    }

    return $adjustments;
  }


}
