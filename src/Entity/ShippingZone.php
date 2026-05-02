<?php

namespace Drupal\ambey_box_calculator\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the shipping zone config entity.
 *
 * @ConfigEntityType(
 *   id = "ambey_shipping_zone",
 *   label = @Translation("Shipping Zone"),
 *   handlers = {
 *     "list_builder" = "Drupal\ambey_box_calculator\ShippingZoneListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ambey_box_calculator\Form\ShippingZoneForm",
 *       "edit" = "Drupal\ambey_box_calculator\Form\ShippingZoneForm",
 *       "delete" = "Drupal\ambey_box_calculator\Form\ShippingZoneDeleteForm"
 *     }
 *   },
 *   config_prefix = "shipping_zone",
 *   admin_permission = "manage ambey shipping",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "cost",
 *     "delivery_days"
 *   },
 *   links = {
 *     "collection" = "/admin/config/ambey/shipping",
 *     "add-form" = "/admin/config/ambey/shipping/add",
 *     "edit-form" = "/admin/config/ambey/shipping/{ambey_shipping_zone}",
 *     "delete-form" = "/admin/config/ambey/shipping/{ambey_shipping_zone}/delete"
 *   }
 * )
 */
class ShippingZone extends ConfigEntityBase implements ShippingZoneInterface {
  protected $id;
  protected $label;
  public float $cost = 0.0;
  public int $delivery_days = 0;
}
