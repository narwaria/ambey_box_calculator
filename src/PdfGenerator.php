<?php

namespace Drupal\ambey_box_calculator;

use Dompdf\Dompdf;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Extension\ThemeExtensionList;

/**
 * Generates quote PDFs from Twig templates.
 */
class PdfGenerator {

  public function __construct(
    protected RendererInterface $renderer,
    protected ThemeExtensionList $themeExtensionList,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function generateQuotePdf(array $data): string {
    $configured_logo = (string) ($this->configFactory->get('ambey_box_calculator.settings')->get('pdf_logo_path') ?? '');
    $logo_path = $this->resolveLogoPath($configured_logo);
    $data = $this->prepareQuoteData($data);

    $build = [
      '#theme' => 'quote_pdf',
      '#quote' => $data,
      '#logo' => $this->getLogoDataUri($logo_path),
    ];
    $html = (string) $this->renderer->renderRoot($build);

    $dompdf = new Dompdf(['isRemoteEnabled' => TRUE]);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4');
    $dompdf->render();
    return $dompdf->output();
  }

  /**
   * Prepares quote values for stable PDF rendering.
   */
  protected function prepareQuoteData(array $data): array {
    $data += [
      'name' => '',
      'email' => '',
      'phone' => '',
      'shape' => '',
      'shipping_zone' => '',
      'quantity' => 0,
      'board_grade' => '',
      'length' => '',
      'width' => '',
      'height' => '',
      'color' => '',
      'print_type' => '',
      'coating' => '',
      'base_price' => 0,
      'gst' => 0,
      'final_price' => 0,
      'total_without_gst' => 0,
      'total_with_gst' => 0,
    ];

    $data['shape'] = $this->getConfigEntityLabel('ambey_shape', (string) $data['shape']) ?: $this->humanizeValue((string) $data['shape']);
    $data['shipping_zone'] = $this->getConfigEntityLabel('ambey_shipping_zone', (string) $data['shipping_zone']) ?: $this->humanizeValue((string) $data['shipping_zone']);
    $data['color'] = $this->optionLabel((string) $data['color'], [
      'brown' => 'Brown Kraft',
      'white' => 'White',
    ]);
    $data['print_type'] = $this->optionLabel((string) $data['print_type'], [
      'none' => 'No Print',
      'single' => 'Single Colour',
      'multi' => 'Multi Colour (CMYK)',
    ]);
    $data['coating'] = $this->optionLabel((string) $data['coating'], [
      '' => 'None',
      'none' => 'None',
      'matte' => 'Matte',
      'gloss' => 'Gloss',
      'lamination' => 'Lamination',
    ]);

    $quantity = (int) $data['quantity'];
    $base_price = $this->normalizeAmount($data['base_price']);
    $final_price = $this->normalizeAmount($data['final_price']);
    $data['gst'] = $this->normalizeAmount($data['gst']);
    $data['base_price'] = $base_price;
    $data['final_price'] = $final_price;
    $data['total_without_gst'] = $this->normalizeAmount($data['total_without_gst']) ?: round($base_price * $quantity, 2);
    $data['total_with_gst'] = $this->normalizeAmount($data['total_with_gst']) ?: round($final_price * $quantity, 2);

    foreach (['base_price', 'gst', 'final_price', 'total_without_gst', 'total_with_gst'] as $field) {
      $data[$field] = PricingCalculator::formatIndianNumber($data[$field]);
    }

    return $data;
  }

  /**
   * Resolves the configured PDF logo path.
   */
  protected function resolveLogoPath(string $configured_logo): string {
    if ($configured_logo !== '') {
      return str_starts_with($configured_logo, '/')
        ? $configured_logo
        : DRUPAL_ROOT . '/' . ltrim($configured_logo, '/');
    }

    $global_logo = (string) ($this->configFactory->get('system.theme.global')->get('logo.path') ?? '');
    if ($global_logo !== '') {
      return str_starts_with($global_logo, '/')
        ? $global_logo
        : DRUPAL_ROOT . '/' . ltrim($global_logo, '/');
    }

    $default_theme = (string) ($this->configFactory->get('system.theme')->get('default') ?? '');
    $default_logo = $default_theme !== ''
      ? DRUPAL_ROOT . '/' . $this->themeExtensionList->getPath($default_theme) . '/logo.svg'
      : '';

    return file_exists($default_logo) ? $default_logo : DRUPAL_ROOT . '/core/misc/druplicon.png';
  }

  /**
   * Converts a local logo file to a Dompdf-friendly data URI.
   */
  protected function getLogoDataUri(string $logo_path): ?string {
    if (!is_file($logo_path) || !is_readable($logo_path)) {
      return NULL;
    }

    $contents = file_get_contents($logo_path);
    if ($contents === FALSE) {
      return NULL;
    }

    if (str_ends_with(strtolower($logo_path), '.svg')) {
      $embedded_image = $this->extractEmbeddedSvgImage($contents);
      if ($embedded_image !== NULL) {
        return $embedded_image;
      }
      return 'data:image/svg+xml;base64,' . base64_encode($contents);
    }

    $mime_type = mime_content_type($logo_path) ?: 'image/png';
    return 'data:' . $mime_type . ';base64,' . base64_encode($contents);
  }

  /**
   * Extracts a data URI from SVG logos that wrap an embedded raster image.
   */
  protected function extractEmbeddedSvgImage(string $svg): ?string {
    if (preg_match('/(?:href|xlink:href)="(data:image\/[^"]+)"/i', $svg, $matches)) {
      return $matches[1];
    }
    return NULL;
  }

  /**
   * Gets a config entity label by ID.
   */
  protected function getConfigEntityLabel(string $entity_type_id, string $id): ?string {
    if ($id === '') {
      return NULL;
    }

    $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
    return $entity ? (string) $entity->label() : NULL;
  }

  /**
   * Gets a fixed option label with a readable fallback.
   */
  protected function optionLabel(string $value, array $labels): string {
    return $labels[$value] ?? $this->humanizeValue($value);
  }

  /**
   * Converts machine values to readable labels.
   */
  protected function humanizeValue(string $value): string {
    $value = trim($value);
    return $value === '' ? 'None' : ucwords(str_replace(['_', '-'], ' ', $value));
  }

  /**
   * Converts raw, formatted, or empty amount values to a float.
   */
  protected function normalizeAmount(mixed $amount): float {
    if ($amount === NULL || $amount === '') {
      return 0.0;
    }

    return (float) preg_replace('/[^\d.\-]/', '', (string) $amount);
  }

}
