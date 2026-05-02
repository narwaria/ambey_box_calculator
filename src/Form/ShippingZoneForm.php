<?php

namespace Drupal\ambey_box_calculator\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

class ShippingZoneForm extends EntityForm {
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $entity = $this->entity;
    $form['label'] = ['#type' => 'textfield', '#title' => $this->t('Zone Name'), '#default_value' => $entity->label(), '#required' => TRUE];
    $form['id'] = ['#type' => 'machine_name', '#default_value' => $entity->id(), '#machine_name' => ['exists' => '\\Drupal\\ambey_box_calculator\\Entity\\ShippingZone::load']];
    $form['cost'] = ['#type' => 'number', '#title' => $this->t('Shipping Cost Per Box'), '#step' => 0.01, '#min' => 0, '#default_value' => $entity->get('cost'), '#required' => TRUE];
    $form['delivery_days'] = ['#type' => 'number', '#title' => $this->t('Delivery Days'), '#min' => 0, '#default_value' => $entity->get('delivery_days')];
    return parent::buildForm($form, $form_state);
  }

  public function save(array $form, FormStateInterface $form_state): int {
    $status = $this->entity->save();
    \Drupal::cache('ambey_pricing')->deleteAll();
    $this->messenger()->addStatus($this->t('Shipping zone saved.'));
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $status;
  }
}
