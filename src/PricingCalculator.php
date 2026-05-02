<?php

namespace Drupal\ambey_box_calculator;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Pricing service for slab lookup, add-ons, shipping and GST.
 */
class PricingCalculator {

  public function __construct(
    protected Connection $database,
    protected CacheBackendInterface $cache,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Gets per-box price with print, coating and shipping add-ons.
   */
  public function getPriceWithAddons(string $shape, string $board, int $quantity, string $print = 'none', string $coating = '', string $color = 'brown', string $shipping_zone = ''): float {
    if ($shape === '' || $board === '' || $quantity <= 0) {
      return 0.0;
    }

    $cache_key = 'price:' . hash('sha256', implode('|', [$shape, $board, $quantity, $print, $coating, $color, $shipping_zone]));
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

    $price = (float) $row['price_per_box'];

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
    $this->cache->set($cache_key, $price, time() + 3600, ['ambey_pricing']);

    $this->logger->debug('Price calculated: Shape=@shape Board=@board Qty=@qty Price=@price', [
      '@shape' => $shape,
      '@board' => $board,
      '@qty' => $quantity,
      '@price' => $price,
    ]);

    return $price;
  }

  /**
   * Applies 12% GST to a per-box price.
   */
  public function applyGST(float $base_price): array {
    $base_price = round($base_price, 2);
    $gst = round($base_price * 0.12, 2);
    $final = round($base_price + $gst, 2);
    return [
      'base' => $base_price,
      'gst' => $gst,
      'final' => $final,
    ];
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
      return $row;
    }

    $storage = $this->entityTypeManager->getStorage('ambey_pricing');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('shape', $shape)
      ->condition('board_grade', $board)
      ->condition('quantity_from', $quantity, '<=')
      ->condition('quantity_to', $quantity, '>=')
      ->range(0, 1)
      ->execute();
    $entities = $storage->loadMultiple($ids);
    $entity = reset($entities);
    if (!$entity) {
      return NULL;
    }

    return [
      'shape' => (string) $entity->get('shape'),
      'board_grade' => (string) $entity->get('board_grade'),
      'quantity_from' => (int) $entity->get('quantity_from'),
      'quantity_to' => (int) $entity->get('quantity_to'),
      'price_per_box' => (float) $entity->get('price_per_box'),
      'print_single_cost' => 0.0,
      'print_multi_cost' => 0.0,
      'coating_cost' => 0.0,
    ];
  }

}
