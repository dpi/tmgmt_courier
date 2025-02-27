<?php

namespace Drupal\tmgmt_courier\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form for changing the recipient.
 */
class RecipientForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
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
    $notification_id = $this->entity->id();
    $register = \Drupal::configFactory()->getEditable('tmgmt_courier.register');
    $templates = array_filter($register->getRawData(), function ($message_type) use ($notification_id) {
      return array_key_exists($notification_id, $message_type);
    });
    $value = reset($templates);
    $value[$notification_id]['identity'] = $form_state->getValue('identity');
    $register->set(key($templates), $value);
    $register->save();
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
