# Ambey Box Calculator

Drupal 11 custom module for a corrugated box quotation calculator.

## Features

- 10-step calculator form at `/box-calculator`
- Size, board grade, colour, shape, print, quality, coating, shipping, quantity, and customer inputs
- Dimension-aware server-side box price calculation
- Quantity slab pricing table with print, coating, and shipping add-ons
- 12% GST calculation
- Indian rupee formatting such as `₹37,98,000.00`
- Business rule validation
- Quote storage with UUID-based confirmation and PDF download URLs
- PDF quotation generation via `dompdf/dompdf`
- Email quotation queue worker
- CRM webhook integration
- Admin quote dashboard, filters, assignment, notes, audit, and export
- REST API endpoints
- Role-based permissions

## Install

Run commands from the Drupal project root.

```bash
ddev composer install
ddev drush pm:enable --yes ambey_box_calculator
ddev drush cache:rebuild
```

Open `/box-calculator`.

## Local Development

```bash
ddev start
ddev drush status
ddev drush cache:rebuild
ddev drush update:db --yes
```

## Pricing Logic

The pricing service is `Drupal\ambey_box_calculator\PricingCalculator`.

Per-box pricing is calculated in this order:

1. Match a pricing slab by `shape`, `board_grade`, and `quantity`.
2. Calculate the entered box surface area:

```text
surface area = 2 * ((length * width) + (length * height) + (width * height))
```

3. Compare that area to the seeded reference box `10 x 8 x 5 in`:

```text
dimension multiplier = entered surface area / reference surface area
```

4. Apply the board grade material factor:

```text
3 Ply = 1.00
5 Ply = 1.75
7 Ply = 2.50
```

5. Scale the slab `price_per_box`:

```text
size_and_board_price = price_per_box * dimension_multiplier * board_grade_factor
```

6. Add print cost:

```text
single colour = print_single_cost
multi colour = print_multi_cost
none = 0
```

7. Add coating cost only when `color` is `white` and `coating` is selected.
8. Add the selected shipping zone cost per box.
9. Apply 12% GST.
10. Calculate totals:

```text
total_without_gst = base_price * quantity
total_with_gst = final_price * quantity
```

When dimensions are not supplied, the multiplier is `1.0` for backward compatibility.

## API Examples

Calculate a quote:

```bash
curl -X POST https://example.com/api/box/calculate \
  -H "Content-Type: application/json" \
  -d '{"shape":"regular","board_grade":"3ply","quantity":500,"print":"none","coating":"","color":"brown","shipping":"local","length":10,"width":8,"height":5}'
```

Create and store a quote:

```bash
curl -X POST https://example.com/api/box/quote \
  -H "Content-Type: application/json" \
  -d '{"name":"Amit Sharma","email":"amit@example.com","phone":"9999999999","company":"Example Pvt Ltd","shape":"regular","board_grade":"3ply","quantity":500,"print":"single","coating":"","color":"brown","shipping":"local","length":10,"width":8,"height":5}'
```

## Admin

- Quotes dashboard: `/admin/ambey/quotes`
- Pricing slabs: `/admin/structure/ambey-pricing`
- Shapes: `/admin/structure/ambey-shapes`
- Shipping zones: `/admin/structure/ambey-shipping-zones`
- CRM settings: `/admin/config/services/ambey-crm`

## Verification

```bash
find web/modules/custom/ambey_box_calculator/ambey_box_calculator/src -name "*.php" -print0 | xargs -0 -n1 php -l
ddev drush php:eval '$s=\Drupal::service("ambey_box_calculator.pricing"); print_r($s->calculateQuotePrices(["shape"=>"regular","board_grade"=>"3ply","quantity"=>500,"print"=>"none","coating"=>"","color"=>"brown","shipping"=>"local","length"=>10,"width"=>8,"height"=>5]));'
ddev drush cache:rebuild
```
