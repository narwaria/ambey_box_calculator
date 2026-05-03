<?php

namespace Drupal\ambey_box_calculator\Form;

use Drupal\ambey_box_calculator\PricingCalculator;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Frontend calculator form.
 */
class BoxCalculatorForm extends FormBase {

  private const DEFAULT_DIMENSIONS = [
    'length' => 10,
    'width' => 8,
    'height' => 5,
  ];

  private const DEFAULT_QUANTITY = 500;

  public function getFormId(): string {
    return 'ambey_box_calculator_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#attached']['library'][] = 'ambey_box_calculator/calculator';
    $form['#prefix'] = '<div class="ambey-calculator"><div class="ambey-quote-hero"><div><h1>Get <span>Instant Box Quotation</span></h1><p>Configure your corrugated box size, material, print, coating, and quantity. Get an estimated price instantly and email the quotation.</p></div><div class="ambey-hero-box" aria-hidden="true"><span></span></div></div>';
    $form['#suffix'] = '</div>';
    $form['#cache'] = [
      'max-age' => 0,
    ];

    $board_options = $this->getBoardGradeOptions();
    $shape_options = $this->getShapeOptions();
    $shipping_options = $this->getShippingOptions();
    $settings = $this->config('ambey_box_calculator.settings');

    $form['size'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Size of box'),
      '#description' => $this->t('Please enter the internal size of the box in inches.'),
      '#attributes' => ['class' => ['ambey-fieldset', 'ambey-step-field', 'ambey-size-fields']],
    ];
    foreach (['length' => 'Length', 'width' => 'Width', 'height' => 'Height'] as $key => $title) {
      $form['size'][$key] = [
        '#type' => 'number',
        '#title' => $this->t('@title (@abbr)', ['@title' => $title, '@abbr' => strtoupper($key[0])]),
        '#required' => TRUE,
        '#step' => 0.1,
        '#min' => 0.1,
        '#default_value' => $settings->get('default_' . $key) ?? self::DEFAULT_DIMENSIONS[$key],
        '#ajax' => ['callback' => '::updatePriceAjax', 'event' => 'change', 'wrapper' => 'price-wrapper'],
      ];
    }

    $form['board_grade'] = [
      '#type' => 'radios',
      '#title' => $this->t('Board grade'),
      '#description' => $this->t('Choose the corrugated board strength for this box.'),
      '#options' => $this->decorateBoardOptions($board_options),
      '#default_value' => $this->optionDefault($board_options, (string) ($settings->get('default_board_grade') ?? '3ply')),
      '#required' => TRUE,
      '#attributes' => ['class' => ['ambey-choice-group', 'ambey-step-field', 'ambey-board-field']],
      '#ajax' => ['callback' => '::updatePriceAjax', 'event' => 'change', 'wrapper' => 'price-wrapper'],
    ];

    $form['color'] = [
      '#type' => 'radios',
      '#title' => $this->t('Colour of box'),
      '#description' => $this->t('Please select the colour of the outer paper of your box.'),
      '#options' => ['brown' => $this->t('Brown Kraft'), 'white' => $this->t('White')],
      '#default_value' => (string) ($settings->get('default_color') ?? 'brown'),
      '#required' => TRUE,
      '#attributes' => ['class' => ['ambey-choice-group', 'ambey-step-field', 'ambey-color-field']],
      '#ajax' => ['callback' => '::updatePriceAjax', 'event' => 'change', 'wrapper' => 'price-wrapper'],
    ];

    $form['shape'] = [
      '#type' => 'radios',
      '#title' => $this->t('Shape of box'),
      '#description' => $this->t('Please select the shape of your box.'),
      '#options' => $this->decorateShapeOptions($shape_options),
      '#default_value' => $this->optionDefault($shape_options, (string) ($settings->get('default_shape') ?? 'regular')),
      '#required' => TRUE,
      '#attributes' => ['class' => ['ambey-choice-group', 'ambey-step-field', 'ambey-shape-field']],
      '#ajax' => ['callback' => '::updatePriceAjax', 'event' => 'change', 'wrapper' => 'price-wrapper'],
    ];

