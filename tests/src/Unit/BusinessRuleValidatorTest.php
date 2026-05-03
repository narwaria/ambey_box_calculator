<?php

namespace Drupal\Tests\ambey_box_calculator\Unit;

use Drupal\ambey_box_calculator\BusinessRuleValidator;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;

/**
 * @group ambey_box_calculator
 */
class BusinessRuleValidatorTest extends UnitTestCase {
  private function validator(): BusinessRuleValidator {
    $config = $this->createMock(ImmutableConfig::class);
    $config->method('get')->willReturnMap([
      ['single_print_min_quantity', 500],
      ['multi_print_min_quantity', 3000],
      ['coating_allowed_color', 'white'],
    ]);
    $config_factory = $this->createMock(ConfigFactoryInterface::class);
    $config_factory->method('get')->with('ambey_box_calculator.settings')->willReturn($config);
    return new BusinessRuleValidator($config_factory);
  }

  public function testValidInputHasNoErrors(): void {
    $validator = $this->validator();
    $errors = $validator->validate(['length' => 10, 'width' => 8, 'height' => 5, 'quantity' => 500, 'print' => 'none', 'color' => 'brown', 'coating' => '']);
    $this->assertSame([], $errors);
  }

  public function testSinglePrintMoq(): void {
    $validator = $this->validator();
    $errors = $validator->validate(['length' => 10, 'width' => 8, 'height' => 5, 'quantity' => 499, 'print' => 'single', 'color' => 'white', 'coating' => '']);
    $this->assertArrayHasKey('quantity', $errors);
  }

  public function testMultiPrintMoq(): void {
    $validator = $this->validator();
    $errors = $validator->validate(['length' => 10, 'width' => 8, 'height' => 5, 'quantity' => 2999, 'print' => 'multi', 'color' => 'white', 'coating' => 'gloss']);
    $this->assertArrayHasKey('quantity', $errors);
  }

  public function testBrownBoxCannotHaveCoating(): void {
    $validator = $this->validator();
    $errors = $validator->validate(['length' => 10, 'width' => 8, 'height' => 5, 'quantity' => 500, 'print' => 'none', 'color' => 'brown', 'coating' => 'gloss']);
    $this->assertArrayHasKey('coating', $errors);
  }

  public function testDimensionsMustBePositive(): void {
    $validator = $this->validator();
    $errors = $validator->validate(['length' => 0, 'width' => -1, 'height' => 0, 'quantity' => 1]);
    $this->assertArrayHasKey('length', $errors);
    $this->assertArrayHasKey('width', $errors);
    $this->assertArrayHasKey('height', $errors);
  }
}
