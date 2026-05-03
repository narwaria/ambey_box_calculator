<?php

namespace Drupal\ambey_box_calculator\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class CalculatorSettingsForm extends ConfigFormBase {

  public function getFormId(): string {
    return 'ambey_box_calculator_settings_form';
  }

  protected function getEditableConfigNames(): array {
    return ['ambey_box_calculator.settings'];
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('ambey_box_calculator.settings');

    $form['defaults'] = [
      '#type' => 'details',
      '#title' => $this->t('Calculator defaults'),
      '#open' => TRUE,
    ];
    $form['defaults']['default_length'] = $this->numberField('Default Length (L)', $config->get('default_length') ?? 10, 0.1, 0.1);
    $form['defaults']['default_width'] = $this->numberField('Default Width (W)', $config->get('default_width') ?? 8, 0.1, 0.1);
    $form['defaults']['default_height'] = $this->numberField('Default Height (H)', $config->get('default_height') ?? 5, 0.1, 0.1);
    $form['defaults']['default_quantity'] = $this->numberField('Default Quantity', $config->get('default_quantity') ?? 500, 1, 1);
    $form['defaults']['default_board_grade'] = ['#type' => 'textfield', '#title' => $this->t('Default Board Grade'), '#default_value' => $config->get('default_board_grade') ?? '3ply', '#required' => TRUE];
    $form['defaults']['default_color'] = ['#type' => 'textfield', '#title' => $this->t('Default Colour'), '#default_value' => $config->get('default_color') ?? 'brown', '#required' => TRUE];
    $form['defaults']['default_shape'] = ['#type' => 'textfield', '#title' => $this->t('Default Shape'), '#default_value' => $config->get('default_shape') ?? 'regular', '#required' => TRUE];
    $form['defaults']['default_print'] = ['#type' => 'textfield', '#title' => $this->t('Default Print'), '#default_value' => $config->get('default_print') ?? 'none', '#required' => TRUE];
    $form['defaults']['default_quality'] = ['#type' => 'textfield', '#title' => $this->t('Default Quality'), '#default_value' => $config->get('default_quality') ?? 'standard', '#required' => TRUE];
    $form['defaults']['default_shipping'] = ['#type' => 'textfield', '#title' => $this->t('Default Shipping Zone'), '#default_value' => $config->get('default_shipping') ?? 'local', '#required' => TRUE];

    $form['pricing'] = [
      '#type' => 'details',
      '#title' => $this->t('Pricing rules'),
      '#open' => TRUE,
    ];
    $form['pricing']['gst_rate'] = $this->numberField('GST Rate', $config->get('gst_rate') ?? 0.12, 0, 0.0001);
    $form['pricing']['price_cache_lifetime'] = $this->numberField('Price Cache Lifetime (seconds)', $config->get('price_cache_lifetime') ?? 3600, 0, 1);
    $form['pricing']['single_print_min_quantity'] = $this->numberField('Single Colour Print Minimum Quantity', $config->get('single_print_min_quantity') ?? 500, 1, 1);
    $form['pricing']['multi_print_min_quantity'] = $this->numberField('Multi Colour Print Minimum Quantity', $config->get('multi_print_min_quantity') ?? 3000, 1, 1);
    $form['pricing']['coating_allowed_color'] = ['#type' => 'textfield', '#title' => $this->t('Coating Allowed Colour'), '#default_value' => $config->get('coating_allowed_color') ?? 'white', '#required' => TRUE];

    $factors = $config->get('board_grade_factors') ?: [];
    $form['pricing']['board_grade_factors'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Board Grade Factors'),
      '#description' => $this->t('Enter one grade per line as grade|factor, for example 5ply|1.75.'),
      '#default_value' => $this->formatFactors($factors),
      '#rows' => 5,
      '#required' => TRUE,
    ];

    $form['pdf'] = [
      '#type' => 'details',
      '#title' => $this->t('PDF'),
      '#open' => TRUE,
    ];
    $form['pdf']['pdf_logo_path'] = ['#type' => 'textfield', '#title' => $this->t('PDF Logo Path'), '#description' => $this->t('Optional absolute path or Drupal-root-relative path. Leave blank to use the Drupal logo fallback.'), '#default_value' => $config->get('pdf_logo_path') ?? ''];

    return parent::buildForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if ($this->parseFactors((string) $form_state->getValue('board_grade_factors')) === []) {
      $form_state->setErrorByName('board_grade_factors', $this->t('Enter at least one valid board grade factor.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('ambey_box_calculator.settings')
      ->set('default_length', (float) $form_state->getValue('default_length'))
      ->set('default_width', (float) $form_state->getValue('default_width'))
      ->set('default_height', (float) $form_state->getValue('default_height'))
      ->set('default_quantity', (int) $form_state->getValue('default_quantity'))
      ->set('default_board_grade', (string) $form_state->getValue('default_board_grade'))
      ->set('default_color', (string) $form_state->getValue('default_color'))
      ->set('default_shape', (string) $form_state->getValue('default_shape'))
      ->set('default_print', (string) $form_state->getValue('default_print'))
      ->set('default_quality', (string) $form_state->getValue('default_quality'))
      ->set('default_shipping', (string) $form_state->getValue('default_shipping'))
      ->set('gst_rate', (float) $form_state->getValue('gst_rate'))
      ->set('price_cache_lifetime', (int) $form_state->getValue('price_cache_lifetime'))
      ->set('single_print_min_quantity', (int) $form_state->getValue('single_print_min_quantity'))
      ->set('multi_print_min_quantity', (int) $form_state->getValue('multi_print_min_quantity'))
      ->set('coating_allowed_color', (string) $form_state->getValue('coating_allowed_color'))
      ->set('board_grade_factors', $this->parseFactors((string) $form_state->getValue('board_grade_factors')))
      ->set('pdf_logo_path', (string) $form_state->getValue('pdf_logo_path'))
      ->save();

    Cache::invalidateTags(['ambey_pricing']);
    \Drupal::cache('ambey_pricing')->deleteAll();
    parent::submitForm($form, $form_state);
  }

  private function numberField(string $title, int|float|string $value, int|float $min, int|float $step): array {
    return ['#type' => 'number', '#title' => $this->t($title), '#default_value' => $value, '#min' => $min, '#step' => $step, '#required' => TRUE];
  }

  private function formatFactors(array $factors): string {
    $lines = [];
    foreach ($factors as $grade => $factor) {
      $lines[] = $grade . '|' . $factor;
    }
    return implode("\n", $lines);
  }

  private function parseFactors(string $value): array {
    $factors = [];
    foreach (preg_split('/\r\n|\r|\n/', $value) as $line) {
      $parts = array_map('trim', explode('|', $line, 2));
      if (count($parts) === 2 && $parts[0] !== '' && is_numeric($parts[1]) && (float) $parts[1] > 0) {
        $factors[$parts[0]] = (float) $parts[1];
      }
    }
    return $factors;
  }

}
