<?php

abstract class HeraldAdapter {

  const FIELD_TITLE                  = 'title';
  const FIELD_BODY                   = 'body';
  const FIELD_AUTHOR                 = 'author';
  const FIELD_REVIEWER               = 'reviewer';
  const FIELD_REVIEWERS              = 'reviewers';
  const FIELD_CC                     = 'cc';
  const FIELD_TAGS                   = 'tags';
  const FIELD_DIFF_FILE              = 'diff-file';
  const FIELD_DIFF_CONTENT           = 'diff-content';
  const FIELD_REPOSITORY             = 'repository';
  const FIELD_RULE                   = 'rule';
  const FIELD_AFFECTED_PACKAGE       = 'affected-package';
  const FIELD_AFFECTED_PACKAGE_OWNER = 'affected-package-owner';

  const CONDITION_CONTAINS        = 'contains';
  const CONDITION_NOT_CONTAINS    = '!contains';
  const CONDITION_IS              = 'is';
  const CONDITION_IS_NOT          = '!is';
  const CONDITION_IS_ANY          = 'isany';
  const CONDITION_IS_NOT_ANY      = '!isany';
  const CONDITION_INCLUDE_ALL     = 'all';
  const CONDITION_INCLUDE_ANY     = 'any';
  const CONDITION_INCLUDE_NONE    = 'none';
  const CONDITION_IS_ME           = 'me';
  const CONDITION_IS_NOT_ME       = '!me';
  const CONDITION_REGEXP          = 'regexp';
  const CONDITION_RULE            = 'conditions';
  const CONDITION_NOT_RULE        = '!conditions';
  const CONDITION_EXISTS          = 'exists';
  const CONDITION_NOT_EXISTS      = '!exists';
  const CONDITION_REGEXP_PAIR     = 'regexp-pair';

  abstract public function getPHID();
  abstract public function getHeraldName();
  abstract public function getHeraldTypeName();
  abstract public function getHeraldField($field_name);
  abstract public function applyHeraldEffects(array $effects);

  public function isEnabled() {
    return true;
  }

  /**
   * NOTE: You generally should not override this; it exists to support legacy
   * adapters which had hard-coded content types.
   */
  public function getAdapterContentType() {
    return get_class($this);
  }

  abstract public function getAdapterContentName();
  abstract public function getFields();

  public function getFieldNameMap() {
    return array(
      self::FIELD_TITLE => pht('Title'),
      self::FIELD_BODY => pht('Body'),
      self::FIELD_AUTHOR => pht('Author'),
      self::FIELD_REVIEWER => pht('Reviewer'),
      self::FIELD_REVIEWERS => pht('Reviewers'),
      self::FIELD_CC => pht('CCs'),
      self::FIELD_TAGS => pht('Tags'),
      self::FIELD_DIFF_FILE => pht('Any changed filename'),
      self::FIELD_DIFF_CONTENT => pht('Any changed file content'),
      self::FIELD_REPOSITORY => pht('Repository'),
      self::FIELD_RULE => pht('Another Herald rule'),
      self::FIELD_AFFECTED_PACKAGE => pht('Any affected package'),
      self::FIELD_AFFECTED_PACKAGE_OWNER =>
        pht("Any affected package's owner"),
    );
  }

  public function getConditionNameMap() {
    return array(
      self::CONDITION_CONTAINS        => pht('contains'),
      self::CONDITION_NOT_CONTAINS    => pht('does not contain'),
      self::CONDITION_IS              => pht('is'),
      self::CONDITION_IS_NOT          => pht('is not'),
      self::CONDITION_IS_ANY          => pht('is any of'),
      self::CONDITION_IS_NOT_ANY      => pht('is not any of'),
      self::CONDITION_INCLUDE_ALL     => pht('include all of'),
      self::CONDITION_INCLUDE_ANY     => pht('include any of'),
      self::CONDITION_INCLUDE_NONE    => pht('include none of'),
      self::CONDITION_IS_ME           => pht('is myself'),
      self::CONDITION_IS_NOT_ME       => pht('is not myself'),
      self::CONDITION_REGEXP          => pht('matches regexp'),
      self::CONDITION_RULE            => pht('matches:'),
      self::CONDITION_NOT_RULE        => pht('does not match:'),
      self::CONDITION_EXISTS          => pht('exists'),
      self::CONDITION_NOT_EXISTS      => pht('does not exist'),
      self::CONDITION_REGEXP_PAIR     => pht('matches regexp pair'),
    );
  }

