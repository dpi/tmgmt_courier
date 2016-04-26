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

    $register = \Drupal::configFactory()->get('tmgmt_courier.register');

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

    foreach ($register->getRawData() as $template_collection_type => $template_collection_trigger) {
      if ($template_collections = TemplateCollection::loadMultiple(array_keys($template_collection_trigger))) {
        /** @var \Drupal\courier\Entity\TemplateCollection $template_collection */
        foreach ($template_collections as $template_collection) {
          $identity = NULL;
          if (isset($template_collection_trigger[$template_collection->id()]['identity'])) {
            /** @var \Drupal\user\Entity\User $identity */
            $identity = User::load($template_collection_trigger[$template_collection->id()]['identity']);
          }
          $definition = $types = \Drupal::service('tmgmt_courier.trigger_repository')
            ->getDefinitionOfType($template_collection_type);
          /** @var \Drupal\courier\Entity\CourierContext $context */
          $context = CourierContext::load($definition['context']);
          $form['list']['#items'][$template_collection_type . '_' . $template_collection->id()] = [
            '#title' => $this->t('@module: @title (@status) To "@receiver"', [
              '@title' => $definition['label'],
              '@module' => \Drupal::moduleHandler()->getName($context->label()),
              '@status' => $template_collection_trigger[$template_collection->id()]['enabled'] ? $this->t('enabled') : $this->t('disabled'),
              '@receiver' => $identity ? $identity->getDisplayName() : t('default'),
            ]),
            '#description' => $definition['description'],
            '#template_collection' => $template_collection,
            '#operations' => $this->getOperations($template_collection->id()),
          ];
        }
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
    $register = \Drupal::configFactory()->getEditable('tmgmt_courier.register');
    foreach ($register->getRawData() as $type => $template_collection_ids) {
      if ($partial_template_collections = TemplateCollection::loadMultiple(array_keys($template_collection_ids))) {
        /** @var \Drupal\courier\TemplateCollectionInterface $template_collection */
        foreach ($partial_template_collections as $template_collection) {
          $template_collections[$type][$template_collection->id()] = $template_collection;
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
      $type = trim(substr($key, 0, $last));
      $id = trim(substr($key, $last + 1));
      if (isset($template_collections[$type][$id])) {
        if (in_array($operation, ['enable', 'disable'])) {
          $enable = $operation == 'enable';
          $value = $register->get($type);
          $value[$id]['enabled'] = $enable;
          $register->set($type, $value);
          $register->save();
          $message = $enable ? $this->t('Messages enabled.') : $this->t('Messages disabled.');
        }
        elseif ($operation == 'delete') {
          $template_collections[$type][$id]->delete();
          unset($template_collections[$type][$id]);
          $value = $register->get($type);
          unset($value[$id]);
          $register->set($type, $value);
          $register->save();
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
      'url' => new Url('entity.tmgmt_template_collection.delete_form', ['tmgmt_template_collection' => $notification_id]),
      'query' => $destination,
    ];
    $links['recipient'] = [
      'title' => $this->t('Change receiver'),
      'url' => new Url('entity.tmgmt_template_collection.recipient', ['tmgmt_template_collection' => $notification_id]),
      'query' => $destination,
    ];

    return $links;
  }

}
