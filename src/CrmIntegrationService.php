<?php

namespace Drupal\ambey_box_calculator;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Sends quote data to a configured CRM webhook.
 */
class CrmIntegrationService {

  public function __construct(
    protected ClientInterface $httpClient,
    protected ConfigFactoryInterface $configFactory,
    protected LoggerInterface $logger,
  ) {}

  public function sendLead(array $quote_data): void {
    $config = $this->configFactory->get('ambey_box_calculator.crm');
    $url = (string) $config->get('webhook_url');
    $api_key = (string) $config->get('api_key');

    if ($url === '') {
      return;
    }

    try {
      $headers = ['Content-Type' => 'application/json'];
      if ($api_key !== '') {
        $headers['Authorization'] = 'Bearer ' . $api_key;
      }

      $this->httpClient->request('POST', $url, [
        'headers' => $headers,
        'json' => [
          'quote_id' => $quote_data['uuid'] ?? NULL,
          'internal_quote_id' => $quote_data['id'] ?? NULL,
          'name' => $quote_data['name'] ?? '',
          'email' => $quote_data['email'] ?? '',
          'phone' => $quote_data['phone'] ?? '',
          'shape' => $quote_data['shape'] ?? '',
          'quantity' => $quote_data['quantity'] ?? 0,
          'final_price' => $quote_data['final_price'] ?? 0,
          'total_with_gst' => $quote_data['total_with_gst'] ?? 0,
        ],
        'timeout' => 10,
      ]);
    }
    catch (\Throwable $e) {
      $this->logger->error('CRM send failed: @error', ['@error' => $e->getMessage()]);
    }
  }

}
