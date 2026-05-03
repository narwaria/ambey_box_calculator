<?php

namespace Drupal\ambey_box_calculator\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the box pricing config entity.
 *
 * @ConfigEntityType(
 *   id = "ambey_pricing",
 *   label = @Translation("Box Pricing"),
 *   handlers = {
 *     "list_builder" = "Drupal\ambey_box_calculator\PricingListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ambey_box_calculator\Form\PricingForm",
 *       "edit" = "Drupal\ambey_box_calculator\Form\PricingForm",
 *       "delete" = "Drupal\ambey_box_calculator\Form\PricingDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   config_prefix = "pricing",
 *   admin_permission = "manage ambey pricing",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "shape",
 *     "board_grade",
 *     "quantity_from",
 *     "quantity_to",
 *     "price_per_box",
 *     "print_single_cost",
 *     "print_multi_cost",
 *     "coating_cost"
 *   },
 *   links = {
 *     "collection" = "/admin/config/ambey/pricing",
 *     "add-form" = "/admin/config/ambey/pricing/add",
 *     "edit-form" = "/admin/config/ambey/pricing/{ambey_pricing}",
 *     "delete-form" = "/admin/config/ambey/pricing/{ambey_pricing}/delete"
 *   }
 * )
 */
class Pricing extends ConfigEntityBase implements PricingInterface {
  protected $id;
  protected $label;
  public string $shape = '';
  public string $board_grade = '';
  public int $quantity_from = 1;
  public int $quantity_to = 999999;
  public float $price_per_box = 0.0;
  public float $print_single_cost = 0.0;
  public float $print_multi_cost = 0.0;
  public float $coating_cost = 0.0;
}
