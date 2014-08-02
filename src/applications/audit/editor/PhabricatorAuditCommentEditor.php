<?php

final class PhabricatorAuditCommentEditor extends PhabricatorEditor {

  private $commit;
  private $attachInlineComments;
  private $noEmail;

  public function __construct(PhabricatorRepositoryCommit $commit) {
    $this->commit = $commit;
    return $this;
  }

  public function setAttachInlineComments($attach_inline_comments) {
    $this->attachInlineComments = $attach_inline_comments;
    return $this;
  }

  public function setNoEmail($no_email) {
    $this->noEmail = $no_email;
    return $this;
  }

  public function addComments(array $comments) {
    assert_instances_of($comments, 'PhabricatorAuditComment');

    $commit = $this->commit;
    $actor = $this->getActor();

    $other_comments = PhabricatorAuditComment::loadComments(
      $actor,
      $commit->getPHID());

    $inline_comments = array();
    if ($this->attachInlineComments) {
      $inline_comments = PhabricatorAuditInlineComment::loadDraftComments(
        $actor,
        $commit->getPHID());
    }

    // When an actor submits an audit comment, we update all the audit requests
    // they have authority over to reflect the most recent status. The general
    // idea here is that if audit has triggered for, e.g., several packages, but
    // a user owns all of them, they can clear the audit requirement in one go
    // without auditing the commit for each trigger.

    $audit_phids = self::loadAuditPHIDsForUser($actor);
    $audit_phids = array_fill_keys($audit_phids, true);

    $requests = id(new PhabricatorRepositoryAuditRequest())
      ->loadAllWhere(
        'commitPHID = %s',
        $commit->getPHID());

    // TODO: We should validate the action, currently we allow anyone to, e.g.,
    // close an audit if they muck with form parameters. I'll followup with this
    // and handle the no-effect cases (e.g., closing and already-closed audit).

    $actor_is_author = ($actor->getPHID() == $commit->getAuthorPHID());

    // Pick a meaningful action, if we have one.
    $action = PhabricatorAuditActionConstants::COMMENT;
    foreach ($comments as $comment) {
      switch ($comment->getAction()) {
        case PhabricatorAuditActionConstants::CLOSE:
        case PhabricatorAuditActionConstants::RESIGN:
        case PhabricatorAuditActionConstants::ACCEPT:
        case PhabricatorAuditActionConstants::CONCERN:
          $action = $comment->getAction();
          break;
      }
    }

    if ($action == PhabricatorAuditActionConstants::CLOSE) {
      if (!PhabricatorEnv::getEnvConfig('audit.can-author-close-audit')) {
        throw new Exception('Cannot Close Audit without enabling'.
          'audit.can-author-close-audit');
      }
      // "Close" means wipe out all the concerns.
      $concerned_status = PhabricatorAuditStatusConstants::CONCERNED;
      foreach ($requests as $request) {
        if ($request->getAuditStatus() == $concerned_status) {
          $request->setAuditStatus(PhabricatorAuditStatusConstants::CLOSED);
          $request->save();
        }
      }
    } else if ($action == PhabricatorAuditActionConstants::RESIGN) {
      // "Resign" has unusual rules for writing user rows, only affects the
      // user row (never package/project rows), and always affects the user
      // row (other actions don't, if they were able to affect a package/project
      // row).
      $actor_request = null;
      foreach ($requests as $request) {
        if ($request->getAuditorPHID() == $actor->getPHID()) {
          $actor_request = $request;
          break;
        }
      }
      if (!$actor_request) {
        $actor_request = id(new PhabricatorRepositoryAuditRequest())
          ->setCommitPHID($commit->getPHID())
          ->setAuditorPHID($actor->getPHID())
          ->setAuditReasons(array('Resigned'));
      }

      $actor_request
        ->setAuditStatus(PhabricatorAuditStatusConstants::RESIGNED)
        ->save();

      $requests[] = $actor_request;
    } else {
      $have_any_requests = false;
      foreach ($requests as $request) {
        if (empty($audit_phids[$request->getAuditorPHID()])) {
          continue;
        }

        $request_is_for_actor =
          ($request->getAuditorPHID() == $actor->getPHID());

        $have_any_requests = true;
        $new_status = null;
        switch ($action) {
          case PhabricatorAuditActionConstants::COMMENT:
          case PhabricatorAuditActionConstants::ADD_CCS:
          case PhabricatorAuditActionConstants::ADD_AUDITORS:
            // Commenting or adding cc's/auditors doesn't change status.
            break;
          case PhabricatorAuditActionConstants::ACCEPT:
            if (!$actor_is_author || $request_is_for_actor) {
              // When modifying your own commits, you act only on behalf of
              // yourself, not your packages/projects -- the idea being that
              // you can't accept your own commits.
              $new_status = PhabricatorAuditStatusConstants::ACCEPTED;
            }
            break;
          case PhabricatorAuditActionConstants::CONCERN:
            if (!$actor_is_author || $request_is_for_actor) {
              // See above.
              $new_status = PhabricatorAuditStatusConstants::CONCERNED;
            }
            break;
          default:
            throw new Exception("Unknown action '{$action}'!");
        }
        if ($new_status !== null) {
          $request->setAuditStatus($new_status);
          $request->save();
        }
      }

      // If the actor has no current authority over any audit trigger, make a
      // new one to represent their audit state.
      if (!$have_any_requests) {
        $new_status = null;
        switch ($action) {
          case PhabricatorAuditActionConstants::COMMENT:
          case PhabricatorAuditActionConstants::ADD_AUDITORS:
          case PhabricatorAuditActionConstants::ADD_CCS:
            break;
          case PhabricatorAuditActionConstants::ACCEPT:
            $new_status = PhabricatorAuditStatusConstants::ACCEPTED;
            break;
          case PhabricatorAuditActionConstants::CONCERN:
            $new_status = PhabricatorAuditStatusConstants::CONCERNED;
            break;
          case PhabricatorAuditActionConstants::CLOSE:
            // Impossible to reach this block with 'close'.
          default:
            throw new Exception("Unknown or invalid action '{$action}'!");
        }

        if ($new_status !== null) {
          $request = id(new PhabricatorRepositoryAuditRequest())
            ->setCommitPHID($commit->getPHID())
            ->setAuditorPHID($actor->getPHID())
            ->setAuditStatus($new_status)
            ->setAuditReasons(array('Voluntary Participant'))
            ->save();
          $requests[] = $request;
        }
      }
    }

    $auditors = array();

    foreach ($comments as $comment) {
      $meta = $comment->getMetadata();

      $auditor_phids = idx(
        $meta,
        PhabricatorAuditComment::METADATA_ADDED_AUDITORS,
        array());
      foreach ($auditor_phids as $phid) {
        $auditors[] = $phid;
      }
    }

    $requests_by_auditor = mpull($requests, null, 'getAuditorPHID');
    $requests_phids = array_keys($requests_by_auditor);

    $auditors = array_diff($auditors, $requests_phids);

    if ($auditors) {
      foreach ($auditors as $auditor_phid) {
        $audit_requested = PhabricatorAuditStatusConstants::AUDIT_REQUESTED;
        $requests[] = id (new PhabricatorRepositoryAuditRequest())
          ->setCommitPHID($commit->getPHID())
          ->setAuditorPHID($auditor_phid)
          ->setAuditStatus($audit_requested)
          ->setAuditReasons(
            array('Added by '.$actor->getUsername()))
          ->save();
      }
    }

    $commit->updateAuditStatus($requests);
    $commit->save();

    $commit->attachAudits($requests);

    // Convert old comments into real transactions and apply them with a
    // normal editor.

    $xactions = array();
    foreach ($comments as $comment) {
      $xactions[] = $comment->getTransactionForSave();
    }

    foreach ($inline_comments as $inline) {
      $xactions[] = id(new PhabricatorAuditTransaction())
        ->setTransactionType(PhabricatorAuditActionConstants::INLINE)
        ->attachComment($inline->getTransactionComment());
    }

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_LEGACY,
      array());

