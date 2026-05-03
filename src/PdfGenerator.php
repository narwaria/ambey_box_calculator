<?php

namespace Drupal\ambey_box_calculator;

use Dompdf\Dompdf;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Render\RendererInterface;

/**
 * Generates quote PDFs from Twig templates.
 */
class PdfGenerator {

  public function __construct(
    protected RendererInterface $renderer,
    protected ModuleExtensionList $moduleExtensionList,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  public function generateQuotePdf(array $data): string {
    $configured_logo = (string) ($this->configFactory->get('ambey_box_calculator.settings')->get('pdf_logo_path') ?? '');
    $logo_path = $configured_logo !== '' ? $configured_logo : DRUPAL_ROOT . '/core/misc/druplicon.png';
    if ($configured_logo !== '' && !str_starts_with($configured_logo, '/')) {
      $logo_path = DRUPAL_ROOT . '/' . ltrim($configured_logo, '/');
    }
    foreach (['base_price', 'gst', 'final_price', 'total_without_gst', 'total_with_gst'] as $field) {
      $data[$field] = PricingCalculator::formatIndianNumber($data[$field] ?? 0);
    }

    $build = [
      '#theme' => 'quote_pdf',
      '#quote' => $data,
      '#logo' => file_exists($logo_path) ? $logo_path : NULL,
    ];
    $html = (string) $this->renderer->renderRoot($build);

    $dompdf = new Dompdf(['isRemoteEnabled' => TRUE]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4');
    $dompdf->render();
    return $dompdf->output();
  }

}
