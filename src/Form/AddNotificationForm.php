<?php

namespace Drupal\tmgmt_courier\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\courier\Entity\CourierContext;
use Drupal\courier\Entity\TemplateCollection;
use Drupal\courier\Service\CourierManagerInterface;
use Drupal\courier\TemplateCollectionInterface;
use Drupal\tmgmt_courier\CourierException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a rule with a rng_courier_message action.
 */
class AddNotificationForm extends EntityForm {

  /**
   * The courier manager.
   *
   * @var \Drupal\courier\Service\CourierManagerInterface
   */
  protected $courierManager;

  /**
   * Constructs a configuration form.
   *
   * @param \Drupal\courier\Service\CourierManagerInterface $courier_manager
   *   The courier manager.
   */
  public function __construct(CourierManagerInterface $courier_manager) {
    $this->courierManager = $courier_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\courier\Service\CourierManagerInterface $courierManager */
    $courierManager = $container->get('courier.manager');
    return new static($courierManager);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $types = \Drupal::service('tmgmt_courier.trigger_repository')->getAll();
    $options = array_map(function ($option) {
      return $option['label'];
    }, $types);

    $form['trigger'] = array(
      '#type' => 'select',
      '#title' => $this->t('Notifications'),
      '#description' => $this->t('Select the notification.'),
      '#options' => $options,
      '#default_value' => new FormattableMarkup(' - @text - ', ['@text' => t('Select')]),
    );

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
      '#value' => t('Create message'),
    );
    $form['actions']['cancel'] = array(
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('entity.tmgmt_template_collection.collection'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $register = \Drupal::configFactory()->getEditable('tmgmt_courier.register');

    // Create template collection.
    $type = $form_state->getValue('trigger');
    /** @var \Drupal\courier\Entity\TemplateCollection $template_collection */
    $template_collection = TemplateCollection::create();

    $types = \Drupal::service('tmgmt_courier.trigger_repository')->getAll();
    $context_id = $types[$type]['context'];

    // Create global context for accounts if it does not exist.
    /** @var \Drupal\courier\CourierContextInterface $courier_context */
    if (!$courier_context = CourierContext::load($context_id)) {
      throw new CourierException('Courier context %context_id does not exist.', ['%context_id' => $context_id]);
    }
    $template_collection->setContext($courier_context);

    if ($template_collection->save()) {
      $this->courierManager->addTemplates($template_collection);
      $this->copyTwigToCourierEmail($template_collection, $type);
      $template_collection->save();
    }

    $value = $register->get($type);
    $value[$template_collection->id()] = [
      'id' => $template_collection->id(),
      // @todo Add uuid key in TemplateCollection
      'uuid' => $template_collection->uuid(),
      'identity' => $form_state->getValue('identity'),
      'enabled' => FALSE,
    ];
    $register->set($type, $value);
    $register->save();

    $form_state->setRedirect('entity.tmgmt_template_collection.collection');
  }

  /**
   * Copy email contents from Drupal to Courier email templates.
   *
   * Template collection and email template must be created prior to calling.
   *
   * @param \Drupal\courier\TemplateCollectionInterface $template_collection
   *   A template collection entity.
   * @param string $template_id
   *   The twig template id.
   */
  protected function copyTwigToCourierEmail(TemplateCollectionInterface &$template_collection, $template_id) {
    $value = [
      '#access' => TRUE,
      '#theme' => $template_id,
    ];

    /** @var \Drupal\courier\Entity\Email $courier_email */
    if ($courier_email = $template_collection->getTemplate('courier_email')) {
      $courier_email
        ->setSubject('Subject')
        ->setBody(\Drupal::service('renderer')->renderPlain($value))
        ->setBodyFormat('full_html');

      $template_collection->setTemplate($courier_email);
    }
  }

}
