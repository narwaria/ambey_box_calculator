<?php

namespace Drupal\ambey_box_calculator;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Pricing service for slab lookup, add-ons, shipping and GST.
 */
class PricingCalculator {

  private const REFERENCE_LENGTH = 10.0;
  private const REFERENCE_WIDTH = 8.0;
  private const REFERENCE_HEIGHT = 5.0;
  private const BOARD_GRADE_FACTORS = [
    '3ply' => 1.0,
    '5ply' => 1.75,
    '7ply' => 2.5,
  ];

  public function __construct(
    protected Connection $database,
    protected CacheBackendInterface $cache,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Gets per-box price with print, coating and shipping add-ons.
   */
  public function getPriceWithAddons(string $shape, string $board, int $quantity, string $print = 'none', string $coating = '', string $color = 'brown', string $shipping_zone = '', float $length = 0.0, float $width = 0.0, float $height = 0.0): float {
    if ($shape === '' || $board === '' || $quantity <= 0) {
      return 0.0;
    }

    $board_factor = $this->getBoardGradeFactor($board);
    $cache_key = 'price:' . hash('sha256', implode('|', [$shape, $board, $quantity, $print, $coating, $color, $shipping_zone, $length, $width, $height, $board_factor]));
    if ($cache = $this->cache->get($cache_key)) {
      return (float) $cache->data;
    }

    $row = $this->loadPricingRow($shape, $board, $quantity);
    if (!$row) {
      $this->logger->warning('No pricing slab found for Shape=@shape Board=@board Qty=@qty.', [
        '@shape' => $shape,
        '@board' => $board,
        '@qty' => $quantity,
      ]);
      return 0.0;
    }

    $dimension_multiplier = $this->calculateDimensionMultiplier(
      $length,
      $width,
      $height,
      (float) ($row['reference_length'] ?? self::REFERENCE_LENGTH),
      (float) ($row['reference_width'] ?? self::REFERENCE_WIDTH),
      (float) ($row['reference_height'] ?? self::REFERENCE_HEIGHT),
    );
    $price = (float) $row['price_per_box'] * $dimension_multiplier * $board_factor;

    if ($print === 'single') {
      $price += (float) $row['print_single_cost'];
    }
    elseif ($print === 'multi') {
      $price += (float) $row['print_multi_cost'];
    }

    if ($color === 'white' && $coating !== '') {
      $price += (float) $row['coating_cost'];
    }

    if ($shipping_zone !== '') {
      $price += $this->getShippingCost($shipping_zone);
    }

    $price = round($price, 2);
    $cache_lifetime = (int) ($this->settings()->get('price_cache_lifetime') ?? 3600);
    $this->cache->set($cache_key, $price, time() + $cache_lifetime, ['ambey_pricing']);

    $this->logger->debug('Price calculated: Shape=@shape Board=@board Qty=@qty Price=@price', [
      '@shape' => $shape,
      '@board' => $board,
      '@qty' => $quantity,
      '@price' => $price,
    ]);

    return $price;
  }

  public function calculateQuotePrices(array $data): array {
    $quantity = (int) ($data['quantity'] ?? 0);
    $settings = $this->settings();
    $pricing_row = $this->loadPricingRow(
      (string) ($data['shape'] ?? $settings->get('default_shape') ?? ''),
      (string) ($data['board_grade'] ?? $settings->get('default_board_grade') ?? ''),
      $quantity,
    );
    $base = $this->getPriceWithAddons(
      (string) ($data['shape'] ?? $settings->get('default_shape') ?? ''),
      (string) ($data['board_grade'] ?? $settings->get('default_board_grade') ?? ''),
      $quantity,
      (string) ($data['print'] ?? $data['print_type'] ?? $settings->get('default_print') ?? 'none'),
      (string) ($data['coating'] ?? ''),
      (string) ($data['color'] ?? $settings->get('default_color') ?? 'brown'),
      (string) ($data['shipping'] ?? $data['shipping_zone'] ?? $settings->get('default_shipping') ?? ''),
      (float) ($data['length'] ?? $settings->get('default_length') ?? 0),
      (float) ($data['width'] ?? $settings->get('default_width') ?? 0),
      (float) ($data['height'] ?? $settings->get('default_height') ?? 0),
    );
    $gst = $this->applyGST($base);
    $reference_length = (float) ($pricing_row['reference_length'] ?? self::REFERENCE_LENGTH);
    $reference_width = (float) ($pricing_row['reference_width'] ?? self::REFERENCE_WIDTH);
    $reference_height = (float) ($pricing_row['reference_height'] ?? self::REFERENCE_HEIGHT);

    return [
      'base_price' => $gst['base'],
      'gst' => $gst['gst'],
      'final_price' => $gst['final'],
      'total_without_gst' => round($gst['base'] * $quantity, 2),
      'total_with_gst' => round($gst['final'] * $quantity, 2),
      'box_area_sq_in' => $this->calculateBoxSurfaceArea(
        (float) ($data['length'] ?? $settings->get('default_length') ?? 0),
        (float) ($data['width'] ?? $settings->get('default_width') ?? 0),
        (float) ($data['height'] ?? $settings->get('default_height') ?? 0),
      ),
      'reference_length' => $reference_length,
      'reference_width' => $reference_width,
      'reference_height' => $reference_height,
      'reference_area_sq_in' => $this->calculateBoxSurfaceArea($reference_length, $reference_width, $reference_height),
      'board_grade_factor' => $this->getBoardGradeFactor((string) ($data['board_grade'] ?? $settings->get('default_board_grade') ?? '')),
      'dimension_multiplier' => $this->calculateDimensionMultiplier(
        (float) ($data['length'] ?? $settings->get('default_length') ?? 0),
        (float) ($data['width'] ?? $settings->get('default_width') ?? 0),
        (float) ($data['height'] ?? $settings->get('default_height') ?? 0),
        $reference_length,
        $reference_width,
        $reference_height,
      ),
    ];
  }

  /**
   * Applies 12% GST to a per-box price.
   */
  public function applyGST(float $base_price): array {
    $base_price = round($base_price, 2);
    $gst_rate = (float) ($this->settings()->get('gst_rate') ?? 0.12);
    $gst = round($base_price * $gst_rate, 2);
    $final = round($base_price + $gst, 2);
    return [
      'base' => $base_price,
      'gst' => $gst,
      'final' => $final,
    ];
  }

  /**
   * Formats an amount with Indian digit grouping.
   */
  public static function formatIndianNumber(float|int|string $amount, int $decimals = 2): string {
    $amount = (float) $amount;
    $negative = $amount < 0;
    $formatted = number_format(abs($amount), $decimals, '.', '');
    $parts = explode('.', $formatted);
    $integer = $parts[0];
    $fraction = $parts[1] ?? '';

    if (strlen($integer) > 3) {
      $last_three = substr($integer, -3);
      $leading = substr($integer, 0, -3);
      $leading = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $leading);
      $integer = $leading . ',' . $last_three;
    }

    return ($negative ? '-' : '') . $integer . ($decimals > 0 ? '.' . $fraction : '');
  }

