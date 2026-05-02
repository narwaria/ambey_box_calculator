<?php

namespace Drupal\ambey_box_calculator\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Processes quote queue items.
 *
 * @QueueWorker(
 *   id = "ambey_quote_queue",
 *   title = @Translation("Ambey Quote Processing Queue"),
 *   cron = {"time" = 30}
 * )
 */
class QuoteProcessingQueue extends QueueWorkerBase {
  public function processItem($data): void {
    $logger = \Drupal::logger('ambey_box_calculator');
    try {
      $pdf = \Drupal::service('ambey_box_calculator.pdf')->generateQuotePdf($data);
      $mail_manager = \Drupal::service('plugin.manager.mail');
      $mail_manager->mail('ambey_box_calculator', 'send_quote', $data['email'], \Drupal::languageManager()->getDefaultLanguage()->getId(), ['file' => $pdf]);
      $admin_mail = \Drupal::config('system.site')->get('mail');
      if ($admin_mail) {
        $mail_manager->mail('ambey_box_calculator', 'send_quote', $admin_mail, \Drupal::languageManager()->getDefaultLanguage()->getId(), ['file' => $pdf]);
      }
      \Drupal::service('ambey_box_calculator.crm')->sendLead($data);
      $logger->notice('Quote queue processed for Quote ID=@id.', ['@id' => $data['id'] ?? 'unknown']);
    }
    catch (\Throwable $e) {
      $logger->error('Quote processing failed: @error', ['@error' => $e->getMessage()]);
      throw $e;
    }
  }
}