    $form['print'] = [
      '#type' => 'radios',
      '#title' => $this->t('Print'),
      '#description' => $this->t('Indicate if you require any print applying to your box.'),
      '#options' => ['none' => $this->t('No Print'), 'single' => $this->t('Single Colour'), 'multi' => $this->t('Multi Colour (CMYK)')],
      '#default_value' => (string) ($settings->get('default_print') ?? 'none'),
      '#required' => TRUE,
      '#attributes' => ['class' => ['ambey-choice-group', 'ambey-step-field', 'ambey-print-field']],
      '#ajax' => ['callback' => '::updatePriceAjax', 'event' => 'change', 'wrapper' => 'price-wrapper'],
    ];

    $form['quality'] = [
      '#type' => 'radios',
      '#title' => $this->t('Quality'),
      '#description' => $this->t('Choose your own quality.'),
      '#options' => ['standard' => $this->t('Standard'), 'premium' => $this->t('Premium'), 'export' => $this->t('Export Quality')],
      '#default_value' => (string) ($settings->get('default_quality') ?? 'standard'),
      '#required' => TRUE,
      '#attributes' => ['class' => ['ambey-choice-group', 'ambey-step-field', 'ambey-quality-field']],
      '#ajax' => ['callback' => '::updatePriceAjax', 'event' => 'change', 'wrapper' => 'price-wrapper'],
    ];

    $form['coating'] = [
      '#type' => 'radios',
      '#title' => $this->t('Coating'),
      '#options' => ['' => $this->t('None'), 'gloss' => $this->t('Gloss Lamination'), 'matt' => $this->t('Matt Lamination'), 'spotuv' => $this->t('Spot UV'), 'emboss' => $this->t('Emboss')],
      '#default_value' => '',
      '#description' => $this->t('Coating is available only for white color box. Please leave blank if you do not want coating.'),
      '#attributes' => ['class' => ['ambey-choice-group', 'ambey-step-field', 'ambey-coating-field']],
      '#ajax' => ['callback' => '::updatePriceAjax', 'event' => 'change', 'wrapper' => 'price-wrapper'],
    ];

    $form['shipping'] = [
      '#type' => 'select',
      '#title' => $this->t('Shipping'),
      '#options' => $shipping_options,
      '#default_value' => $this->optionDefault($shipping_options, (string) ($settings->get('default_shipping') ?? 'local')),
      '#required' => TRUE,
      '#wrapper_attributes' => ['class' => ['ambey-step-field', 'ambey-shipping-field']],
      '#ajax' => ['callback' => '::updatePriceAjax', 'event' => 'change', 'wrapper' => 'price-wrapper'],
    ];

    $form['quantity'] = [
      '#type' => 'number',
      '#title' => $this->t('Quantity'),
      '#required' => TRUE,
      '#min' => 1,
      '#default_value' => (int) ($settings->get('default_quantity') ?? self::DEFAULT_QUANTITY),
      '#wrapper_attributes' => ['class' => ['ambey-step-field', 'ambey-quantity-field']],
      '#ajax' => ['callback' => '::updatePriceAjax', 'event' => 'change', 'wrapper' => 'price-wrapper'],
    ];