  /**
   * Formats a rupee amount with Indian digit grouping.
   */
  public static function formatIndianCurrency(float|int|string $amount, int $decimals = 2): string {
    return '₹' . self::formatIndianNumber($amount, $decimals);
  }

  /**
   * Calculates the outside surface area of a rectangular box in square inches.
   */
  public function calculateBoxSurfaceArea(float $length, float $width, float $height): float {
    if ($length <= 0 || $width <= 0 || $height <= 0) {
      return 0.0;
    }
    return round(2 * (($length * $width) + ($length * $height) + ($width * $height)), 2);
  }

  /**
   * Scales slab prices by size against the seeded reference box.
   */
  public function calculateDimensionMultiplier(float $length, float $width, float $height, float $reference_length = self::REFERENCE_LENGTH, float $reference_width = self::REFERENCE_WIDTH, float $reference_height = self::REFERENCE_HEIGHT): float {
    $area = $this->calculateBoxSurfaceArea($length, $width, $height);
    if ($area <= 0) {
      return 1.0;
    }

    $reference_area = $this->calculateBoxSurfaceArea($reference_length, $reference_width, $reference_height);
    if ($reference_area <= 0) {
      return 1.0;
    }
    return round($area / $reference_area, 4);
  }

  /**
   * Gets the material multiplier for a board grade.
   */
  public function getBoardGradeFactor(string $board): float {
    $factors = $this->settings()->get('board_grade_factors') ?: self::BOARD_GRADE_FACTORS;
    return isset($factors[$board]) ? (float) $factors[$board] : 1.0;
  }

