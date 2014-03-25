<?php

final class ManiphestTaskStatus extends ManiphestConstants {

  const STATUS_OPEN               = 0;
  const STATUS_CLOSED_RESOLVED    = 1;
  const STATUS_CLOSED_WONTFIX     = 2;
  const STATUS_CLOSED_INVALID     = 3;
  const STATUS_CLOSED_DUPLICATE   = 4;
  const STATUS_CLOSED_SPITE       = 5;

  public static function getTaskStatusMap() {
    $open = pht('Open');
    $resolved = pht('Resolved');
    $wontfix = pht('Wontfix');
    $invalid = pht('Invalid');
    $duplicate = pht('Duplicate');
    $spite = pht('Spite');

    $statuses = array(
      self::STATUS_OPEN                 => $open,
      self::STATUS_CLOSED_RESOLVED      => $resolved,
      self::STATUS_CLOSED_WONTFIX       => $wontfix,
      self::STATUS_CLOSED_INVALID       => $invalid,
      self::STATUS_CLOSED_DUPLICATE     => $duplicate,
      self::STATUS_CLOSED_SPITE         => $spite,
    );

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    if (!$is_serious) {
      $statuses[self::STATUS_CLOSED_SPITE] = pht('Spite');
    }

    return $statuses;
  }

  public static function getTaskStatusName($status) {
    return idx(self::getTaskStatusMap(), $status, pht('Unknown Status'));
  }

  public static function getTaskStatusFullName($status) {
    $open = pht('Open');
    $resolved = pht('Closed, Resolved');
    $wontfix = pht('Closed, Wontfix');
    $invalid = pht('Closed, Invalid');
    $duplicate = pht('Closed, Duplicate');
    $spite = pht('Closed, Spite');

    $map = array(
      self::STATUS_OPEN                 => $open,
      self::STATUS_CLOSED_RESOLVED      => $resolved,
      self::STATUS_CLOSED_WONTFIX       => $wontfix,
      self::STATUS_CLOSED_INVALID       => $invalid,
      self::STATUS_CLOSED_DUPLICATE     => $duplicate,
      self::STATUS_CLOSED_SPITE         => $spite,
    );
    return idx($map, $status, '???');
  }

  public static function renderFullDescription($status) {
    if (self::isOpenStatus($status)) {
      $color = 'status';
      $icon = 'oh-open';
    } else {
      $color = 'status-dark';
      $icon = 'oh-closed-dark';
    }

    $img = id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_STATUS)
      ->setSpriteIcon($icon);

    $tag = phutil_tag(
      'span',
      array(
        'class' => 'phui-header-'.$color.' plr',
      ),
      array(
        $img,
        self::getTaskStatusFullName($status),
      ));

    return $tag;
  }

  public static function getDefaultStatus() {
    return self::STATUS_OPEN;
  }

  public static function getDefaultClosedStatus() {
    return self::STATUS_CLOSED_RESOLVED;
  }

  public static function getDuplicateStatus() {
    return self::STATUS_CLOSED_DUPLICATE;
  }

  public static function getOpenStatusConstants() {
    return array(
      self::STATUS_OPEN,
    );
  }

  public static function getClosedStatusConstants() {
    $all = array_keys(self::getTaskStatusMap());
    $open = self::getOpenStatusConstants();
    return array_diff($all, $open);
  }

  public static function isOpenStatus($status) {
    foreach (self::getOpenStatusConstants() as $constant) {
      if ($status == $constant) {
        return true;
      }
    }
    return false;
  }

  public static function isClosedStatus($status) {
    return !self::isOpenStatus($status);
  }

  public static function getStatusActionName($status) {
    switch ($status) {
      case self::STATUS_CLOSED_SPITE:
        return pht('Spited');
    }
    return null;
  }

  public static function getStatusColor($status) {
    if (self::isOpenStatus($status)) {
      return 'green';
    }
    return 'black';
  }

  public static function getStatusIcon($status) {
    switch ($status) {
      case ManiphestTaskStatus::STATUS_CLOSED_SPITE:
        return 'dislike';
      case ManiphestTaskStatus::STATUS_CLOSED_DUPLICATE:
        return 'delete';
    }
  }


  public static function getStatusPrefixMap() {
    return array(
      'resolve'       => self::STATUS_CLOSED_RESOLVED,
      'resolves'      => self::STATUS_CLOSED_RESOLVED,
      'resolved'      => self::STATUS_CLOSED_RESOLVED,
      'fix'           => self::STATUS_CLOSED_RESOLVED,
      'fixes'         => self::STATUS_CLOSED_RESOLVED,
      'fixed'         => self::STATUS_CLOSED_RESOLVED,
      'wontfix'       => self::STATUS_CLOSED_WONTFIX,
      'wontfixes'     => self::STATUS_CLOSED_WONTFIX,
      'wontfixed'     => self::STATUS_CLOSED_WONTFIX,
      'spite'         => self::STATUS_CLOSED_SPITE,
      'spites'        => self::STATUS_CLOSED_SPITE,
      'spited'        => self::STATUS_CLOSED_SPITE,
      'invalidate'    => self::STATUS_CLOSED_INVALID,
      'invaldiates'   => self::STATUS_CLOSED_INVALID,
      'invalidated'   => self::STATUS_CLOSED_INVALID,
      'close'         => self::STATUS_CLOSED_RESOLVED,
      'closes'        => self::STATUS_CLOSED_RESOLVED,
      'closed'        => self::STATUS_CLOSED_RESOLVED,
      'ref'           => null,
      'refs'          => null,
      'references'    => null,
      'cf.'           => null,
    );
  }

  public static function getStatusSuffixMap() {
    return array(
      'as resolved'   => self::STATUS_CLOSED_RESOLVED,
      'as fixed'      => self::STATUS_CLOSED_RESOLVED,
      'as wontfix'    => self::STATUS_CLOSED_WONTFIX,
      'as spite'      => self::STATUS_CLOSED_SPITE,
      'out of spite'  => self::STATUS_CLOSED_SPITE,
      'as invalid'    => self::STATUS_CLOSED_INVALID,
    );
  }


}
