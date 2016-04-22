<?php

/**
 * @file
 * Hooks provided by the TMGMT courier module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Add types of template collections.
 *
 * @param array $types
 *   Array of information on filters exposed by filter plugins.
 */
function hook_tmgmt_courier_trigger_types(&$types) {
  // Define the TemplateCollection types.
  $types['type_key'] = [
    'context' => 'tmgmt_courier',
    'label' => 'Type key',
    'description' => 'Description',
  ];
}

/**
 * @} End of "addtogroup hooks".
 */
