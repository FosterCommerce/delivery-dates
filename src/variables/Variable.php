<?php

namespace fostercommerce\deliverydates\variables;

use fostercommerce\deliverydates\DeliveryDates;

use Craft;
use fostercommerce\deliverydates\Calculator;

class Variable
{
    /**
     * @param \DateTime $time the time to base delivery calculations on. Defaults to `"now"` in the systems configured timezone.
     * @return \DateTime The estimated delivery date and time
     */
    public function deliveryBy($time = null)
    {
        // Timezone can be set in general.php or in Control Panel.
        // TODO: Is there another way to get the configured timezone?
        $configTz = Craft::$app->getTimeZone();
        if (!$time) {
            $time = new \DateTime('now', new \DateTimeZone($configTz));
        }

        // Because we're doing comparisons against dates in a specific format, ex: 2019-04-16, we
        // need to ensure that the date we're comparing against is in the same timezone so that
        // there are no off-by-a-day issues.
        $time->setTimezone(new \DateTimeZone($configTz));

        $calculator = new Calculator(DeliveryDates::getInstance()->settings, $time);
        $calculator->calculate();
        return $calculator->deliveryDate;
    }
}

