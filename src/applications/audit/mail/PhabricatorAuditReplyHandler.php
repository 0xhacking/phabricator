<?php

final class PhabricatorAuditReplyHandler extends PhabricatorMailReplyHandler {

  public function validateMailReceiver($mail_receiver) {
    if (!($mail_receiver instanceof PhabricatorRepositoryCommit)) {
      throw new Exception('Mail receiver is not a commit!');
    }
  }

  public function getPrivateReplyHandlerEmailAddress(
    PhabricatorObjectHandle $handle) {
    return $this->getDefaultPrivateReplyHandlerEmailAddress($handle, 'C');
  }

  public function getPublicReplyHandlerEmailAddress() {
    return $this->getDefaultPublicReplyHandlerEmailAddress('C');
  }

  protected function receiveEmail(PhabricatorMetaMTAReceivedMail $mail) {
    $commit = $this->getMailReceiver();
    $actor = $this->getActor();
    $message = $mail->getCleanTextBody();

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_EMAIL,
      array(
        'id' => $mail->getID(),
      ));

    // TODO: Support !raise, !accept, etc.

    $xactions = array();

    $xactions[] = id(new PhabricatorAuditTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new PhabricatorAuditTransactionComment())
          ->setCommitPHID($commit->getPHID())
          ->setContent($message));

    $editor = id(new PhabricatorAuditEditor())
      ->setActor($actor)
      ->setContentSource($content_source)
      ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs())
      ->setContinueOnMissingFields(true)
      ->applyTransactions($commit, $xactions);
  }

}
