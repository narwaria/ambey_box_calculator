<?php

namespace Drupal\ambey_box_calculator\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class CrmSettingsForm extends ConfigFormBase {
  public function getFormId(): string {
    return 'ambey_crm_settings_form';
  }

  protected function getEditableConfigNames(): array {
    return ['ambey_box_calculator.crm'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ambey_box_calculator.crm');
    $form['webhook_url'] = ['#type' => 'url', '#title' => $this->t('CRM Webhook URL'), '#default_value' => $config->get('webhook_url')];
    $form['api_key'] = ['#type' => 'textfield', '#title' => $this->t('API Key'), '#default_value' => $config->get('api_key')];
    $form['debug_mode'] = ['#type' => 'checkbox', '#title' => $this->t('Enable Debug Logging'), '#default_value' => $config->get('debug_mode')];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('ambey_box_calculator.crm')
      ->set('webhook_url', $form_state->getValue('webhook_url'))
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('debug_mode', (bool) $form_state->getValue('debug_mode'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}