    $editor = id(new PhabricatorAuditEditor())
      ->setActor($actor)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true)
      ->setContentSource($content_source)
      ->setExcludeMailRecipientPHIDs($this->getExcludeMailRecipientPHIDs())
      ->setDisableEmail($this->noEmail)
      ->applyTransactions($commit, $xactions);

    $feed_dont_publish_phids = array();
    foreach ($requests as $request) {
      $status = $request->getAuditStatus();
      switch ($status) {
        case PhabricatorAuditStatusConstants::RESIGNED:
        case PhabricatorAuditStatusConstants::NONE:
        case PhabricatorAuditStatusConstants::AUDIT_NOT_REQUIRED:
          $feed_dont_publish_phids[$request->getAuditorPHID()] = 1;
          break;
        default:
          unset($feed_dont_publish_phids[$request->getAuditorPHID()]);
          break;
      }
    }
    $feed_dont_publish_phids = array_keys($feed_dont_publish_phids);

    $feed_phids = array_diff($requests_phids, $feed_dont_publish_phids);
    foreach ($comments as $comment) {
      $this->publishFeedStory($comment, $feed_phids);
    }
  }


  /**
   * Load the PHIDs for all objects the user has the authority to act as an
   * audit for. This includes themselves, and any packages they are an owner
   * of.
   */
  public static function loadAuditPHIDsForUser(PhabricatorUser $user) {
    $phids = array();

    // TODO: This method doesn't really use the right viewer, but in practice we
    // never issue this query of this type on behalf of another user and are
    // unlikely to do so in the future. This entire method should be refactored
    // into a Query class, however, and then we should use a proper viewer.

    // The user can audit on their own behalf.
    $phids[$user->getPHID()] = true;

    $owned_packages = id(new PhabricatorOwnersPackageQuery())
      ->setViewer($user)
      ->withOwnerPHIDs(array($user->getPHID()))
      ->execute();
    foreach ($owned_packages as $package) {
      $phids[$package->getPHID()] = true;
    }

    // The user can audit on behalf of all projects they are a member of.
    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($user)
      ->withMemberPHIDs(array($user->getPHID()))
      ->execute();
    foreach ($projects as $project) {
      $phids[$project->getPHID()] = true;
    }

    return array_keys($phids);
  }

  private function publishFeedStory(
    PhabricatorAuditComment $comment,
    array $more_phids) {

    $commit = $this->commit;
    $actor = $this->getActor();

    $related_phids = array_merge(
      array(
        $actor->getPHID(),
        $commit->getPHID(),
      ),
      $more_phids);

    id(new PhabricatorFeedStoryPublisher())
      ->setRelatedPHIDs($related_phids)
      ->setStoryAuthorPHID($actor->getPHID())
      ->setStoryTime(time())
      ->setStoryType(PhabricatorFeedStoryTypeConstants::STORY_AUDIT)
      ->setStoryData(
        array(
          'commitPHID'    => $commit->getPHID(),
          'action'        => $comment->getAction(),
          'content'       => $comment->getContent(),
        ))
      ->publish();
  }

}
