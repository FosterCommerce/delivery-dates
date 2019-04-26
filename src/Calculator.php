<?php

namespace fostercommerce\deliverydates;

use fostercommerce\deliverydates\models\Settings;

class Calculator
{
    private $settings;
    private $orderDate;
    private $orderTime;

    private $orderByDate;
    private $orderReadyDate;
    private $courierHandOffDate;
    private $deliveryDate;

    public function __get($property)
    {
        return $this->$property;
    }

    public function __construct(Settings $settings, \DateTime $time)
    {
        $this->settings = $settings;

        $this->orderDate = $time;
        $this->orderTime = $time->format('G') + 1;
    }

    /**
     * Calculate the estimated delivery date.
     */
    public function calculate()
    {
        $this->calculateOrderReady();
        $this->calculateCourierHandOff();
        $this->calculateCourierDeliveryDate();
    }

    /**
     * Search for exceptions matching the current day.
     */
    private function findException($formattedDate) {
        if ($this->settings->exceptions) {
            foreach ($this->settings->exceptions as $exception) {
                $exceptionDate = new \DateTime($exception['date']['date'], new \DateTimeZone($exception['date']['timezone']));
                $formattedExceptionDate = $exceptionDate->format('Y-m-d');
                if ($formattedDate === $formattedExceptionDate) {
                    return $exception;
                }
            }
        }

        return false;
    }

    /**
     * Return configuration for the current day, incrementing the days until a day marked as
     * `active` is found.
     */
    private function getDaySettings()
    {
        $day = strtolower($this->orderDate->format('l'));
        $formattedDate = $this->orderDate->format('Y-m-d');
        $daySettings = $this->settings->daysOfWeek[$day];

        // Exceptions are automatically inactive days, so if one is found, we mark the day as
        // inactive and move on.
        if ($exception = $this->findException($formattedDate)) {
            $daySettings['active'] = false;
        }

        if (!$daySettings['active']) {
            // Recurse until we get an active day
            $this->orderDate->modify('+1 day');
            // If we've skipped a day, set the time to midnight so we don't lose a day in our calculation.
            // For example, if ordered on Saturday at 20h00, this would set the order time to midnight
            // on Monday morning.
            $this->orderDate->setTime(0, 0);
            $this->orderTime = 0;

            return $this->getDaySettings();
        }

        return $daySettings;
    }

    /**
     * Calculate when the order is ready to be handed off to the courier.
     */
    private function calculateOrderReady()
    {
        $startDayFound = false;
        $settings = null;

        // Search for the soonest day and time we can accept an order. Orders can be placed outside of
        // working hours, therefore we need to ensure that we adjust the time and day so that the order
        // can be fulfilled withing the configured business hours.
        while (!$startDayFound) {
            $settings = $this->getDaySettings();
            if ($this->orderTime === false || $this->orderTime < $settings['min']) {
                $this->orderTime = $settings['min'];
            }

            if ($this->orderTime >= $settings['min'] && $this->orderTime <= $settings['max']) {
                $startDayFound = true;
            } else {
                $this->orderDate->modify('+1 day');
                $this->orderTime = false; // Original order time will no longer apply.
            }
        }

        $this->orderByDate = clone($this->orderDate);
        $this->orderByDate->setTime($this->orderTime, 0);

        $fulfillmentDay = null;

        // Time left until the end of business hours
        $dayTimeRemainder = $settings['max'] - $this->orderTime;

        // Time left until an order is guaranteed to be ready for hand-off
        $fulfillmentTimeRemainder = $this->settings->fulfillmentTime - $dayTimeRemainder;

        if ($fulfillmentTimeRemainder > 0) {
            $this->orderDate->modify('+1 day');
        } else {
            $fulfillmentDay = clone($this->orderDate);
        }

        // Start searching for the first realistic fulfillment day and time. For each active day,
        // we decrease the amount of hours remaining until an order is ready for hand off.
        while (!$fulfillmentDay) {
            $settings = $this->getDaySettings();
            $dayHours = $settings['max'] - $settings['min'];
            $fulfillmentTimeRemainder = $fulfillmentTimeRemainder - $dayHours;

            if ($fulfillmentTimeRemainder > 0) {
                $this->orderDate->modify('+1 day');
            } else {
                $fulfillmentDay = clone($this->orderDate);
            }
        }

        $this->orderReadyDate = $fulfillmentDay;

        // Set the time for possible hand-off.
        $this->orderReadyDate->setTime($settings['max'] + $fulfillmentTimeRemainder, 0);
    }


    /**
     * Calculate when the order will be handed off to courier.
     */
    private function calculateCourierHandOff()
    {
        // Use the order ready date/time to determine when actual hand-off will happen.
        $this->orderDate = clone($this->orderReadyDate);
        $this->orderTime = $this->orderDate->format('G');
        $startDayFound = false;
        $settings = null;
        $courierSetings = null;

        // It is possible that orders can be ready for hand-off outside of the courier's
        // cut-off time. If that is true, we search for the next available day for courier
        // hand-off.
        while (!$startDayFound) {
            $settings = $this->getDaySettings();
            if ($this->orderTime === false || $this->orderTime < $settings['min']) {
                $this->orderTime = $settings['min'];
            }

            if (
                $this->orderTime >= $settings['min'] &&
                $this->orderTime <= $settings['max']
            ) {
                $startDayFound = true;
            } else {
                $this->orderDate->modify('+1 day');
                $this->orderTime = false; // Original order time will no longer apply.
            }
        }

        $this->courierHandOffDate = clone($this->orderDate);
        $this->courierHandOffDate->setTime($this->settings->courierCutoffTime, 0);
    }

    /**
     * Determine the estimated delivery date to the customer
     */
    private function calculateCourierDeliveryDate()
    {
        $this->orderDate = clone($this->courierHandOffDate);
        $this->orderTime = $this->orderDate->format('G');

        // 2-day delivery
        $hoursToAdd = 48;
        $interval = 24;

        // Add a day every time we find a day configured as active.
        while($hoursToAdd > 0) {
            $this->getDaySettings(); // This will increment the days for us.
            $this->orderDate->modify("+{$interval} hours");
            $hoursToAdd -= $interval;
        }

        $startDayFound = false;
        $settings = null;

        // If for some reason the delivery time falls outside of working hours, we can search for
        // the next active day when the courier would complete the delivery.
        while (!$startDayFound) {
            $settings = $this->getDaySettings();
            if ($this->orderTime === false || $this->orderTime < $settings['min']) {
                $this->orderTime = $settings['min'];
            }

            if ($this->orderTime >= $settings['min'] && $this->orderTime <= $settings['max']) {
                $startDayFound = true;
            } else {
                $this->orderDate->modify('+1 day');
                $this->orderTime = false;
            }
        }

        $this->deliveryDate = clone($this->orderDate);
    }
}
