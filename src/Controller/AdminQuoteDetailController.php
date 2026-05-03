<?php

namespace Drupal\ambey_box_calculator\Controller;

use Drupal\ambey_box_calculator\Form\AdminQuoteManageForm;
use Drupal\ambey_box_calculator\PricingCalculator;
use Drupal\ambey_box_calculator\QuoteStorage;
use Drupal\Core\Controller\ControllerBase;

class AdminQuoteDetailController extends ControllerBase {
  public function view(string $quote_id): array {
    $storage = \Drupal::service('ambey_box_calculator.quote');
    $quote = $storage->loadQuoteByUuid($quote_id);
    if (!$quote) {
      return ['#markup' => $this->t('Quote not found.')];
    }
    $quote['status_label'] = QuoteStorage::statusLabel($quote['status'] ?? 'new');
    $quote['priority_label'] = QuoteStorage::priorityLabel($quote['priority'] ?? 'medium');
    $quote['assigned_to_label'] = !empty($quote['assigned_uid']) ? $this->userName((int) $quote['assigned_uid']) : $this->t('Unassigned');
    $quote['created_label'] = date('d M Y H:i', (int) $quote['created']);
    $quote['changed_label'] = !empty($quote['changed']) ? date('d M Y H:i', (int) $quote['changed']) : '';
    $quote = $this->formatQuoteAmounts($quote);

    return [
      '#theme' => 'admin_quote_detail',
      '#quote' => $quote,
      '#notes' => $this->decorateUsers($storage->loadNotes((int) $quote['id'])),
      '#audit' => $this->decorateUsers($storage->loadAudit((int) $quote['id'])),
      '#manage_form' => $this->currentUser()->hasPermission('edit quotes') ? \Drupal::formBuilder()->getForm(AdminQuoteManageForm::class, $quote) : NULL,
      '#attached' => ['library' => ['ambey_box_calculator/admin_quotes']],
    ];
  }

  private function decorateUsers(array $rows): array {
    foreach ($rows as &$row) {
      $row['user_label'] = !empty($row['uid']) ? $this->userName((int) $row['uid']) : $this->t('System');
      $row['created_label'] = date('d M Y H:i', (int) $row['created']);
    }
    return $rows;
  }

  private function formatQuoteAmounts(array $quote): array {
    foreach (['base_price', 'gst', 'final_price', 'total_without_gst', 'total_with_gst'] as $field) {
      $quote[$field] = PricingCalculator::formatIndianNumber($quote[$field] ?? 0);
    }
    return $quote;
  }

  private function userName(int $uid): string {
    $account = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    return $account ? $account->getDisplayName() : (string) $this->t('User @uid', ['@uid' => $uid]);
  }
}
