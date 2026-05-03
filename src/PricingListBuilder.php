<?php

namespace Drupal\ambey_box_calculator;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

class PricingListBuilder extends ConfigEntityListBuilder {
  public function buildHeader(): array {
    $header['label'] = $this->t('Label');
    $header['shape'] = $this->t('Shape');
    $header['board'] = $this->t('Board Grade');
    $header['quantity'] = $this->t('Quantity Range');
    $header['reference_size'] = $this->t('Reference Size');
    $header['price'] = $this->t('Price');
    return $header + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity): array {
    $row['label'] = $entity->label();
    $row['shape'] = $entity->get('shape');
    $row['board'] = $entity->get('board_grade');
    $row['quantity'] = $entity->get('quantity_from') . ' - ' . $entity->get('quantity_to');
    $row['reference_size'] = ($entity->get('reference_length') ?: 10) . ' x ' . ($entity->get('reference_width') ?: 8) . ' x ' . ($entity->get('reference_height') ?: 5) . ' in';
    $row['price'] = PricingCalculator::formatIndianCurrency($entity->get('price_per_box'));
    return $row + parent::buildRow($entity);
  }
}
