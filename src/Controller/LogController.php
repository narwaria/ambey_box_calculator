<?php

namespace Drupal\ambey_box_calculator\Controller;

use Drupal\Core\Controller\ControllerBase;

class LogController extends ControllerBase {
  public function view(): array {
    $database = \Drupal::database();
    if (!$database->schema()->tableExists('watchdog')) {
      return ['#markup' => $this->t('Database logging table was not found. Enable the dblog module to view watchdog logs.')];
    }

    $results = $database->select('watchdog', 'w')
      ->fields('w')
      ->condition('type', 'ambey_box_calculator')
      ->orderBy('timestamp', 'DESC')
      ->range(0, 50)
      ->execute();

    $rows = [];
    foreach ($results as $row) {
      $rows[] = [date('d-m-Y H:i', (int) $row->timestamp), $row->message, $row->severity];
    }

    return [
      '#type' => 'table',
      '#header' => [$this->t('Date'), $this->t('Message'), $this->t('Severity')],
      '#rows' => $rows,
      '#empty' => $this->t('No calculator logs found.'),
    ];
  }
}
