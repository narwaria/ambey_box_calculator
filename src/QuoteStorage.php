<?php

namespace Drupal\ambey_box_calculator;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;

/**
 * Storage helper for quote records.
 */
class QuoteStorage {

  public const STATUSES = [
    'new' => 'New',
    'in_review' => 'In Review',
    'in_progress' => 'In Progress',
    'need_more_information' => 'Need More Information',
    'quote_prepared' => 'Quote Prepared',
    'quote_sent' => 'Quote Sent',
    'follow_up' => 'Follow Up',
    'on_hold' => 'On Hold',
    'converted' => 'Converted',
    'rejected' => 'Rejected',
    'closed' => 'Closed',
  ];

  public const PRIORITIES = [
    'low' => 'Low',
    'medium' => 'Medium',
    'high' => 'High',
    'urgent' => 'Urgent',
  ];

  public function __construct(
    protected Connection $database,
    protected UuidInterface $uuid,
  ) {}

  public function saveQuote(array $data): int {
    if (empty($data['uuid'])) {
      $data['uuid'] = $this->uuid->generate();
    }
    $data += [
      'status' => 'new',
      'priority' => 'medium',
      'product_type' => $data['shape'] ?? '',
      'changed' => $data['created'] ?? time(),
    ];

    $id = (int) $this->database->insert('ambey_box_quote')
      ->fields($data)
      ->execute();
    $this->addAudit($id, 'created', 'Quote created.', 0);
    return $id;
  }

  public function loadQuote(int $id): ?array {
    $quote = $this->database->select('ambey_box_quote', 'q')
      ->fields('q')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();
    return $quote ?: NULL;
  }

  public function loadQuoteByUuid(string $uuid): ?array {
    $quote = $this->database->select('ambey_box_quote', 'q')
      ->fields('q')
      ->condition('uuid', $uuid)
      ->execute()
      ->fetchAssoc();
    return $quote ?: NULL;
  }

  public function listQuotes(?string $email = NULL, ?string $date = NULL, int $limit = 50): array {
    return $this->searchQuotes(['email' => $email, 'date_from' => $date, 'date_to' => $date], $limit);
  }

  public function searchQuotes(array $filters = [], int $limit = 50): array {
    $query = $this->database->select('ambey_box_quote', 'q')->fields('q');
    if (!empty($filters['keyword'])) {
      $or = $query->orConditionGroup()
        ->condition('uuid', '%' . $this->database->escapeLike($filters['keyword']) . '%', 'LIKE')
        ->condition('name', '%' . $this->database->escapeLike($filters['keyword']) . '%', 'LIKE')
        ->condition('email', '%' . $this->database->escapeLike($filters['keyword']) . '%', 'LIKE')
        ->condition('phone', '%' . $this->database->escapeLike($filters['keyword']) . '%', 'LIKE')
        ->condition('company', '%' . $this->database->escapeLike($filters['keyword']) . '%', 'LIKE');
      $query->condition($or);
    }
    foreach (['email', 'phone', 'company', 'product_type'] as $field) {
      if (!empty($filters[$field])) {
        $query->condition($field, '%' . $this->database->escapeLike($filters[$field]) . '%', 'LIKE');
      }
    }
    foreach (['status', 'priority', 'assigned_uid'] as $field) {
      if ($filters[$field] !== NULL && $filters[$field] !== '') {
        $query->condition($field, $filters[$field]);
      }
    }
    if (!empty($filters['date_from'])) {
      $query->condition('created', strtotime($filters['date_from'] . ' 00:00:00'), '>=');
    }
    if (!empty($filters['date_to'])) {
      $query->condition('created', strtotime($filters['date_to'] . ' 23:59:59'), '<=');
    }

    return $query->orderBy('created', 'DESC')->range(0, $limit)->execute()->fetchAllAssoc('id', \PDO::FETCH_ASSOC);
  }

  public function getSummaryCounts(): array {
    $counts = ['total' => 0] + array_fill_keys(array_keys(self::STATUSES), 0);
    $query = $this->database->select('ambey_box_quote', 'q')
      ->fields('q', ['status']);
    $query->addExpression('COUNT(*)', 'count');
    $result = $query->groupBy('status')->execute();

    foreach ($result as $row) {
      $status = $row->status ?: 'new';
      $count = (int) $row->count;
      $counts[$status] = $count;
      $counts['total'] += $count;
    }
    return $counts;
  }

  public function updateManagementFields(string $uuid, array $values, string $note, int $uid): ?array {
    $quote = $this->loadQuoteByUuid($uuid);
    if (!$quote) {
      return NULL;
    }

    $fields = [
      'status' => $values['status'] ?? $quote['status'],
      'priority' => $values['priority'] ?? $quote['priority'],
      'assigned_uid' => $values['assigned_uid'] ?: NULL,
      'changed' => time(),
    ];

    if ((int) ($quote['assigned_uid'] ?? 0) !== (int) ($fields['assigned_uid'] ?? 0)) {
      $fields['assigned_by'] = $uid;
      $fields['assigned_date'] = time();
    }

    $this->database->update('ambey_box_quote')
      ->fields($fields)
      ->condition('id', $quote['id'])
      ->execute();

    if (($quote['status'] ?? 'new') !== $fields['status']) {
      $this->addAudit((int) $quote['id'], 'status_changed', 'Status changed from ' . self::statusLabel($quote['status'] ?? 'new') . ' to ' . self::statusLabel($fields['status']) . '.', $uid);
    }
    if (($quote['priority'] ?? 'medium') !== $fields['priority']) {
      $this->addAudit((int) $quote['id'], 'priority_changed', 'Priority changed from ' . self::priorityLabel($quote['priority'] ?? 'medium') . ' to ' . self::priorityLabel($fields['priority']) . '.', $uid);
    }
    if ((int) ($quote['assigned_uid'] ?? 0) !== (int) ($fields['assigned_uid'] ?? 0)) {
      $this->addAudit((int) $quote['id'], 'assignment_changed', 'Assigned user changed.', $uid);
    }
    if (trim($note) !== '') {
      $this->addNote((int) $quote['id'], $note, $uid);
    }

    return $this->loadQuoteByUuid($uuid);
  }

  public function addNote(int $quote_id, string $note, int $uid): void {
    $this->database->insert('ambey_box_quote_note')
      ->fields([
        'quote_id' => $quote_id,
        'uid' => $uid,
        'note' => $note,
        'created' => time(),
      ])
      ->execute();
    $this->addAudit($quote_id, 'note_added', 'Internal note added.', $uid);
  }

  public function loadNotes(int $quote_id): array {
    return $this->database->select('ambey_box_quote_note', 'n')
      ->fields('n')
      ->condition('quote_id', $quote_id)
      ->orderBy('created', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function loadAudit(int $quote_id): array {
    return $this->database->select('ambey_box_quote_audit', 'a')
      ->fields('a')
      ->condition('quote_id', $quote_id)
      ->orderBy('created', 'DESC')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function addAudit(int $quote_id, string $event, string $message, int $uid): void {
    if (!$this->database->schema()->tableExists('ambey_box_quote_audit')) {
      return;
    }
    $this->database->insert('ambey_box_quote_audit')
      ->fields([
        'quote_id' => $quote_id,
        'uid' => $uid,
        'event' => $event,
        'message' => $message,
        'created' => time(),
      ])
      ->execute();
  }

  public static function statusLabel(string $status): string {
    return self::STATUSES[$status] ?? $status;
  }

  public static function priorityLabel(string $priority): string {
    return self::PRIORITIES[$priority] ?? $priority;
  }

}
