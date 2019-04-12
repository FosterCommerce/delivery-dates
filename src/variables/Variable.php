<?php

namespace fostercommerce\deliverydates\variables;

use fostercommerce\deliverydates\DeliveryDates;

use Craft;
use fostercommerce\deliverydates\Calculator;

class Variable
{
    public function deliveryBy($time = null)
    {
        // Timezone can be set in general.php or in Control Panel.
        $configTz = Craft::$app->getTimeZone();
        if (!$time) {
            $time = new \DateTime('2019-04-15T14:00:00', new \DateTimeZone($configTz));
        }

        $time->setTimezone(new \DateTimeZone($configTz));

        $calculator = new Calculator(DeliveryDates::getInstance()->settings, $time);
        $calculator->calculate();
        return $calculator->deliveryDate;
    }
}

