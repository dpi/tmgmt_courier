<?php

namespace Drupal\tmgmt_courier;

use Drupal\Core\Session\AccountInterface;
use Drupal\courier\Entity\TemplateCollection;
use Drupal\courier\MessageQueueItemInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

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
   * @param \Drupal\Core\Session\AccountInterface $default_identity
   *   The default identity.
   */
  public function sendNotification($type, array $tokens, AccountInterface $default_identity) {
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
          if (isset($properties['identity'])) {
            $identity = User::load($properties['identity']);
          }
          else {
            $identity = $default_identity;
          }
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
