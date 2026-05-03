<?php

namespace Drupal\ambey_box_calculator;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Validates calculator business rules shared by form and API flows.
 */
class BusinessRuleValidator {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Validates quote input data.
   *
   * @param array $data
   *   Input data.
   *
   * @return array
   *   Associative array of field machine name => error message.
   */
  public function validate(array $data): array {
    $errors = [];

    foreach (['length', 'width', 'height'] as $dimension) {
      if (!isset($data[$dimension]) || !is_numeric($data[$dimension]) || (float) $data[$dimension] <= 0) {
        $errors[$dimension] = ucfirst($dimension) . ' must be greater than 0.';
      }
    }

    if (!isset($data['quantity']) || !is_numeric($data['quantity']) || (int) $data['quantity'] <= 0) {
      $errors['quantity'] = 'Quantity must be greater than 0.';
    }

    $quantity = isset($data['quantity']) ? (int) $data['quantity'] : 0;
    $print = $data['print'] ?? $data['print_type'] ?? '';

    $config = $this->configFactory->get('ambey_box_calculator.settings');
    $single_min = (int) ($config->get('single_print_min_quantity') ?? 500);
    $multi_min = (int) ($config->get('multi_print_min_quantity') ?? 3000);

    if ($print === 'single' && $quantity < $single_min) {
      $errors['quantity'] = 'Single colour print requires minimum quantity of ' . $single_min . '.';
    }

    if ($print === 'multi' && $quantity < $multi_min) {
      $errors['quantity'] = 'Multi colour print requires minimum quantity of ' . $multi_min . '.';
    }

    $coating_allowed_color = (string) ($config->get('coating_allowed_color') ?? 'white');
    if (($data['color'] ?? '') !== $coating_allowed_color && !empty($data['coating'])) {
      $errors['coating'] = 'Coating is available only for ' . $coating_allowed_color . ' boxes.';
    }

    return $errors;
  }

}
