<?php

namespace Drupal\ambey_box_calculator\Controller;

use Drupal\ambey_box_calculator\PricingCalculator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

class QuoteConfirmationController extends ControllerBase {
  public function view(string $quote_id): array {
    $quote = \Drupal::service('ambey_box_calculator.quote')->loadQuoteByUuid($quote_id);
    if (!$quote) {
      return ['#markup' => '<div class="text-red-600">Quote not found.</div>'];
    }
    $quote['total_with_gst'] = PricingCalculator::formatIndianNumber($quote['total_with_gst'] ?? 0);
    $download_url = Url::fromRoute('ambey_box_calculator.download', ['quote_id' => $quote['uuid']])->toString();
    $quote['download_url'] = $download_url;

    return [
      '#theme' => 'quote_confirmation',
      '#quote' => $quote,
      '#download_url' => $download_url,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }
}
