<?php

namespace Drupal\Tests\ambey_box_calculator\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * @group ambey_box_calculator
 */
class QuoteStorageTest extends KernelTestBase {
  protected static $modules = ['system', 'user', 'ambey_box_calculator'];

  public function testQuoteStorageSavesAndLoadsQuote(): void {
    $this->installSchema('ambey_box_calculator', ['ambey_box_pricing', 'ambey_box_quote']);
    $storage = \Drupal::service('ambey_box_calculator.quote');
    $id = $storage->saveQuote(['created' => time(), 'name' => 'Test User', 'email' => 'test@example.com', 'phone' => '9999999999', 'quantity' => 500, 'base_price' => 15.11, 'gst' => 1.81, 'final_price' => 16.92, 'total_without_gst' => 7555, 'total_with_gst' => 8460]);
    $this->assertGreaterThan(0, $id);
    $quote = $storage->loadQuote($id);
    $this->assertSame('Test User', $quote['name']);
    $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $quote['uuid']);
    $this->assertSame($quote, $storage->loadQuoteByUuid($quote['uuid']));
  }
}
