<?php

namespace Drupal\ambey_box_calculator;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

class ShippingZoneListBuilder extends ConfigEntityListBuilder {
  public function buildHeader(): array {
    $header['label'] = $this->t('Zone');
    $header['cost'] = $this->t('Cost Per Box');
    $header['delivery'] = $this->t('Delivery Days');
    return $header + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity): array {
    $row['label'] = $entity->label();
    $row['cost'] = '₹' . number_format((float) $entity->get('cost'), 2);
    $row['delivery'] = $entity->get('delivery_days') . ' days';
    return $row + parent::buildRow($entity);
  }
}
