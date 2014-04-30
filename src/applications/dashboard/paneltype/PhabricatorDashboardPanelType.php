<?php

abstract class PhabricatorDashboardPanelType extends Phobject {

  abstract public function getPanelTypeKey();
  abstract public function getPanelTypeName();
  abstract public function getPanelTypeDescription();

  public static function getAllPanelTypes() {
    static $types;

    if ($types === null) {
      $objects = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      $map = array();
      foreach ($objects as $object) {
        $key = $object->getPanelTypeKey();
        if (!empty($map[$key])) {
          $this_class = get_class($object);
          $that_class = get_class($map[$key]);
          throw new Exception(
            pht(
              'Two dashboard panels (of classes "%s" and "%s") have the '.
              'same panel type key ("%s"). Each panel type must have a '.
              'unique panel type key.',
              $this_class,
              $that_class,
              $key));
        }

        $map[$key] = $object;
      }

      $types = $map;
    }

    return $types;
  }

}