  private function settings() {
    return $this->configFactory->get('ambey_box_calculator.settings');
  }

  /**
   * Gets shipping cost per box for a zone.
   */
  public function getShippingCost(string $zone_id): float {
    if ($zone_id === '') {
      return 0.0;
    }
    $zone = $this->entityTypeManager->getStorage('ambey_shipping_zone')->load($zone_id);
    return $zone ? (float) $zone->get('cost') : 0.0;
  }

  /**
   * Gets shipping delivery days.
   */
  public function getDeliveryDays(string $zone_id): ?int {
    if ($zone_id === '') {
      return NULL;
    }
    $zone = $this->entityTypeManager->getStorage('ambey_shipping_zone')->load($zone_id);
    return $zone ? (int) $zone->get('delivery_days') : NULL;
  }

  /**
   * Gets shape ids that have at least one pricing slab.
   */
  public function getAvailableShapes(): array {
    $shapes = $this->database->select('ambey_box_pricing', 'p')
      ->distinct()
      ->fields('p', ['shape'])
      ->execute()
      ->fetchCol();

    $storage = $this->entityTypeManager->getStorage('ambey_pricing');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    foreach ($storage->loadMultiple($ids) as $entity) {
      if ($shape = (string) $entity->get('shape')) {
        $shapes[] = $shape;
      }
    }

    $shapes = array_values(array_unique(array_filter($shapes)));
    sort($shapes);
    return $shapes;
  }

  /**
   * Gets board grade ids that have at least one pricing slab.
   */
  public function getAvailableBoardGrades(): array {
    $boards = $this->database->select('ambey_box_pricing', 'p')
      ->distinct()
      ->fields('p', ['board_grade'])
      ->execute()
      ->fetchCol();

    $storage = $this->entityTypeManager->getStorage('ambey_pricing');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    foreach ($storage->loadMultiple($ids) as $entity) {
      if ($board = (string) $entity->get('board_grade')) {
        $boards[] = $board;
      }
    }

    $boards = array_values(array_unique(array_filter($boards)));
    sort($boards);
    return $boards;
  }

  /**
   * Loads the matching pricing row from the table or config entity fallback.
   */
  private function loadPricingRow(string $shape, string $board, int $quantity): ?array {
    $storage = $this->entityTypeManager->getStorage('ambey_pricing');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('shape', $shape)
      ->condition('board_grade', $board)
      ->condition('quantity_from', $quantity, '<=')
      ->condition('quantity_to', $quantity, '>=')
      ->sort('quantity_from', 'DESC')
      ->range(0, 1)
      ->execute();
    $entities = $storage->loadMultiple($ids);
    $entity = reset($entities);
    if ($entity) {
      return [
        'shape' => (string) $entity->get('shape'),
        'board_grade' => (string) $entity->get('board_grade'),
        'quantity_from' => (int) $entity->get('quantity_from'),
        'quantity_to' => (int) $entity->get('quantity_to'),
        'reference_length' => (float) ($entity->get('reference_length') ?: self::REFERENCE_LENGTH),
        'reference_width' => (float) ($entity->get('reference_width') ?: self::REFERENCE_WIDTH),
        'reference_height' => (float) ($entity->get('reference_height') ?: self::REFERENCE_HEIGHT),
        'price_per_box' => (float) $entity->get('price_per_box'),
        'print_single_cost' => (float) ($entity->get('print_single_cost') ?: 0),
        'print_multi_cost' => (float) ($entity->get('print_multi_cost') ?: 0),
        'coating_cost' => (float) ($entity->get('coating_cost') ?: 0),
      ];
    }

    $query = $this->database->select('ambey_box_pricing', 'p')
      ->fields('p')
      ->condition('shape', $shape)
      ->condition('board_grade', $board)
      ->condition('quantity_from', $quantity, '<=')
      ->condition('quantity_to', $quantity, '>=')
      ->orderBy('quantity_from', 'DESC')
      ->range(0, 1);

    $row = $query->execute()->fetchAssoc();
    if ($row) {
      $row += [
        'reference_length' => self::REFERENCE_LENGTH,
        'reference_width' => self::REFERENCE_WIDTH,
        'reference_height' => self::REFERENCE_HEIGHT,
      ];
      return $row;
    }

    return NULL;
  }

}
