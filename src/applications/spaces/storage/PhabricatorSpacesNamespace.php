<?php

final class PhabricatorSpacesNamespace
  extends PhabricatorSpacesDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorApplicationTransactionInterface,
    PhabricatorDestructibleInterface {

  protected $namespaceName;
  protected $viewPolicy;
  protected $editPolicy;
  protected $isDefaultNamespace;

  public static function initializeNewNamespace(PhabricatorUser $actor) {
    $app = id(new PhabricatorApplicationQuery())
      ->setViewer($actor)
      ->withClasses(array('PhabricatorSpacesApplication'))
      ->executeOne();

    $view_policy = $app->getPolicy(
      PhabricatorSpacesCapabilityDefaultView::CAPABILITY);
    $edit_policy = $app->getPolicy(
      PhabricatorSpacesCapabilityDefaultEdit::CAPABILITY);

    return id(new PhabricatorSpacesNamespace())
      ->setIsDefaultNamespace(null)
      ->setViewPolicy($view_policy)
      ->setEditPolicy($edit_policy);
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'namespaceName' => 'text255',
        'isDefaultNamespace' => 'bool?',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_default' => array(
          'columns' => array('isDefaultNamespace'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorSpacesNamespacePHIDType::TYPECONST);
  }

  public function getMonogram() {
    return 'S'.$this->getID();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
        return $this->getViewPolicy();
      case PhabricatorPolicyCapability::CAN_EDIT:
        return $this->getEditPolicy();
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhabricatorSpacesNamespaceEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhabricatorSpacesNamespaceTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {
    return $timeline;
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {
    $this->delete();
  }

}