    $form['customer'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Customer Details'),
      '#attributes' => ['class' => ['ambey-fieldset', 'ambey-customer-fields']],
    ];
    $form['customer']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Full Name'),
      '#required' => TRUE,
      '#attributes' => ['placeholder' => $this->t('Enter your full name')],
    ];
    $form['customer']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Email'),
      '#required' => TRUE,
      '#attributes' => ['placeholder' => $this->t('Enter your email address')],
    ];
    $form['customer']['phone'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Phone Number'),
      '#required' => TRUE,
      '#attributes' => ['placeholder' => $this->t('Enter your phone number')],
    ];
    $form['customer']['company'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Company Name'),
      '#attributes' => ['placeholder' => $this->t('Enter your company name')],
    ];
    $form['customer']['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message / Requirement'),
      '#rows' => 3,
      '#attributes' => ['placeholder' => $this->t('Enter any specific requirement...')],
    ];

    $form['pricing'] = ['#type' => 'container', '#attributes' => ['id' => 'price-wrapper', 'class' => ['price-panel']]];
    $form['pricing']['#cache'] = [
      'max-age' => 0,
    ];
    $this->buildPricingPanel($form, $form_state);

    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['ambey-form-actions']],
    ];
    $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Email Quotation'), '#attributes' => ['class' => ['ambey-submit-button']]];
    $form['actions']['update_price'] = [
      '#type' => 'submit',
      '#value' => $this->t('Calculate Price'),
      '#submit' => ['::updatePriceSubmit'],
      '#ajax' => [
        'callback' => '::updatePriceAjax',
        'wrapper' => 'price-wrapper',
      ],
      '#limit_validation_errors' => [
        ['length'],
        ['width'],
        ['height'],
        ['board_grade'],
        ['color'],
        ['shape'],
        ['print'],
        ['quality'],
        ['coating'],
        ['shipping'],
        ['quantity'],
      ],
      '#attributes' => ['class' => ['ambey-update-price-button']],
    ];

    $form['main'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ambey-calculator-main']],
      '#weight' => 0,
    ];
    $form['sidebar'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['ambey-calculator-sidebar']],
      '#weight' => 1,
    ];

    foreach (['size', 'board_grade', 'color', 'shape', 'print', 'quality', 'coating', 'shipping', 'quantity', 'customer', 'actions'] as $key) {
      $form['main'][$key] = $form[$key];
      unset($form[$key]);
    }
    $form['sidebar']['pricing'] = $form['pricing'];
    unset($form['pricing']);

    return $form;
  }

  public function updatePriceSubmit(array &$form, FormStateInterface $form_state): void {
    $form_state->setRebuild(TRUE);
  }

  public function updatePriceAjax(array &$form, FormStateInterface $form_state): array {
    $form_state->setRebuild(TRUE);
    return $form['sidebar']['pricing'] ?? $form['pricing'];
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $errors = \Drupal::service('ambey_box_calculator.rules')->validate($this->extractData($form_state));
    foreach ($errors as $field => $message) {
      $form_state->setErrorByName($field, $this->t($message));
    }

    if (!$errors && $this->calculateBasePrice($form_state) <= 0) {
      $form_state->setErrorByName('quantity', $this->t('No pricing slab found for the selected shape, board grade, and quantity.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $data = $this->extractData($form_state);
    $pricing = \Drupal::service('ambey_box_calculator.pricing');
    $prices = $pricing->calculateQuotePrices($data);
    $quantity = (int) $data['quantity'];

    $quote_data = [
      'created' => time(),
      'name' => $data['name'],
      'email' => $data['email'],
      'phone' => $data['phone'],
      'company' => $data['company'],
      'product_type' => $data['shape'],
      'shape' => $data['shape'],
      'board_grade' => $data['board_grade'],
      'length' => $data['length'],
      'width' => $data['width'],
      'height' => $data['height'],
      'color' => $data['color'],
      'print_type' => $data['print'],
      'quality' => $data['quality'],
      'coating' => $data['coating'],
      'shipping_zone' => $data['shipping'],
      'quantity' => $quantity,
      'description' => $data['message'],
      'base_price' => $prices['base_price'],
      'gst' => $prices['gst'],
      'final_price' => $prices['final_price'],
      'total_without_gst' => $prices['total_without_gst'],
      'total_with_gst' => $prices['total_with_gst'],
    ];

    $quote_id = \Drupal::service('ambey_box_calculator.quote')->saveQuote($quote_data);
    $quote_data['id'] = $quote_id;
    $quote_data = \Drupal::service('ambey_box_calculator.quote')->loadQuote($quote_id) ?: $quote_data;
    \Drupal::queue('ambey_quote_queue')->createItem($quote_data);
    \Drupal::logger('ambey_box_calculator')->notice('Quote created ID=@id for @email', ['@id' => $quote_id, '@email' => $quote_data['email']]);

    $form_state->setRedirect('ambey_box_calculator.confirmation', ['quote_id' => $quote_data['uuid']]);
  }

  private function buildPricingPanel(array &$form, FormStateInterface $form_state): void {
    $data = $this->extractData($form_state);
    $quantity = (int) $data['quantity'];
    $result = \Drupal::service('ambey_box_calculator.pricing')->calculateQuotePrices($data);

    $form['pricing']['title'] = ['#markup' => '<h3>Quotation Summary</h3>'];
    $form['pricing']['selected'] = [
      '#markup' => '<div class="ambey-selected-box"><div class="ambey-mini-box"></div><div><strong>Selected Box</strong><span>' . Html::escape($data['length'] . ' × ' . $data['width'] . ' × ' . $data['height'] . ' in') . '</span><span>' . Html::escape($data['board_grade'] . ' | ' . $data['shape']) . '</span><span>' . Html::escape($data['color'] . ' | ' . $data['print']) . '</span><span>' . $this->t('Quantity: @quantity', ['@quantity' => $quantity]) . '</span></div></div>',
    ];
    if ($result['base_price'] <= 0) {
      $form['pricing']['warning'] = ['#markup' => '<div class="ambey-price-empty">Choose a priced shape, board grade, shipping zone, and quantity.</div>'];
      return;
    }

    $form['pricing']['quantity'] = ['#markup' => '<div class="ambey-price-row"><span>Quantity</span><strong>' . $quantity . '</strong></div>'];
    $form['pricing']['base'] = ['#markup' => '<div class="ambey-price-row"><span>Box Price Without GST</span><strong>' . PricingCalculator::formatIndianCurrency($result['base_price']) . '</strong></div>'];
    $form['pricing']['gst'] = ['#markup' => '<div class="ambey-price-row"><span>GST (12%)</span><strong>' . PricingCalculator::formatIndianCurrency($result['gst']) . '</strong></div>'];
    $form['pricing']['final'] = ['#markup' => '<div class="ambey-price-row"><span>Final Price Per Box</span><strong>' . PricingCalculator::formatIndianCurrency($result['final_price']) . '</strong></div>'];
    if ($quantity > 0) {
      $form['pricing']['total'] = ['#markup' => '<div class="ambey-price-total"><span>Total With GST</span><strong>' . PricingCalculator::formatIndianCurrency($result['total_with_gst']) . '</strong></div>'];
    }
    if (!empty($result['calculation_debug'])) {
      $form['pricing']['debug'] = ['#markup' => $this->buildCalculationDebugMarkup($result['calculation_debug'])];
    }
    $form['pricing']['trust'] = ['#markup' => '<div class="ambey-trust-list"><div><strong>Best Price Guarantee</strong><span>Get competitive prices instantly.</span></div><div><strong>Secure & Reliable</strong><span>Your information is safe with us.</span></div><div><strong>Quick Response</strong><span>We will get back within 24 hours.</span></div></div>'];
  }

  private function buildCalculationDebugMarkup(array $debug): string {
    $labels = [
      'box_area_formula' => 'Area Formula',
      'box_area_sq_in' => 'Box Area',
      'reference_area_sq_in' => 'Reference Area',
      'dimension_multiplier' => 'Dimension Multiplier',
      'slab_price_per_box' => 'Slab Price',
      'board_grade_factor' => 'Board Factor',
      'size_board_price' => 'Size + Board Price',
      'print_addon' => 'Print Add-on',
      'coating_addon' => 'Coating Add-on',
      'shipping_addon' => 'Shipping Add-on',
      'base_price_formula' => 'Base Formula',
      'base_price' => 'Base Price',
      'gst_rate' => 'GST Rate',
      'gst' => 'GST',
      'final_price' => 'Final Per Box',
      'total_with_gst' => 'Total With GST',
    ];
    $currency_keys = ['slab_price_per_box', 'size_board_price', 'print_addon', 'coating_addon', 'shipping_addon', 'base_price', 'gst', 'final_price', 'total_with_gst'];
    $rows = '';

    foreach ($labels as $key => $label) {
      if (!array_key_exists($key, $debug)) {
        continue;
      }
      $value = $debug[$key];
      if (in_array($key, $currency_keys, TRUE)) {
        $value = PricingCalculator::formatIndianCurrency($value);
      }
      elseif ($key === 'gst_rate') {
        $value = ((float) $value * 100) . '%';
      }
      $rows .= '<div><span>' . Html::escape($label) . '</span><strong>' . Html::escape((string) $value) . '</strong></div>';
    }

    return '<div class="ambey-price-debug"><h4>Calculation Debug</h4>' . $rows . '</div>';
  }

  private function calculateBasePrice(FormStateInterface $form_state): float {
    $data = $this->extractData($form_state);
    if (empty($data['shape']) || empty($data['board_grade']) || empty($data['quantity'])) {
      return 0.0;
    }
    return \Drupal::service('ambey_box_calculator.pricing')->getPriceWithAddons(
      (string) $data['shape'],
      (string) $data['board_grade'],
      (int) $data['quantity'],
      (string) $data['print'],
      (string) $data['coating'],
      (string) $data['color'],
      (string) $data['shipping'],
      (float) $data['length'],
      (float) $data['width'],
      (float) $data['height']
    );
  }

  private function extractData(FormStateInterface $form_state): array {
    $settings = $this->config('ambey_box_calculator.settings');
    return [
      'length' => (float) ($form_state->getValue('length') ?: ($settings->get('default_length') ?? self::DEFAULT_DIMENSIONS['length'])),
      'width' => (float) ($form_state->getValue('width') ?: ($settings->get('default_width') ?? self::DEFAULT_DIMENSIONS['width'])),
      'height' => (float) ($form_state->getValue('height') ?: ($settings->get('default_height') ?? self::DEFAULT_DIMENSIONS['height'])),
      'quantity' => (int) ($form_state->getValue('quantity') ?: ($settings->get('default_quantity') ?? self::DEFAULT_QUANTITY)),
      'board_grade' => (string) ($form_state->getValue('board_grade') ?: ($settings->get('default_board_grade') ?? '3ply')),
      'color' => (string) ($form_state->getValue('color') ?: ($settings->get('default_color') ?? 'brown')),
      'shape' => (string) ($form_state->getValue('shape') ?: ($settings->get('default_shape') ?? 'regular')),
      'print' => (string) ($form_state->getValue('print') ?: ($settings->get('default_print') ?? 'none')),
      'quality' => (string) ($form_state->getValue('quality') ?: ($settings->get('default_quality') ?? 'standard')),
      'coating' => (string) $form_state->getValue('coating'),
      'shipping' => (string) ($form_state->getValue('shipping') ?: ($settings->get('default_shipping') ?? 'local')),
      'name' => (string) $form_state->getValue('name'),
      'email' => (string) $form_state->getValue('email'),
      'phone' => (string) $form_state->getValue('phone'),
      'company' => (string) $form_state->getValue('company'),
      'message' => (string) $form_state->getValue('message'),
    ];
  }

  private function optionDefault(array $options, string $preferred): string {
    if ($preferred !== '' && isset($options[$preferred])) {
      return $preferred;
    }
    return (string) array_key_first($options);
  }

  private function decorateBoardOptions(array $options): array {
    $labels = [
      '3ply' => $this->t('3 Ply'),
      '5ply' => $this->t('5 Ply'),
      '7ply' => $this->t('7 Ply'),
    ];
    foreach ($labels as $key => $label) {
      if (isset($options[$key])) {
        $options[$key] = $label;
      }
    }
    return $options;
  }

  private function decorateShapeOptions(array $options): array {
    foreach ($options as $key => $label) {
      $options[$key] = $key === 'regular' ? $this->t('Regular Box') : $label;
    }
    return $options;
  }

  private function getShapeOptions(): array {
    $options = [];
    $entities = \Drupal::entityTypeManager()->getStorage('ambey_shape')->loadMultiple();
    foreach ($entities as $entity) {
      if ($entity->get('enabled')) {
        $options[$entity->id()] = $entity->label();
      }
    }
    return $options ?: ['regular' => $this->t('Regular Box'), 'pizza' => $this->t('Pizza Box')];
  }

  private function getBoardGradeOptions(): array {
    $labels = [
      'mono' => $this->t('Mono Carton'),
      '3ply' => $this->t('3 Ply'),
      '5ply' => $this->t('5 Ply'),
      '7ply' => $this->t('7 Ply'),
    ];
    $available_boards = \Drupal::service('ambey_box_calculator.pricing')->getAvailableBoardGrades();

    $options = [];
    foreach ($labels as $id => $label) {
      if (in_array($id, $available_boards, TRUE)) {
        $options[$id] = $label;
      }
    }

    foreach ($available_boards as $id) {
      $options[$id] ??= $id;
    }

    return $options ?: $labels;
  }

  private function getShippingOptions(): array {
    $options = [];
    $entities = \Drupal::entityTypeManager()->getStorage('ambey_shipping_zone')->loadMultiple();
    foreach ($entities as $entity) {
      $options[$entity->id()] = $entity->label();
    }
    return $options ?: ['local' => $this->t('Local'), 'regional' => $this->t('Regional'), 'national' => $this->t('National')];
  }

}
