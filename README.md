# Ambey Box Calculator

Drupal 11 custom module for a corrugated box quotation calculator.

## Features

- 10-step calculator form
- Shape, board grade, colour, print, quality, coating, shipping and quantity inputs
- Server-side pricing calculation
- Quantity slab pricing table
- 12% GST calculation
- Business rule validation
- Quote storage
- PDF quotation generation via `dompdf/dompdf`
- Email quotation queue worker
- CRM webhook integration
- Admin quote dashboard
- REST API endpoints
- Role-based permissions

## Install

```bash
composer require dompdf/dompdf
cp -R ambey_box_calculator web/modules/custom/
vendor/bin/drush en ambey_box_calculator -y
vendor/bin/drush cr
```

Open `/box-calculator`.

## Dry-run commands

```bash
find web/modules/custom/ambey_box_calculator -name "*.php" -print0 | xargs -0 -n1 php -l
vendor/bin/drush cr
vendor/bin/drush sqlq "SHOW TABLES LIKE 'ambey_box_%';"
vendor/bin/drush route | grep ambey
vendor/bin/drush php:eval '$s=\Drupal::service("ambey_box_calculator.pricing"); print_r($s->applyGST(11.72));'
```

## API example

```bash
curl -X POST https://example.com/api/box/calculate \
  -H "Content-Type: application/json" \
  -d '{"shape":"regular","board_grade":"3ply","quantity":500,"print":"none","coating":"","color":"brown","shipping":"local","length":10,"width":10,"height":5}'
```
