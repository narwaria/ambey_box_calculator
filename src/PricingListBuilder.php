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
    $header['price'] = $this->t('Price');
    return $header + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity): array {
    $row['label'] = $entity->label();
    $row['shape'] = $entity->get('shape');
    $row['board'] = $entity->get('board_grade');
    $row['quantity'] = $entity->get('quantity_from') . ' - ' . $entity->get('quantity_to');
    $row['price'] = '₹' . number_format((float) $entity->get('price_per_box'), 2);
    return $row + parent::buildRow($entity);
  }
}
