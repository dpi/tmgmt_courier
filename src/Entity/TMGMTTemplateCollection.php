<?php

namespace Drupal\tmgmt_courier\Entity;

use Drupal\courier\Entity\TemplateCollection;

/**
 * Defines a tmgmt_template_collection entity.
 *
 * @ContentEntityType(
 *   id = "tmgmt_template_collection",
 *   label = @Translation("Template collection"),
 *   handlers = {
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\tmgmt_courier\Form\AddNotificationForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *       "recipient" = "Drupal\tmgmt_courier\Form\RecipientForm",
 *     },
 *     "list_builder" = "Drupal\Core\Entity\EntityListBuilder",
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData"
 *   },
 *   base_table = "tmgmt_template_collection",
 *   entity_keys = {
 *     "id" = "id",
 *   },
 *   links = {
 *     "add-form" = "/admin/config/communication/courier/template_collections/add",
 *     "delete-form" = "/admin/config/communication/courier/template_collections/{tmgmt_template_collection}/delete",
 *     "recipient" = "/admin/config/communication/courier/template_collections/{tmgmt_template_collection}/recipient",
 *   }
 * )
 */
class TMGMTTemplateCollection extends TemplateCollection {

}
