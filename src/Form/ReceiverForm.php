<?php

namespace Drupal\tmgmt_courier\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for deleting an email.
 */
class ReceiverForm extends FormBase {

  /**
   * {@inheritdoc}
   *
   * @param int $notification_id
   *   The notification ID.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $notification_id = NULL) {
    $form_state->set('notification_id', $notification_id);

    $form['identity'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#selection_settings' => ['include_anonymous' => FALSE],
      '#title' => $this->t('Receiver'),
      '#description' => $this->t('Select the receiver.'),
    ];

    $form['actions'] = array('#type' => 'actions');
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save'),
    );
    $form['actions']['cancel'] = array(
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('entity.default_template_collection.collection'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $notification_id = $form_state->get('notification_id');
    $key_value = \Drupal::keyValue('tmgmt_courier_template_collections');
    $templates = array_filter($key_value->getAll(), function ($message_type) use ($notification_id) {
      return array_key_exists($notification_id, $message_type);
    });
    $value = reset($templates);
    $value[$notification_id]['identity'] = $form_state->getValue('identity');
    $key_value->set(key($templates), $value);
    drupal_set_message(t('Notification deleted.'));
    $form_state->setRedirect('entity.default_template_collection.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tmgmt_notification_receiver';
  }

}
