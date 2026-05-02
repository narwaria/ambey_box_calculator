<?php

namespace Drupal\ambey_box_calculator\Controller;

use Drupal\Core\Controller\ControllerBase;

class QuoteConfirmationController extends ControllerBase {
  public function view(string $quote_id): array {
    $quote = \Drupal::service('ambey_box_calculator.quote')->loadQuoteByUuid($quote_id);
    if (!$quote) {
      return ['#markup' => '<div class="text-red-600">Quote not found.</div>'];
    }
    return ['#theme' => 'quote_confirmation', '#quote' => $quote];
  }
}
