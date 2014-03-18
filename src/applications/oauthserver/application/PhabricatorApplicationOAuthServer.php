<?php

final class PhabricatorApplicationOAuthServer extends PhabricatorApplication {

  public function getBaseURI() {
    return '/oauthserver/';
  }

  public function getShortDescription() {
    return pht('OAuth Provider');
  }

  public function getIconName() {
    return 'oauthserver';
  }

  public function getTitleGlyph() {
    return "\xE2\x99\x86";
  }

  public function getFlavorText() {
    return pht('Login with Phabricator');
  }

  public function getApplicationGroup() {
    return self::GROUP_UTILITIES;
  }

  public function isBeta() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/oauthserver/' => array(
        '' => 'PhabricatorOAuthServerConsoleController',
        'auth/'          => 'PhabricatorOAuthServerAuthController',
        'test/'          => 'PhabricatorOAuthServerTestController',
        'token/'         => 'PhabricatorOAuthServerTokenController',
        'client/' => array(
          ''                        => 'PhabricatorOAuthClientListController',
          'create/'                 => 'PhabricatorOAuthClientEditController',
          'delete/(?P<phid>[^/]+)/' => 'PhabricatorOAuthClientDeleteController',
          'edit/(?P<phid>[^/]+)/'   => 'PhabricatorOAuthClientEditController',
          'view/(?P<phid>[^/]+)/'   => 'PhabricatorOAuthClientViewController',
        ),
      ),
    );
  }

}
