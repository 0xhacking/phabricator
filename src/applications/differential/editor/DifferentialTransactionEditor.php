<?php

final class DifferentialTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    $types[] = DifferentialTransaction::TYPE_ACTION;
    $types[] = DifferentialTransaction::TYPE_INLINE;

/*

    $types[] = DifferentialTransaction::TYPE_UPDATE;
*/

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        return $object->getViewPolicy();
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return $object->getEditPolicy();
      case DifferentialTransaction::TYPE_ACTION:
        return null;
      case DifferentialTransaction::TYPE_INLINE:
        return null;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case DifferentialTransaction::TYPE_ACTION:
        return $xaction->getNewValue();
      case DifferentialTransaction::TYPE_INLINE:
        return null;
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function transactionHasEffect(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case DifferentialTransaction::TYPE_INLINE:
        return $xaction->hasComment();
    }

    return parent::transactionHasEffect($object, $xaction);
  }


  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        $object->setViewPolicy($xaction->getNewValue());
        return;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        $object->setEditPolicy($xaction->getNewValue());
        return;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_COMMENT:
      case DifferentialTransaction::TYPE_INLINE:
        return;
      case PhabricatorTransactions::TYPE_EDGE:
        // TODO: When removing reviewers, we may be able to move the revision
        // to "Accepted".
        return;
      case DifferentialTransaction::TYPE_ACTION:
        // TODO: For now, we're just shipping these through without acting
        // on them.
        return null;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return;
      case PhabricatorTransactions::TYPE_SUBSCRIBERS:
      case PhabricatorTransactions::TYPE_EDGE:
      case PhabricatorTransactions::TYPE_COMMENT:
      case DifferentialTransaction::TYPE_ACTION:
      case DifferentialTransaction::TYPE_INLINE:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
    }

    return $errors;
  }

  protected function sortTransactions(array $xactions) {
    $head = array();
    $tail = array();

    // Move bare comments to the end, so the actions precede them.
    foreach ($xactions as $xaction) {
      $type = $xaction->getTransactionType();
      if ($type == DifferentialTransaction::TYPE_INLINE) {
        $tail[] = $xaction;
      } else {
        $head[] = $xaction;
      }
    }

    return array_values(array_merge($head, $tail));
  }

  protected function requireCapabilities(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
    }

    return parent::requireCapabilities($object, $xaction);
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();
    $phids[] = $object->getAuthorPHID();
    foreach ($object->getReviewerStatus() as $reviewer) {
      $phids[] = $reviewer->getReviewerPHID();
    }
    return $phids;
  }

  protected function getMailSubjectPrefix() {
    return PhabricatorEnv::getEnvConfig('metamta.differential.subject-prefix');
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new DifferentialReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $title = $object->getTitle();

    $original_title = $object->getOriginalTitle();

    $subject = "D{$id}: {$title}";
    $thread_topic = "D{$id}: {$original_title}";

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($subject)
      ->addHeader('Thread-Topic', $thread_topic);
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $body->addTextSection(
      pht('REVISION DETAIL'),
      PhabricatorEnv::getProductionURI('/D'.$object->getID()));

    return $body;
  }

  protected function supportsSearch() {
    return true;
  }

  protected function extractFilePHIDsFromCustomTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
    }

    return parent::extractFilePHIDsFromCustomTransaction($object, $xaction);
  }

}