  public function getConditionsForField($field) {
    switch ($field) {
      case self::FIELD_TITLE:
      case self::FIELD_BODY:
        return array(
          self::CONDITION_CONTAINS,
          self::CONDITION_NOT_CONTAINS,
          self::CONDITION_IS,
          self::CONDITION_IS_NOT,
          self::CONDITION_REGEXP,
        );
      case self::FIELD_AUTHOR:
      case self::FIELD_REPOSITORY:
      case self::FIELD_REVIEWER:
        return array(
          self::CONDITION_IS_ANY,
          self::CONDITION_IS_NOT_ANY,
        );
      case self::FIELD_TAGS:
      case self::FIELD_REVIEWERS:
      case self::FIELD_CC:
        return array(
          self::CONDITION_INCLUDE_ALL,
          self::CONDITION_INCLUDE_ANY,
          self::CONDITION_INCLUDE_NONE,
        );
      case self::FIELD_DIFF_FILE:
        return array(
          self::CONDITION_CONTAINS,
          self::CONDITION_REGEXP,
        );
      case self::FIELD_DIFF_CONTENT:
        return array(
          self::CONDITION_CONTAINS,
          self::CONDITION_REGEXP,
          self::CONDITION_REGEXP_PAIR,
        );
      case self::FIELD_RULE:
        return array(
          self::CONDITION_RULE,
          self::CONDITION_NOT_RULE,
        );
      case self::FIELD_AFFECTED_PACKAGE:
      case self::FIELD_AFFECTED_PACKAGE_OWNER:
        return array(
          self::CONDITION_INCLUDE_ANY,
          self::CONDITION_INCLUDE_NONE,
        );
      default:
        throw new Exception(
          "This adapter does not define conditions for field '{$field}'!");
    }
  }


  public static function applyFlagEffect(HeraldEffect $effect, $phid) {
    $color = $effect->getTarget();

    // TODO: Silly that we need to load this again here.
    $rule = id(new HeraldRule())->load($effect->getRuleID());
    $user = id(new PhabricatorUser())->loadOneWhere(
      'phid = %s',
      $rule->getAuthorPHID());

    $flag = PhabricatorFlagQuery::loadUserFlag($user, $phid);
    if ($flag) {
      return new HeraldApplyTranscript(
        $effect,
        false,
        pht('Object already flagged.'));
    }

    $handle = PhabricatorObjectHandleData::loadOneHandle(
      $phid,
      $user);

    $flag = new PhabricatorFlag();
    $flag->setOwnerPHID($user->getPHID());
    $flag->setType($handle->getType());
    $flag->setObjectPHID($handle->getPHID());

    // TOOD: Should really be transcript PHID, but it doesn't exist yet.
    $flag->setReasonPHID($user->getPHID());

    $flag->setColor($color);
    $flag->setNote(
      pht('Flagged by Herald Rule "%s".', $rule->getName()));
    $flag->save();

    return new HeraldApplyTranscript(
      $effect,
      true,
      pht('Added flag.'));
  }

  public static function getAllAdapters() {
    static $adapters;
    if (!$adapters) {
      $adapters = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();
    }
    return $adapters;
  }

  public static function getAllEnabledAdapters() {
    $adapters = self::getAllAdapters();
    foreach ($adapters as $key => $adapter) {
      if (!$adapter->isEnabled()) {
        unset($adapters[$key]);
      }
    }
    return $adapters;
  }

  public static function getAdapterForContentType($content_type) {
    $adapters = self::getAllAdapters();

    foreach ($adapters as $adapter) {
      if ($adapter->getAdapterContentType() == $content_type) {
        return $adapter;
      }
    }

    throw new Exception(
      pht(
        'No adapter exists for Herald content type "%s".',
        $content_type));
  }



}

