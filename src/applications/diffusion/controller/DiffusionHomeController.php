<?php

final class DiffusionHomeController extends DiffusionController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $shortcuts = id(new PhabricatorRepositoryShortcut())->loadAll();
    if ($shortcuts) {
      $shortcuts = msort($shortcuts, 'getSequence');

      $rows = array();
      foreach ($shortcuts as $shortcut) {
        $rows[] = array(
          $shortcut->getName(),
          $shortcut->getHref(),
          $shortcut->getDescription(),
        );
      }

      $list = new PHUIObjectItemListView();
      $list->setCards(true);
      $list->setFlush(true);
      foreach ($rows as $row) {
        $item = id(new PHUIObjectItemView())
            ->setHeader($row[0])
            ->setHref($row[1])
            ->setSubhead(($row[2] ? $row[2] : pht('No Description')));
        $list->addItem($item);
      }

      $shortcut_panel = id(new AphrontPanelView())
        ->setNoBackground(true)
        ->setHeader(pht('Shortcuts'))
        ->appendChild($list);

    } else {
      $shortcut_panel = null;
    }

    $repositories = id(new PhabricatorRepositoryQuery())
      ->setViewer($user)
      ->needCommitCounts(true)
      ->needMostRecentCommits(true)
      ->execute();

    foreach ($repositories as $key => $repo) {
      if (!$repo->isTracked()) {
        unset($repositories[$key]);
      }
    }
    $repositories = msort($repositories, 'getName');

    $rows = array();
    foreach ($repositories as $repository) {
      $id = $repository->getID();

      $size = $repository->getCommitCount();
      if ($size) {
        $size = hsprintf(
          '<a href="%s">%s</a>',
          DiffusionRequest::generateDiffusionURI(array(
            'callsign' => $repository->getCallsign(),
            'action' => 'history',
          )),
          pht('%s Commits', new PhutilNumber($size)));
      }

      $datetime = '';
      $most_recent_commit = $repository->getMostRecentCommit();
      if ($most_recent_commit) {
        $date = phabricator_date($most_recent_commit->getEpoch(), $user);
        $time = phabricator_time($most_recent_commit->getEpoch(), $user);
        $datetime = $date.' '.$time;
      }

      $rows[] = array(
        $repository->getName(),
        ('/diffusion/'.$repository->getCallsign().'/'),
        PhabricatorRepositoryType::getNameForRepositoryType(
          $repository->getVersionControlSystem()),
        $size ? $size : null,
        $most_recent_commit
          ? DiffusionView::linkCommit(
              $repository,
              $most_recent_commit->getCommitIdentifier(),
              $most_recent_commit->getSummary())
          : pht('No Commits'),
        $datetime
      );
    }

    $repository_tool_uri = PhabricatorEnv::getProductionURI('/repository/');
    $repository_tool     = phutil_tag('a',
      array(
       'href' => $repository_tool_uri,
      ),
      'repository tool');
    $preface = pht('This instance of Phabricator does not have any '.
                   'configured repositories.');
    if ($user->getIsAdmin()) {
      $no_repositories_txt = hsprintf(
        '%s %s',
        $preface,
        pht(
          'To setup one or more repositories, visit the %s.',
          $repository_tool));
    } else {
      $no_repositories_txt = hsprintf(
        '%s %s',
        $preface,
        pht(
          'Ask an administrator to setup one or more repositories '.
          'via the %s.',
          $repository_tool));
    }

    $list = new PHUIObjectItemListView();
    $list->setCards(true);
    $list->setFlush(true);
    foreach ($rows as $row) {
      $item = id(new PHUIObjectItemView())
          ->setHeader($row[0])
          ->setSubHead($row[4])
          ->setHref($row[1])
          ->addAttribute(($row[2] ? $row[2] : pht('No Information')))
          ->addAttribute(($row[3] ? $row[3] : pht('0 Commits')))
          ->addIcon('none', $row[5]);
      $list->addItem($item);
    }

    $list = id(new AphrontPanelView())
      ->setNoBackground(true)
      ->setHeader(pht('Repositories'))
      ->appendChild($list);


    $crumbs = $this->buildCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('All Repositories'))
        ->setHref($this->getApplicationURI()));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $shortcut_panel,
        $list,
      ),
      array(
        'title' => pht('Diffusion'),
        'device' => true,
      ));
  }

}
