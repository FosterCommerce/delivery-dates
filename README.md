# Delivery Dates plugin for Craft CMS 3.1.x and Craft Commerce 2.x

Delivery Dates

## Installation

To install the plugin, follow these instructions.

1. Open your terminal and go to your Craft project:

        cd /path/to/project

2. Then tell Composer to load the plugin:

        composer require FosterCommerce/delivery-dates

3. In the Control Panel, go to Settings → Plugins and click the “Install” button for Delivery Dates.

## Template Usage

```twig
{% set deliveryBy = craft.deliveryDates.deliveryBy %}
Order by {{deliveryBy.orderByDate|date('m/d/Y H:i:s')}}{# 4/19/2019 14:00:00 #}
Delivery By {{deliveryBy.deliveryDate|date('m/d/Y')}} {# 4/19/2019 #}
```

```twig
{% set deliveryBy = craft.deliveryDates.deliveryBy(order.dateOrdered) %}
Order by {{deliveryBy.orderByDate|date('m/d/Y H:i:s')}}{# 4/29/2019 09:00:00 #}
Delivery By {{deliveryBy.deliveryDate|date('m/d/Y')}} {# 5/2/2019 #}
```

Brought to you by [Foster Commerce](https://fostercommerce.com)
