<?php

namespace Drupal\Tests\ambey_box_calculator\Unit;

use Drupal\ambey_box_calculator\PricingCalculator;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @group ambey_box_calculator
 */
class PricingCalculatorGstTest extends UnitTestCase {
  public function testApplyGst(): void {
    $calculator = new PricingCalculator(
      $this->createMock(Connection::class),
      $this->createMock(CacheBackendInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(LoggerInterface::class)
    );
    $this->assertSame(['base' => 100.0, 'gst' => 12.0, 'final' => 112.0], $calculator->applyGST(100));
  }

  public function testApplyGstRounding(): void {
    $calculator = new PricingCalculator(
      $this->createMock(Connection::class),
      $this->createMock(CacheBackendInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(LoggerInterface::class)
    );
    $this->assertSame(['base' => 11.72, 'gst' => 1.41, 'final' => 13.13], $calculator->applyGST(11.72));
  }
}
