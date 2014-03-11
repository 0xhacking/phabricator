<?php

final class DifferentialTestPlanFieldSpecification
  extends DifferentialFieldSpecification {

  private $plan = '';

  // NOTE: This means "uninitialized".
  private $error = false;

  public function shouldAppearOnEdit() {
    return false;
  }

  protected function didSetRevision() {
    $this->plan = (string)$this->getRevision()->getTestPlan();
  }

  public function setValueFromRequest(AphrontRequest $request) {
    $this->plan = $request->getStr('testplan');
    $this->error = null;
    return $this;
  }

  public function renderEditControl() {
    if ($this->error === false) {
      if ($this->isRequired()) {
        $this->error = true;
      } else {
        $this->error = null;
      }
    }

    return id(new PhabricatorRemarkupControl())
      ->setLabel('Test Plan')
      ->setName('testplan')
      ->setValue($this->plan)
      ->setError($this->error);
  }

  public function shouldExtractMentions() {
    return true;
  }

  public function validateField() {
    if ($this->isRequired()) {
      if (!strlen($this->plan)) {
        $this->error = 'Required';
        throw new DifferentialFieldValidationException(
          "You must provide a test plan.");
      }
    }
  }

  public function shouldAppearOnCommitMessage() {
    return false;
  }

  public function getCommitMessageKey() {
    return 'testPlan';
  }

  public function setValueFromParsedCommitMessage($value) {
    $this->plan = (string)$value;
    return $this;
  }

  public function shouldOverwriteWhenCommitMessageIsEdited() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return 'Test Plan';
  }

  public function getSupportedCommitMessageLabels() {
    return array(
      'Test Plan',
      'Testplan',
      'Tested',
      'Tests',
    );
  }


  public function renderValueForCommitMessage($is_edit) {
    return $this->plan;
  }

  public function parseValueFromCommitMessage($value) {
    return $value;
  }

  public function shouldAddToSearchIndex() {
    return true;
  }

  public function getValueForSearchIndex() {
    return $this->plan;
  }

  public function getKeyForSearchIndex() {
    return 'tpln';
  }

  private function isRequired() {
    return PhabricatorEnv::getEnvConfig('differential.require-test-plan-field');
  }



}
