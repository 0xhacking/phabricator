<?php

final class PhabricatorRepositoryTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_ACTIVATE     = 'repo:activate';
  const TYPE_NAME         = 'repo:name';
  const TYPE_DESCRIPTION  = 'repo:description';
  const TYPE_ENCODING     = 'repo:encoding';
  const TYPE_DEFAULT_BRANCH = 'repo:default-branch';
  const TYPE_TRACK_ONLY = 'repo:track-only';
  const TYPE_AUTOCLOSE_ONLY = 'repo:autoclose-only';

  public function getApplicationName() {
    return 'repository';
  }

  public function getApplicationTransactionType() {
    return PhabricatorRepositoryPHIDTypeRepository::TYPECONST;
  }

  public function getApplicationTransactionCommentObject() {
    return null;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    switch ($this->getTransactionType()) {
      case self::TYPE_ACTIVATE:
        if ($new) {
          return pht(
            '%s activated this repository.',
            $this->renderHandleLink($author_phid));
        } else {
          return pht(
            '%s deactivated this repository.',
            $this->renderHandleLink($author_phid));
        }
      case self::TYPE_NAME:
        return pht(
          '%s renamed this repository from "%s" to "%s".',
          $this->renderHandleLink($author_phid),
          $old,
          $new);
      case self::TYPE_DESCRIPTION:
        return pht(
          '%s updated the description of this repository.',
          $this->renderHandleLink($author_phid));
      case self::TYPE_ENCODING:
        if (strlen($old) && !strlen($new)) {
          return pht(
            '%s removed the "%s" encoding configured for this repository.',
            $this->renderHandleLink($author_phid),
            $old);
        } else if (strlen($new) && !strlen($old)) {
          return pht(
            '%s set the encoding for this repository to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else {
          return pht(
            '%s changed the repository encoding from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
      case self::TYPE_DEFAULT_BRANCH:
        if (!strlen($new)) {
          return pht(
            '%s removed "%s" as the default branch.',
            $this->renderHandleLink($author_phid),
            $old);
        } else if (!strlen($old)) {
          return pht(
            '%s set the default branch to "%s".',
            $this->renderHandleLink($author_phid),
            $new);
        } else {
          return pht(
            '%s changed the default branch from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            $old,
            $new);
        }
        break;
      case self::TYPE_TRACK_ONLY:
        if (!$new) {
          return pht(
            '%s set this repository to track all branches.',
            $this->renderHandleLink($author_phid));
        } else if (!$old) {
          return pht(
            '%s set this repository to track branches: %s.',
            $this->renderHandleLink($author_phid),
            implode(', ', $new));
        } else {
          return pht(
            '%s changed track branches from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            implode(', ', $old),
            implode(', ', $new));
        }
        break;
      case self::TYPE_AUTOCLOSE_ONLY:
        if (!$new) {
          return pht(
            '%s set this repository to autoclose on all branches.',
            $this->renderHandleLink($author_phid));
        } else if (!$old) {
          return pht(
            '%s set this repository to autoclose on branches: %s.',
            $this->renderHandleLink($author_phid),
            implode(', ', $new));
        } else {
          return pht(
            '%s changed autoclose branches from "%s" to "%s".',
            $this->renderHandleLink($author_phid),
            implode(', ', $old),
            implode(', ', $new));
        }
        break;
    }

    return parent::getTitle();
  }

  public function hasChangeDetails() {
    switch ($this->getTransactionType()) {
      case self::TYPE_DESCRIPTION:
        return true;
    }
    return parent::hasChangeDetails();
  }

  public function renderChangeDetails(PhabricatorUser $viewer) {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $view = id(new PhabricatorApplicationTransactionTextDiffDetailView())
      ->setUser($viewer)
      ->setOldText($old)
      ->setNewText($new);

    return $view->render();
  }


}

