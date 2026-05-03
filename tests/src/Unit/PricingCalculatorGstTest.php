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
  private function calculator(): PricingCalculator {
    return new PricingCalculator(
      $this->createMock(Connection::class),
      $this->createMock(CacheBackendInterface::class),
      $this->createMock(EntityTypeManagerInterface::class),
      $this->createMock(LoggerInterface::class)
    );
  }

  public function testApplyGst(): void {
    $calculator = $this->calculator();
    $this->assertSame(['base' => 100.0, 'gst' => 12.0, 'final' => 112.0], $calculator->applyGST(100));
  }

  public function testApplyGstRounding(): void {
    $calculator = $this->calculator();
    $this->assertSame(['base' => 11.72, 'gst' => 1.41, 'final' => 13.13], $calculator->applyGST(11.72));
  }

  public function testCalculateBoxSurfaceArea(): void {
    $calculator = $this->calculator();
    $this->assertSame(340.0, $calculator->calculateBoxSurfaceArea(10, 8, 5));
  }

  public function testCalculateDimensionMultiplier(): void {
    $calculator = $this->calculator();
    $this->assertSame(1.0, $calculator->calculateDimensionMultiplier(10, 8, 5));
    $this->assertSame(4.0, $calculator->calculateDimensionMultiplier(20, 16, 10));
  }

  public function testGetBoardGradeFactor(): void {
    $calculator = $this->calculator();
    $this->assertSame(1.0, $calculator->getBoardGradeFactor('3ply'));
    $this->assertSame(1.75, $calculator->getBoardGradeFactor('5ply'));
    $this->assertSame(2.5, $calculator->getBoardGradeFactor('7ply'));
    $this->assertSame(1.0, $calculator->getBoardGradeFactor('unknown'));
  }

  public function testFormatIndianCurrency(): void {
    $this->assertSame('₹37,98,000.00', PricingCalculator::formatIndianCurrency(3798000));
  }
}
