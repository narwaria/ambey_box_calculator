<?php

namespace Drupal\ambey_box_calculator;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

class ShapeListBuilder extends ConfigEntityListBuilder {
  public function buildHeader(): array {
    $header['label'] = $this->t('Shape Name');
    $header['description'] = $this->t('Description');
    $header['enabled'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  public function buildRow(EntityInterface $entity): array {
    $row['label'] = $entity->label();
    $row['description'] = $entity->get('description');
    $row['enabled'] = $entity->get('enabled') ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }
}
