<?php

namespace Drupal\ambey_box_calculator\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ApiController extends ControllerBase {
  public function calculate(Request $request): JsonResponse {
    $data = $this->decodeJson($request);
    $errors = \Drupal::service('ambey_box_calculator.rules')->validate($data);
    if ($errors) {
      return new JsonResponse(['status' => 'error', 'errors' => $errors], 400);
    }
    $result = $this->calculatePrices($data);
    if ($result['base_price'] <= 0) {
      return new JsonResponse([
        'status' => 'error',
        'errors' => [
          'quantity' => 'No pricing slab found for the selected shape, board grade, and quantity.',
        ],
      ], 400);
    }
    return new JsonResponse($result);
  }

  public function createQuote(Request $request): JsonResponse {
    $data = $this->decodeJson($request);
    $errors = \Drupal::service('ambey_box_calculator.rules')->validate($data);
    foreach (['name', 'email', 'phone'] as $field) {
      if (empty($data[$field])) {
        $errors[$field] = ucfirst($field) . ' is required.';
      }
    }
    if ($errors) {
      return new JsonResponse(['status' => 'error', 'errors' => $errors], 400);
    }

    $prices = $this->calculatePrices($data);
    if ($prices['base_price'] <= 0) {
      return new JsonResponse([
        'status' => 'error',
        'errors' => [
          'quantity' => 'No pricing slab found for the selected shape, board grade, and quantity.',
        ],
      ], 400);
    }
    $quantity = (int) $data['quantity'];
    $settings = $this->config('ambey_box_calculator.settings');
    $quote_data = [
      'created' => time(),
      'name' => $data['name'],
      'email' => $data['email'],
      'phone' => $data['phone'],
      'shape' => $data['shape'],
      'board_grade' => $data['board_grade'],
      'length' => (float) $data['length'],
      'width' => (float) $data['width'],
      'height' => (float) $data['height'],
      'color' => $data['color'] ?? $settings->get('default_color') ?? 'brown',
      'print_type' => $data['print'] ?? $settings->get('default_print') ?? 'none',
      'quality' => $data['quality'] ?? $settings->get('default_quality') ?? 'standard',
      'coating' => $data['coating'] ?? '',
      'shipping_zone' => $data['shipping'] ?? $data['shipping_zone'] ?? $settings->get('default_shipping') ?? '',
      'quantity' => $quantity,
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
    return new JsonResponse(['status' => 'created', 'quote_id' => $quote_data['uuid']] + $prices, 201);
  }

  public function getQuote(string $id): JsonResponse {
    $quote = \Drupal::service('ambey_box_calculator.quote')->loadQuoteByUuid($id);
    return $quote ? new JsonResponse($quote) : new JsonResponse(['error' => 'Quote not found'], 404);
  }

  public function listQuotes(): JsonResponse {
    return new JsonResponse(array_values(\Drupal::service('ambey_box_calculator.quote')->listQuotes(NULL, NULL, 100)));
  }

  private function calculatePrices(array $data): array {
    return \Drupal::service('ambey_box_calculator.pricing')->calculateQuotePrices($data);
  }

  private function decodeJson(Request $request): array {
    $data = json_decode($request->getContent(), TRUE);
    return is_array($data) ? $data : [];
  }
}
