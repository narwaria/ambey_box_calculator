<?php

namespace Drupal\ambey_box_calculator;

/**
 * Validates calculator business rules shared by form and API flows.
 */
class BusinessRuleValidator {

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

    if ($print === 'single' && $quantity < 500) {
      $errors['quantity'] = 'Single colour print requires minimum quantity of 500.';
    }

    if ($print === 'multi' && $quantity < 3000) {
      $errors['quantity'] = 'Multi colour print requires minimum quantity of 3000.';
    }

    if (($data['color'] ?? '') === 'brown' && !empty($data['coating'])) {
      $errors['coating'] = 'Coating is available only for white boxes.';
    }

    return $errors;
  }

}
