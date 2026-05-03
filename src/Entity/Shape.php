<?php

namespace Drupal\ambey_box_calculator\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the box shape config entity.
 *
 * @ConfigEntityType(
 *   id = "ambey_shape",
 *   label = @Translation("Box Shape"),
 *   handlers = {
 *     "list_builder" = "Drupal\ambey_box_calculator\ShapeListBuilder",
 *     "form" = {
 *       "add" = "Drupal\ambey_box_calculator\Form\ShapeForm",
 *       "edit" = "Drupal\ambey_box_calculator\Form\ShapeForm",
 *       "delete" = "Drupal\ambey_box_calculator\Form\ShapeDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   config_prefix = "shape",
 *   admin_permission = "manage ambey shapes",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "image",
 *     "enabled"
 *   },
 *   links = {
 *     "collection" = "/admin/config/ambey/shapes",
 *     "add-form" = "/admin/config/ambey/shapes/add",
 *     "edit-form" = "/admin/config/ambey/shapes/{ambey_shape}",
 *     "delete-form" = "/admin/config/ambey/shapes/{ambey_shape}/delete"
 *   }
 * )
 */
class Shape extends ConfigEntityBase implements ShapeInterface {
  protected $id;
  protected $label;
  public string $description = '';
  public string $image = '';
  public bool $enabled = TRUE;
}
