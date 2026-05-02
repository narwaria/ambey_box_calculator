<?php

namespace Drupal\ambey_box_calculator\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

class PricingDeleteForm extends EntityDeleteForm {
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    Cache::invalidateTags(['ambey_pricing']);
    \Drupal::cache('ambey_pricing')->deleteAll();
  }
}
