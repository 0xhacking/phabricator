<?php

final class HarbormasterBuild extends HarbormasterDAO
  implements PhabricatorPolicyInterface {

  protected $buildablePHID;
  protected $buildPlanPHID;
  protected $buildStatus;

  private $buildable = self::ATTACHABLE;
  private $buildPlan = self::ATTACHABLE;
  private $unprocessedCommands = self::ATTACHABLE;

  /**
   * Not currently being built.
   */
  const STATUS_INACTIVE = 'inactive';

  /**
   * Pending pick up by the Harbormaster daemon.
   */
  const STATUS_PENDING = 'pending';

  /**
   * Waiting for a resource to be allocated (not yet relevant).
   */
  const STATUS_WAITING = 'waiting';

  /**
   * Current building the buildable.
   */
  const STATUS_BUILDING = 'building';

  /**
   * The build has passed.
   */
  const STATUS_PASSED = 'passed';

  /**
   * The build has failed.
   */
  const STATUS_FAILED = 'failed';

  /**
   * The build encountered an unexpected error.
   */
  const STATUS_ERROR = 'error';

  /**
   * The build has been stopped.
   */
  const STATUS_STOPPED = 'stopped';

  public static function initializeNewBuild(PhabricatorUser $actor) {
    return id(new HarbormasterBuild())
      ->setBuildStatus(self::STATUS_INACTIVE);
  }

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      HarbormasterPHIDTypeBuild::TYPECONST);
  }

  public function attachBuildable(HarbormasterBuildable $buildable) {
    $this->buildable = $buildable;
    return $this;
  }

  public function getBuildable() {
    return $this->assertAttached($this->buildable);
  }

  public function getName() {
    if ($this->getBuildPlan()) {
      return $this->getBuildPlan()->getName();
    }
    return pht('Build');
  }

  public function attachBuildPlan(
    HarbormasterBuildPlan $build_plan = null) {
    $this->buildPlan = $build_plan;
    return $this;
  }

  public function getBuildPlan() {
    return $this->assertAttached($this->buildPlan);
  }

  public function isBuilding() {
    return $this->getBuildStatus() === self::STATUS_PENDING ||
      $this->getBuildStatus() === self::STATUS_WAITING ||
      $this->getBuildStatus() === self::STATUS_BUILDING;
  }

  public function createLog(
    HarbormasterBuildTarget $build_target,
    $log_source,
    $log_type) {

    $log = HarbormasterBuildLog::initializeNewBuildLog($build_target)
      ->setLogSource($log_source)
      ->setLogType($log_type)
      ->save();

    return $log;
  }

  public function createArtifact(
    HarbormasterBuildTarget $build_target,
    $artifact_key,
    $artifact_type) {

    $artifact =
      HarbormasterBuildArtifact::initializeNewBuildArtifact($build_target);
    $artifact->setArtifactKey($this->getPHID(), $artifact_key);
    $artifact->setArtifactType($artifact_type);
    $artifact->save();
    return $artifact;
  }

  public function loadArtifact($name) {
    $artifact = id(new HarbormasterBuildArtifactQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withArtifactKeys(
        $this->getPHID(),
        array($name))
      ->executeOne();
    if ($artifact === null) {
      throw new Exception("Artifact not found!");
    }
    return $artifact;
  }

  public function retrieveVariablesFromBuild() {
    $results = array(
      'buildable.diff' => null,
      'buildable.revision' => null,
      'buildable.commit' => null,
      'repository.callsign' => null,
      'repository.vcs' => null,
      'repository.uri' => null,
      'step.timestamp' => null,
      'build.id' => null);

    $buildable = $this->getBuildable();
    $object = $buildable->getBuildableObject();

    $repo = null;
    if ($object instanceof DifferentialDiff) {
      $results['buildable.diff'] = $object->getID();
      $revision = $object->getRevision();
      $results['buildable.revision'] = $revision->getID();
      $repo = $revision->getRepository();
    } else if ($object instanceof PhabricatorRepositoryCommit) {
      $results['buildable.commit'] = $object->getCommitIdentifier();
      $repo = $object->getRepository();
    }

    if ($repo) {
      $results['repository.callsign'] = $repo->getCallsign();
      $results['repository.vcs'] = $repo->getVersionControlSystem();
      $results['repository.uri'] = $repo->getPublicRemoteURI();
    }

    $results['step.timestamp'] = time();
    $results['build.id'] = $this->getID();

    return $results;
  }

  public static function getAvailableBuildVariables() {
    return array(
      'buildable.diff' =>
        pht('The differential diff ID, if applicable.'),
      'buildable.revision' =>
        pht('The differential revision ID, if applicable.'),
      'buildable.commit' => pht('The commit identifier, if applicable.'),
      'repository.callsign' =>
        pht('The callsign of the repository in Phabricator.'),
      'repository.vcs' =>
        pht('The version control system, either "svn", "hg" or "git".'),
      'repository.uri' =>
        pht('The URI to clone or checkout the repository from.'),
      'step.timestamp' => pht('The current UNIX timestamp.'),
      'build.id' => pht('The ID of the current build.'));
  }

  public function isComplete() {
    switch ($this->getBuildStatus()) {
      case self::STATUS_PASSED:
      case self::STATUS_FAILED:
      case self::STATUS_ERROR:
      case self::STATUS_STOPPED:
        return true;
    }

    return false;
  }

  public function isStopped() {
    return ($this->getBuildStatus() == self::STATUS_STOPPED);
  }


/* -(  Build Commands  )----------------------------------------------------- */


  public function getUnprocessedCommands() {
    return $this->assertAttached($this->unprocessedCommands);
  }

  public function attachUnprocessedCommands(array $commands) {
    $this->unprocessedCommands = $commands;
    return $this;
  }

  public function hasWaitingCommand($command_name) {
    foreach ($this->getUnprocessedCommands() as $command_object) {
      if ($command_object->getCommand() == $command_name) {
        return true;
      }
    }
    return false;
  }

  public function canRestartBuild() {
    return !$this->isRestarting();
  }

  public function canStopBuild() {
    return !$this->isComplete() &&
           !$this->isStopped() &&
           !$this->isStopping();
  }

  public function canResumeBuild() {
    return $this->isStopped() &&
           !$this->isResuming();
  }

  public function isStopping() {
    return $this->hasWaitingCommand(HarbormasterBuildCommand::COMMAND_STOP);
  }

  public function isResuming() {
    return $this->hasWaitingCommand(HarbormasterBuildCommand::COMMAND_RESUME);
  }

  public function isRestarting() {
    return $this->hasWaitingCommand(HarbormasterBuildCommand::COMMAND_RESTART);
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return $this->getBuildable()->getPolicy($capability);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return $this->getBuildable()->hasAutomaticCapability(
      $capability,
      $viewer);
  }

  public function describeAutomaticCapability($capability) {
    return pht(
      'Users must be able to see a buildable to view its build plans.');
  }

}
