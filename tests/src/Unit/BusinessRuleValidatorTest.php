<?php

namespace Drupal\Tests\ambey_box_calculator\Unit;

use Drupal\ambey_box_calculator\BusinessRuleValidator;
use Drupal\Tests\UnitTestCase;

/**
 * @group ambey_box_calculator
 */
class BusinessRuleValidatorTest extends UnitTestCase {
  public function testValidInputHasNoErrors(): void {
    $validator = new BusinessRuleValidator();
    $errors = $validator->validate(['length' => 10, 'width' => 8, 'height' => 5, 'quantity' => 500, 'print' => 'none', 'color' => 'brown', 'coating' => '']);
    $this->assertSame([], $errors);
  }

  public function testSinglePrintMoq(): void {
    $validator = new BusinessRuleValidator();
    $errors = $validator->validate(['length' => 10, 'width' => 8, 'height' => 5, 'quantity' => 499, 'print' => 'single', 'color' => 'white', 'coating' => '']);
    $this->assertArrayHasKey('quantity', $errors);
  }

  public function testMultiPrintMoq(): void {
    $validator = new BusinessRuleValidator();
    $errors = $validator->validate(['length' => 10, 'width' => 8, 'height' => 5, 'quantity' => 2999, 'print' => 'multi', 'color' => 'white', 'coating' => 'gloss']);
    $this->assertArrayHasKey('quantity', $errors);
  }

  public function testBrownBoxCannotHaveCoating(): void {
    $validator = new BusinessRuleValidator();
    $errors = $validator->validate(['length' => 10, 'width' => 8, 'height' => 5, 'quantity' => 500, 'print' => 'none', 'color' => 'brown', 'coating' => 'gloss']);
    $this->assertArrayHasKey('coating', $errors);
  }

  public function testDimensionsMustBePositive(): void {
    $validator = new BusinessRuleValidator();
    $errors = $validator->validate(['length' => 0, 'width' => -1, 'height' => 0, 'quantity' => 1]);
    $this->assertArrayHasKey('length', $errors);
    $this->assertArrayHasKey('width', $errors);
    $this->assertArrayHasKey('height', $errors);
  }
}
