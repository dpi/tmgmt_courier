<?php

namespace Drupal\tmgmt_courier;

use Drupal\courier\Entity\TemplateCollection;
use Drupal\courier\MessageQueueItemInterface;
use Drupal\user\Entity\User;
use Drupal\tmgmt\JobItemInterface;

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
   *   The type of the notification. aka trigger
   * @param
   *   ------the context
   */
  public function sendNotification($type, $context = []) {
    /** @var \Drupal\courier\Service\CourierManagerInterface $courier_manager */
    $courier_manager = \Drupal::service('courier.manager');

    // Identity stuff.

    /** @var \Drupal\user\UserInterface[] $users */
    $users = [];

    // Send a notification to everyone with this permission, for the hell of it.
    $roles = array_keys(user_roles(TRUE, 'administer tmgmt'));
    if (count($roles)) {
      $user_storage = \Drupal::entityTypeManager()->getStorage('user');
      $uids = $user_storage
        ->getQuery()
        ->condition('roles', array_keys($roles), 'IN')
        ->execute();
      $users = $user_storage->loadMultiple($uids);
    }

    // Token stuff.

    $tokens = [];
    if (in_array($type, ['job_new'])) {
      /** @var \Drupal\tmgmt\JobInterface $job */
      $job = $context['job'];
      $tokens = ['tmgmt_job' => $job];

      // notify owner
      $users[] = $job->getOwner();
    }
    if (in_array($type, ['job_item_autoaccepted', 'job_item_needs_review'])) {
      /** @var JobItemInterface $job */
      $job_item = $context['job_item'];
      $tokens = ['tmgmt_job_item' => $job_item, 'tmgmt_job' => $job_item->getJob()];

      // notify owner
      $users[] = $job_item->getJob()->getOwner();
    }

    $tcids = \Drupal::state()->get('tmgmt_notification.tcids', []);
    if (!isset($tcids[$type])) {
      drupal_set_message('run the couriertemp form...');
    }

    $tcid = $tcids[$type];
    $tc_original = TemplateCollection::load($tcid);

    foreach ($users as $identity) {
      $tc = $tc_original->createDuplicate();

      foreach ($tokens as $token_key => $value) {
        $tc->setTokenValue($token_key, $value);
      }

      $result = $courier_manager->sendMessage($tc, $identity);
      if ($result instanceof MessageQueueItemInterface) {
        drupal_set_message(t('Message queued for delivery.'));
      }
      else {
        drupal_set_message(t('Failed to send message'), 'error');
      }
    }

//    $register = \Drupal::configFactory()->get('tmgmt_courier.register');
//    if ($template_collections = $register->get($type)) {
//      $mqi = NULL;
//      foreach ($template_collections as $id => $properties) {
//        if ($properties['enabled']) {
//          $template_collection = TemplateCollection::load($id);
//          foreach ($tokens as $token_key => $value) {
//            $template_collection->setTokenValue($token_key, $value);
//          }
//          /** @var \Drupal\user\Entity\User $identity */
//          $identity = User::load($properties['identity']);
//          $mqi = \Drupal::service('courier.manager')
//            ->sendMessage($template_collection, $identity);
//        }
//      }
//      if ($mqi instanceof MessageQueueItemInterface) {
//        drupal_set_message(t('Message queued for delivery.'));
//      }
//      else {
//        drupal_set_message(t('Failed to send message'), 'error');
//      }
//    }
  }

}
