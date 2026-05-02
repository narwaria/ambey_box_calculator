<?php

namespace Drupal\ambey_box_calculator\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

class ShapeForm extends EntityForm {
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $entity = $this->entity;
    $form['label'] = ['#type' => 'textfield', '#title' => $this->t('Shape Name'), '#default_value' => $entity->label(), '#required' => TRUE];
    $form['id'] = ['#type' => 'machine_name', '#default_value' => $entity->id(), '#machine_name' => ['exists' => '\\Drupal\\ambey_box_calculator\\Entity\\Shape::load']];
    $form['description'] = ['#type' => 'textarea', '#title' => $this->t('Description'), '#default_value' => $entity->get('description')];
    $form['image'] = ['#type' => 'textfield', '#title' => $this->t('Image URL or path'), '#default_value' => $entity->get('image'), '#description' => $this->t('Example: /modules/custom/ambey_box_calculator/ambey_box_calculator/images/regular.png')];
    $form['enabled'] = ['#type' => 'checkbox', '#title' => $this->t('Enabled'), '#default_value' => $entity->get('enabled') ?? TRUE];
    return parent::buildForm($form, $form_state);
  }

  public function save(array $form, FormStateInterface $form_state): int {
    $status = $this->entity->save();
    $this->messenger()->addStatus($this->t('Shape saved.'));
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $status;
  }
}
