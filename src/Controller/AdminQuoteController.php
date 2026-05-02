<?php

namespace Drupal\ambey_box_calculator\Controller;

use Drupal\ambey_box_calculator\QuoteStorage;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class AdminQuoteController extends ControllerBase {
  public function dashboard(Request $request): array {
    $filters = $this->getFilters($request);
    $storage = \Drupal::service('ambey_box_calculator.quote');
    $quotes = $storage->searchQuotes($filters, 250);

    $rows = [];
    foreach ($quotes as $row) {
      $assigned_to = !empty($row['assigned_uid']) ? $this->userName((int) $row['assigned_uid']) : $this->t('Unassigned');
      $rows[] = [
        'id' => '#' . $row['id'],
        'uuid' => $row['uuid'],
        'name' => $row['name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'company' => $row['company'] ?? '',
        'product' => $row['product_type'] ?: $row['shape'],
        'quantity' => $row['quantity'],
        'status' => [
          'key' => $row['status'] ?? 'new',
          'label' => QuoteStorage::statusLabel($row['status'] ?? 'new'),
        ],
        'priority' => [
          'key' => $row['priority'] ?? 'medium',
          'label' => QuoteStorage::priorityLabel($row['priority'] ?? 'medium'),
        ],
        'assigned_to' => $assigned_to,
        'date' => date('d M Y H:i', (int) $row['created']),
        'changed' => !empty($row['changed']) ? date('d M Y H:i', (int) $row['changed']) : '',
        'price' => '₹' . number_format((float) $row['total_with_gst'], 2),
        'view' => Url::fromRoute('ambey_box_calculator.quote_detail', ['quote_id' => $row['uuid']])->toString(),
        'download' => Url::fromRoute('ambey_box_calculator.download', ['quote_id' => $row['uuid']])->toString(),
      ];
    }

    return [
      '#theme' => 'admin_quote_dashboard',
      '#quotes' => $rows,
      '#summary' => $storage->getSummaryCounts(),
      '#filters' => $filters,
      '#status_options' => QuoteStorage::STATUSES,
      '#priority_options' => QuoteStorage::PRIORITIES,
      '#assigned_options' => $this->userOptions(),
      '#export_url' => Url::fromRoute('ambey_box_calculator.admin_quotes_export', [], ['query' => $request->query->all()])->toString(),
      '#attached' => ['library' => ['ambey_box_calculator/admin_quotes']],
    ];
  }

  public function export(Request $request): Response {
    $quotes = \Drupal::service('ambey_box_calculator.quote')->searchQuotes($this->getFilters($request), 10000);
    $handle = fopen('php://temp', 'r+');
    fputcsv($handle, ['Quote ID', 'UUID', 'Customer Name', 'Email', 'Phone', 'Company', 'Product / Service', 'Quantity', 'Status', 'Priority', 'Assigned To', 'Created Date', 'Last Updated', 'Total'], ',', '"', '');
    foreach ($quotes as $row) {
      fputcsv($handle, [
        $row['id'],
        $row['uuid'],
        $row['name'],
        $row['email'],
        $row['phone'],
        $row['company'] ?? '',
        $row['product_type'] ?: $row['shape'],
        $row['quantity'],
        QuoteStorage::statusLabel($row['status'] ?? 'new'),
        QuoteStorage::priorityLabel($row['priority'] ?? 'medium'),
        !empty($row['assigned_uid']) ? $this->userName((int) $row['assigned_uid']) : '',
        date('Y-m-d H:i:s', (int) $row['created']),
        !empty($row['changed']) ? date('Y-m-d H:i:s', (int) $row['changed']) : '',
        $row['total_with_gst'],
      ], ',', '"', '');
    }
    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return new Response($csv, 200, [
      'Content-Type' => 'text/csv; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="quotes-' . date('Y-m-d') . '.csv"',
    ]);
  }

  private function getFilters(Request $request): array {
    return [
      'keyword' => trim((string) $request->query->get('keyword')),
      'email' => trim((string) $request->query->get('email')),
      'phone' => trim((string) $request->query->get('phone')),
      'company' => trim((string) $request->query->get('company')),
      'status' => (string) $request->query->get('status'),
      'priority' => (string) $request->query->get('priority'),
      'assigned_uid' => $request->query->get('assigned_uid'),
      'product_type' => trim((string) $request->query->get('product_type')),
      'date_from' => (string) $request->query->get('date_from'),
      'date_to' => (string) $request->query->get('date_to'),
    ];
  }

  private function userOptions(): array {
    $options = [0 => $this->t('All assignees')];
    $users = \Drupal::entityTypeManager()->getStorage('user')->loadMultiple();
    foreach ($users as $user) {
      if ($user->id() > 0 && $user->isActive()) {
        $options[$user->id()] = $user->getDisplayName();
      }
    }
    return $options;
  }

  private function userName(int $uid): string {
    $account = \Drupal::entityTypeManager()->getStorage('user')->load($uid);
    return $account ? $account->getDisplayName() : (string) $this->t('User @uid', ['@uid' => $uid]);
  }
}
