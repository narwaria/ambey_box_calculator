<?php

namespace Drupal\ambey_box_calculator\Form;

use Drupal\ambey_box_calculator\QuoteStorage;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Management form for quote workflow fields.
 */
class AdminQuoteManageForm extends FormBase {

  public function getFormId(): string {
    return 'ambey_admin_quote_manage_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?array $quote = NULL): array {
    if (!$this->currentUser()->hasPermission('edit quotes')) {
      return ['#markup' => $this->t('You do not have permission to edit quotes.')];
    }
    if (!$quote) {
      return ['#markup' => $this->t('Quote not found.')];
    }

    $form['quote_uuid'] = [
      '#type' => 'hidden',
      '#value' => $quote['uuid'],
    ];
    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Status'),
      '#options' => QuoteStorage::STATUSES,
      '#default_value' => $quote['status'] ?? 'new',
      '#required' => TRUE,
    ];
    $form['priority'] = [
      '#type' => 'select',
      '#title' => $this->t('Priority'),
      '#options' => QuoteStorage::PRIORITIES,
      '#default_value' => $quote['priority'] ?? 'medium',
      '#required' => TRUE,
    ];
    $form['assigned_uid'] = [
      '#type' => 'select',
      '#title' => $this->t('Assigned To'),
      '#options' => $this->getUserOptions(),
      '#default_value' => (int) ($quote['assigned_uid'] ?? 0),
    ];
    $form['note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Add Internal Note'),
      '#rows' => 4,
    ];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Update Quote'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->currentUser()->hasPermission('edit quotes')) {
      $this->messenger()->addError($this->t('You do not have permission to edit quotes.'));
      return;
    }
    $uuid = (string) $form_state->getValue('quote_uuid');
    $updated = \Drupal::service('ambey_box_calculator.quote')->updateManagementFields($uuid, [
      'status' => (string) $form_state->getValue('status'),
      'priority' => (string) $form_state->getValue('priority'),
      'assigned_uid' => (int) $form_state->getValue('assigned_uid'),
    ], (string) $form_state->getValue('note'), (int) $this->currentUser()->id());

    if ($updated) {
      $this->messenger()->addStatus($this->t('Quote updated.'));
    }
    else {
      $this->messenger()->addError($this->t('Quote could not be updated.'));
    }
  }

  private function getUserOptions(): array {
    $options = [0 => $this->t('- Unassigned -')];
    $users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple();
    foreach ($users as $user) {
      if ($user->id() > 0 && $user->isActive()) {
        $options[$user->id()] = $user->getDisplayName();
      }
    }
    return $options;
  }

}
