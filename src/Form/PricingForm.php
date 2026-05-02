<?php

namespace Drupal\ambey_box_calculator\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

class PricingForm extends EntityForm {
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $entity = $this->entity;
    $form['label'] = ['#type' => 'textfield', '#title' => $this->t('Label'), '#default_value' => $entity->label(), '#required' => TRUE];
    $form['id'] = ['#type' => 'machine_name', '#default_value' => $entity->id(), '#machine_name' => ['exists' => '\\Drupal\\ambey_box_calculator\\Entity\\Pricing::load']];
    $form['shape'] = ['#type' => 'textfield', '#title' => $this->t('Shape'), '#default_value' => $entity->get('shape'), '#required' => TRUE];
    $form['board_grade'] = ['#type' => 'textfield', '#title' => $this->t('Board Grade'), '#default_value' => $entity->get('board_grade'), '#required' => TRUE];
    $form['quantity_from'] = ['#type' => 'number', '#title' => $this->t('Quantity From'), '#default_value' => $entity->get('quantity_from'), '#min' => 1, '#required' => TRUE];
    $form['quantity_to'] = ['#type' => 'number', '#title' => $this->t('Quantity To'), '#default_value' => $entity->get('quantity_to'), '#min' => 1, '#required' => TRUE];
    $form['price_per_box'] = ['#type' => 'number', '#title' => $this->t('Price Per Box'), '#step' => 0.01, '#min' => 0, '#default_value' => $entity->get('price_per_box'), '#required' => TRUE];
    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if ((int) $form_state->getValue('quantity_to') < (int) $form_state->getValue('quantity_from')) {
      $form_state->setErrorByName('quantity_to', $this->t('Quantity To must be greater than or equal to Quantity From.'));
    }
  }

  public function save(array $form, FormStateInterface $form_state): int {
    $status = $this->entity->save();
    Cache::invalidateTags(['ambey_pricing']);
    \Drupal::cache('ambey_pricing')->deleteAll();
    $this->messenger()->addStatus($this->t('Pricing saved.'));
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $status;
  }
}
