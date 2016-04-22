<?php

namespace Drupal\tmgmt_courier;

use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Retrieves all the types of TemplateCollections.
 *
 * @ingroup tmgmt_notifications
 */
class TriggerRepository {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * TriggerRepository constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Invoke hook_tmgmt_courier_trigger_types() implementations.
   *
   * @return array
   *   All the TemplateCollection types.
   */
  public function getAll() {
    $types = [];
    $modules = $this->moduleHandler->getImplementations('tmgmt_courier_trigger_types');
    foreach ($modules as $module) {
      $hook = $module . '_tmgmt_courier_trigger_types';
      $hook($types);
    }
    return $types;
  }

  /**
   * Get definition of a type by ID.
   *
   * @param int $id
   *   The id of the type.
   *
   * @return array
   *   The definition of this TemplateCollection type.
   */
  public function getDefinitionOfType($id) {
    $modules = $this->moduleHandler->getImplementations('tmgmt_courier_trigger_types');
    foreach ($modules as $module) {
      $types = [];
      $hook = $module . '_tmgmt_courier_trigger_types';
      $hook($types);
      if (isset($types[$id])) {
        return $types[$id];
      }
    }
    return [];
  }

}
