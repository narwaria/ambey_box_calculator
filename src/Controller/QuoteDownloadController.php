<?php

namespace Drupal\ambey_box_calculator\Controller;

use Symfony\Component\HttpFoundation\Response;

class QuoteDownloadController {
  public function download(string $quote_id): Response {
    $quote = \Drupal::service('ambey_box_calculator.quote')->loadQuoteByUuid($quote_id);
    if (!$quote) {
      return new Response('Quote not found', 404);
    }
    $pdf = \Drupal::service('ambey_box_calculator.pdf')->generateQuotePdf($quote);
    return new Response($pdf, 200, [
      'Content-Type' => 'application/pdf',
      'Content-Disposition' => 'attachment; filename="quote-' . $quote['uuid'] . '.pdf"',
    ]);
  }
}
