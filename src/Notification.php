<?php

namespace Drupal\tmgmt_courier;

use Drupal\courier\Entity\TemplateCollection;
use Drupal\courier\MessageQueueItemInterface;
use Drupal\user\Entity\User;

/**
 * Represents a TMGMT Notification.
 *
 * @ingroup tmgmt_notifications
 */
class Notification {

  /**
   * Send a notification.
   *
   * @param string $type
   *   The type of the notification.
   * @param array $tokens
   *   Array of tokens.
   */
  public function sendNotification($type, array $tokens) {
    $register = \Drupal::configFactory()->get('tmgmt_courier.register');
    if ($template_collections = $register->get($type)) {
      $mqi = NULL;
      foreach ($template_collections as $id => $properties) {
        if ($properties['enabled']) {
          $template_collection = TemplateCollection::load($id);
          foreach ($tokens as $token_key => $value) {
            $template_collection->setTokenValue($token_key, $value);
          }
          /** @var \Drupal\user\Entity\User $identity */
          $identity = User::load($properties['identity']);
          $mqi = \Drupal::service('courier.manager')
            ->sendMessage($template_collection, $identity);
        }
      }
      if ($mqi instanceof MessageQueueItemInterface) {
        drupal_set_message(t('Message queued for delivery.'));
      }
      else {
        drupal_set_message(t('Failed to send message'), 'error');
      }
    }
  }

}
