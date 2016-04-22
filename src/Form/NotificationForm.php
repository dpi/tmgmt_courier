<?php

namespace Drupal\tmgmt_courier\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Url;
use Drupal\courier\Entity\CourierContext;
use Drupal\courier\Entity\TemplateCollection;
use Drupal\courier\Service\CourierManagerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure Courier System settings.
 */
class NotificationForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The courier manager.
   *
   * @var \Drupal\courier\Service\CourierManagerInterface
   */
  protected $courierManager;

  /**
   * Constructs a configuration form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\courier\Service\CourierManagerInterface $courier_manager
   *   The courier manager.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_manager, CourierManagerInterface $courier_manager) {
    parent::__construct($config_factory);
    $this->entityManager = $entity_manager;
    $this->courierManager = $courier_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Config\ConfigFactoryInterface $configFactory */
    $configFactory = $container->get('config.factory');
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entityManager */
    $entityManager = $container->get('entity.manager');
    /** @var \Drupal\courier\Service\CourierManagerInterface $courierManager */
    $courierManager = $container->get('courier.manager');
    return new static($configFactory, $entityManager, $courierManager);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'tmgmt_notifications_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'tmgmt_notifications.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $key_value = \Drupal::keyValue('tmgmt_courier_template_collections');

    // Actions.
    $form['actions'] = [
      '#type' => 'details',
      '#attributes' => [
        'class' => ['container-inline'],
      ],
      '#open' => TRUE,
    ];
    $form['actions']['operation'] = [
      '#title' => $this->t('With selection'),
      '#type' => 'select',
      '#options' => [
        'enable' => $this->t('Enable messages'),
        'disable' => $this->t('Disable messages'),
        'delete' => $this->t('Delete messages'),
      ],
      '#default_value' => new FormattableMarkup(' - @text - ', ['@text' => t('Select')]),
      '#button_type' => 'primary',
    ];
    $form['actions']['apply'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply'),
    ];

    // List items.
    $form['list'] = [
      '#type' => 'courier_template_collection_list',
      '#checkboxes' => TRUE,
      '#items' => [],
    ];

    foreach ($key_value->getAll() as $template_collection_type => $template_collection_trigger) {
      if ($template_collections = TemplateCollection::loadMultiple(array_keys($template_collection_trigger))) {
        /** @var \Drupal\courier\Entity\TemplateCollection $template_collection */
        $template_collection = reset($template_collections);
        /** @var \Drupal\user\Entity\User $identity */
        $identity = User::load($template_collection_trigger[$template_collection->id()]['identity']);
        $definition = $types = \Drupal::service('tmgmt_courier.trigger_repository')->getDefinitionOfType($template_collection_type);
        /** @var \Drupal\courier\Entity\CourierContext $context */
        $context = CourierContext::load($definition['context']);
        $form['list']['#items'][$template_collection_type . '_' . $template_collection->id()] = [
          '#title' => $this->t('@module: @title (@status) To "@receiver"', [
            '@title' => $definition['label'],
            '@module' => \Drupal::moduleHandler()->getName($context->label()),
            '@status' => $template_collection_trigger[$template_collection->id()]['enabled'] ? $this->t('enabled') : $this->t('disabled'),
            '@receiver' => $identity->getDisplayName(),
          ]),
          '#description' => $definition['description'],
          '#template_collection' => $template_collection,
          '#operations' => $this->getOperations($template_collection->id()),
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $message = $this->t('No operations were executed.');

    // Template collections keyed by mail ID.
    /** @var \Drupal\courier\TemplateCollectionInterface[][] $template_collections */
    $template_collections = [];
    $key_value = \Drupal::keyValue('tmgmt_courier_template_collections');
    foreach ($key_value->getAll() as $mail_id => $template_collection_ids) {
      if ($partial_template_collections = TemplateCollection::loadMultiple(array_keys($template_collection_ids))) {
        /** @var \Drupal\courier\TemplateCollectionInterface $template_collection */
        foreach ($partial_template_collections as $template_collection) {
          $template_collections[$mail_id][$template_collection->id()] = $template_collection;
        }
      }
    }

    // List of checked mail IDs.
    $checkboxes = [];
    foreach ($form_state->getValue(['list', 'checkboxes']) as $id => $checked) {
      if ($checked) {
        $checkboxes[] = $id;
      }
    }

    $operation = $form_state->getValue('operation');
    foreach ($checkboxes as $key) {
      $last = strrpos($key, '_');
      $mail_id = trim(substr($key, 0, $last));
      $id = trim(substr($key, $last + 1));
      if (isset($template_collections[$mail_id][$id])) {
        if (in_array($operation, ['enable', 'disable'])) {
          $enable = $operation == 'enable';
          $value = $key_value->get($mail_id);
          $value[$id]['enabled'] = $enable;
          $key_value->set($mail_id, $value);
          $message = $enable ? $this->t('Messages enabled.') : $this->t('Messages disabled.');
        }
        elseif ($operation == 'delete') {
          $template_collections[$mail_id][$id]->delete();
          unset($template_collections[$mail_id][$id]);
          $value = $key_value->get($mail_id);
          unset($value[$id]);
          $key_value->set($mail_id, $value);
          $message = $this->t('Messages deleted');
        }
      }
    }

    drupal_set_message($message);
  }

  /**
   * Gets operations for a template.
   *
   * @param int $notification_id
   *   Template collection ID.
   *
   * @return array
   *   An array of operations.
   */
  protected function getOperations($notification_id) {
    $links = [];
    $destination = \Drupal::destination()->getAsArray();
    $links['delete'] = [
      'title' => $this->t('Delete'),
      'url' => new Url('entity.default_template_collection.delete_form', ['template_collection' => $notification_id]),
      'query' => $destination,
    ];
    $links['receiver'] = [
      'title' => $this->t('Change receiver'),
      'url' => new Url('entity.default_template_collection.receiver', ['template_collection' => $notification_id]),
      'query' => $destination,
    ];

    return $links;
  }

}
