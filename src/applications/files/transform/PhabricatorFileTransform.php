<?php

abstract class PhabricatorFileTransform extends Phobject {

  abstract public function getTransformName();
  abstract public function getTransformKey();
  abstract public function canApplyTransform(PhabricatorFile $file);
  abstract public function applyTransform(PhabricatorFile $file);

  public function generateTransforms() {
    return array($this);
  }

  public static function getAllTransforms() {
    static $map;

    if ($map === null) {
      $xforms = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      $result = array();
      foreach ($xforms as $xform_template) {
        foreach ($xform_template->generateTransforms() as $xform) {
          $key = $xform->getTransformKey();
          if (isset($result[$key])) {
            throw new Exception(
              pht(
                'Two %s objects define the same transform key ("%s"), but '.
                'each transform must have a unique key.',
                __CLASS__,
                $key));
          }
          $result[$key] = $xform;
        }
      }

      $map = $result;
    }

    return $map;
  }

}
