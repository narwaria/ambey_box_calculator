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
    $form['shape'] = ['#type' => 'select', '#title' => $this->t('Shape'), '#options' => $this->getShapeOptions((string) $entity->get('shape')), '#default_value' => $entity->get('shape') ?: 'regular', '#required' => TRUE];
    $form['board_grade'] = ['#type' => 'select', '#title' => $this->t('Board Grade'), '#options' => $this->getBoardGradeOptions(), '#default_value' => $entity->get('board_grade') ?: '3ply', '#required' => TRUE];
    $form['quantity_from'] = ['#type' => 'number', '#title' => $this->t('Quantity From'), '#default_value' => $entity->get('quantity_from'), '#min' => 1, '#required' => TRUE];
    $form['quantity_to'] = ['#type' => 'number', '#title' => $this->t('Quantity To'), '#default_value' => $entity->get('quantity_to'), '#min' => 1, '#required' => TRUE];
    $form['reference_size'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Reference Box Size'),
      '#description' => $this->t('Set the box size that the Price Per Box is based on. Customer-entered length, width, and height are scaled against this reference size.'),
    ];
    $form['reference_size']['reference_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Reference Length (L)'),
      '#step' => 0.1,
      '#min' => 0.1,
      '#default_value' => $entity->get('reference_length') ?: 10,
      '#required' => TRUE,
      '#parents' => ['reference_length'],
    ];
    $form['reference_size']['reference_width'] = [
      '#type' => 'number',
      '#title' => $this->t('Reference Width (W)'),
      '#step' => 0.1,
      '#min' => 0.1,
      '#default_value' => $entity->get('reference_width') ?: 8,
      '#required' => TRUE,
      '#parents' => ['reference_width'],
    ];
    $form['reference_size']['reference_height'] = [
      '#type' => 'number',
      '#title' => $this->t('Reference Height (H)'),
      '#step' => 0.1,
      '#min' => 0.1,
      '#default_value' => $entity->get('reference_height') ?: 5,
      '#required' => TRUE,
      '#parents' => ['reference_height'],
    ];
    $form['price_per_box'] = ['#type' => 'number', '#title' => $this->t('Price Per Box'), '#description' => $this->t('Base price for the reference box size above, before customer size scaling, board material factor, add-ons, shipping, and GST.'), '#step' => 0.01, '#min' => 0, '#default_value' => $entity->get('price_per_box'), '#required' => TRUE];
    return parent::buildForm($form, $form_state);
  }

  private function getShapeOptions(string $current_shape = ''): array {
    $options = [];
    $entities = \Drupal::entityTypeManager()->getStorage('ambey_shape')->loadMultiple();
    foreach ($entities as $entity) {
      if ($entity->get('enabled') || $entity->id() === $current_shape) {
        $options[$entity->id()] = $entity->label();
      }
    }

    if ($current_shape !== '' && !isset($options[$current_shape])) {
      $options[$current_shape] = $current_shape;
    }

    return $options ?: ['regular' => $this->t('Regular Box')];
  }

  private function getBoardGradeOptions(): array {
    $options = [
      'mono' => $this->t('Mono Carton'),
      '3ply' => $this->t('3 Ply'),
      '5ply' => $this->t('5 Ply'),
      '7ply' => $this->t('7 Ply'),
    ];

    foreach (\Drupal::service('ambey_box_calculator.pricing')->getAvailableBoardGrades() as $id) {
      $options[$id] ??= $id;
    }

    return $options;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if ((int) $form_state->getValue('quantity_to') < (int) $form_state->getValue('quantity_from')) {
      $form_state->setErrorByName('quantity_to', $this->t('Quantity To must be greater than or equal to Quantity From.'));
    }
    foreach (['reference_length', 'reference_width', 'reference_height'] as $field) {
      if ((float) $form_state->getValue($field) <= 0) {
        $form_state->setErrorByName($field, $this->t('Reference size values must be greater than zero.'));
      }
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
