<?php

/**
 * @group maniphest
 */
abstract class ManiphestAuxiliaryFieldSpecification
  extends ManiphestCustomField
  implements PhabricatorMarkupInterface {

  const RENDER_TARGET_HTML  = 'html';
  const RENDER_TARGET_TEXT  = 'text';

  private $label;
  private $auxiliaryKey;
  private $caption;
  private $value;
  private $markupEngine;
  private $handles;

  // TODO: Remove; obsolete.
  public function getTask() {
    return $this->getObject();
  }

  // TODO: Remove; obsolete.
  public function getUser() {
    return $this->getViewer();
  }

  public function setLabel($val) {
    $this->label = $val;
    return $this;
  }

  public function getLabel() {
    return $this->label;
  }

  public function setAuxiliaryKey($val) {
    $this->auxiliaryKey = $val;
    return $this;
  }

  public function getAuxiliaryKey() {
    return 'std:maniphest:'.$this->auxiliaryKey;
  }

  public function setCaption($val) {
    $this->caption = $val;
    return $this;
  }

  public function getCaption() {
    return $this->caption;
  }

  public function setValue($val) {
    $this->value = $val;
    return $this;
  }

  public function getValue() {
    return $this->value;
  }

  public function validate() {
    return true;
  }

  public function isRequired() {
    return false;
  }

  public function setType($val) {
    $this->type = $val;
    return $this;
  }

  public function getType() {
    return $this->type;
  }

  public function renderControl() {
    return null;
  }

  public function renderForDetailView() {
    return $this->getValue();
  }


  /**
   * Render a verb to appear in email titles when a transaction involving this
   * field occurs. Specifically, Maniphest emails are formatted like this:
   *
   *   [Maniphest] [Verb Here] TNNN: Task title here
   *                ^^^^^^^^^
   *
   * You should optionally return a title-case verb or short phrase like
   * "Created", "Retitled", "Closed", "Resolved", "Commented On",
   * "Lowered Priority", etc., which describes the transaction.
   *
   * @param ManiphestTransaction The transaction which needs description.
   * @return string|null A short description of the transaction.
   */
  public function renderTransactionEmailVerb(
    ManiphestTransaction $transaction) {
    return null;
  }


  /**
   * Render a short description of the transaction, to appear above comments
   * in the Maniphest transaction log. The string will be rendered after the
   * acting user's name. Examples are:
   *
   *    added a comment
   *    added alincoln to CC
   *    claimed this task
   *    created this task
   *    closed this task out of spite
   *
   * You should return a similar string, describing the transaction.
   *
   * Note the ##$target## parameter -- Maniphest needs to render transaction
   * descriptions for different targets, like web and email. This method will
   * be called with a ##ManiphestAuxiliaryFieldSpecification::RENDER_TARGET_*##
   * constant describing the intended target.
   *
   * @param ManiphestTransaction The transaction which needs description.
   * @param const Constant describing the rendering target (e.g., html or text).
   * @return string|null Description of the transaction.
   */
  public function renderTransactionDescription(
    ManiphestTransaction $transaction,
    $target) {
    return 'updated a custom field';
  }

  public function getRequiredHandlePHIDs() {
    return array();
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = array_select_keys(
      $handles,
      $this->getRequiredHandlePHIDs());
    return $this;
  }

  public function getHandle($phid) {
    if (empty($this->handles[$phid])) {
      throw new Exception(
        "Field is requesting a handle ('{$phid}') it did not require.");
    }
    return $this->handles[$phid];
  }

  public function getMarkupFields() {
    return array();
  }

  public function setMarkupEngine(PhabricatorMarkupEngine $engine) {
    $this->markupEngine = $engine;
    return $this;
  }

  public function getMarkupEngine() {
    return $this->markupEngine;
  }


/* -(  PhabricatorMarkupInterface  )----------------------------------------- */


  public function getMarkupFieldKey($field) {
    $hash = PhabricatorHash::digestForIndex($this->getMarkupText($field));
    return 'maux:'.$this->getAuxiliaryKey().':'.$hash;
  }


  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newManiphestMarkupEngine();
  }


  public function getMarkupText($field) {
    return $this->getValue();
  }

  public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {
    return phutil_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      $output);
  }

  public function shouldUseMarkupCache($field) {
    return true;
  }


/* -(  API Compatibility With New Custom Fields  )--------------------------- */


  public function getFieldKey() {
    return $this->getAuxiliaryKey();
  }

  public function shouldAppearInEditView() {
    return true;
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function shouldUseStorage() {
    return true;
  }

  public function renderPropertyViewValue() {
    return $this->renderForDetailView();
  }

  public function renderPropertyViewLabel() {
    return $this->getLabel();
  }

  public function readValueFromRequest(AphrontRequest $request) {
    return $this->setValueFromRequest($request);
  }

  public static function writeLegacyAuxiliaryUpdates(
    ManiphestTask $task,
    array $map) {

    $table = new ManiphestCustomFieldStorage();
    $conn_w = $table->establishConnection('w');
    $update = array();
    $remove = array();

    foreach ($map as $key => $value) {
      $index = PhabricatorHash::digestForIndex($key);
      if ($value === null) {
        $remove[$index] = true;
      } else {
        $update[$index] = $value;
      }
    }

    if ($remove) {
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE objectPHID = %s AND fieldIndex IN (%Ls)',
        $table->getTableName(),
        $task->getPHID(),
        array_keys($remove));
    }

    if ($update) {
      $sql = array();
      foreach ($update as $index => $val) {
        $sql[] = qsprintf(
          $conn_w,
          '(%s, %s, %s)',
          $task->getPHID(),
          $index,
          $val);
      }
      queryfx(
        $conn_w,
        'INSERT INTO %T (objectPHID, fieldIndex, fieldValue)
          VALUES %Q ON DUPLICATE KEY
          UPDATE fieldValue = VALUES(fieldValue)',
        $table->getTableName(),
        implode(', ', $sql));
    }

  }

}
