<?php

namespace fostercommerce\deliverydates;

class Calculator
{
    private $settings;
    private $orderDate;
    private $orderTime;

    private $orderReadyDate;
    private $courierHandOffDate;
    private $deliveryDate;

    public function __get($property)
    {
        return $this->$property;
    }

    public function __construct($settings, \DateTime $time)
    {
        $this->settings = $settings;

        $this->orderDate = $time;
        $this->orderTime = $time->format('G');
    }

    public function calculate()
    {
        $this->calculateOrderReady();
        $this->calculateCourierHandOff();
        $this->calculateCourierDeliveryDate();
    }

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

    private function getDaySettings()
    {
        $day = strtolower($this->orderDate->format('l'));
        $formattedDate = $this->orderDate->format('Y-m-d');
        $daySettings = $this->settings->daysOfWeek[$day];

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

        $fulfillmentDay = null;

        $dayTimeRemainder = $settings['max'] - $this->orderTime;
        $fulfillmentTimeRemainder = $this->settings->fulfillmentTime - $dayTimeRemainder;
        if ($fulfillmentTimeRemainder > 0) {
            $this->orderDate->modify('+1 day');
        } else {
            $fulfillmentDay = $this->orderDate;
        }

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
        $this->orderReadyDate->setTime($settings['max'] + $fulfillmentTimeRemainder, 0);
    }


    /**
     * Calculate when the order will be handed off to courier
     */
    private function calculateCourierHandOff()
    {
        $this->orderDate = clone($this->orderReadyDate);
        $this->orderTime = $this->orderDate->format('G');
        $startDayFound = false;
        $settings = null;
        $courierSetings = null;

        while (!$startDayFound) {
            $settings = $this->getDaySettings();
            if ($this->orderTime === false || $this->orderTime < $settings['min']) {
                $this->orderTime = $settings['min'];
            }

            if (
                $this->orderTime >= $settings['min'] &&
                $this->orderTime <= $settings['max'] &&
                $this->orderTime <= $this->settings->cutoffTime
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

    private function calculateCourierDeliveryDate()
    {
        // 2-day delivery
        $this->orderDate = clone($this->courierHandOffDate);
        $this->orderTime = $this->orderDate->format('G');

        $hoursToAdd = 48;
        $interval = 24;

        while($hoursToAdd > 0) {
            $settings = $this->getDaySettings();
            $this->orderDate->modify("+{$interval} hours");
            $hoursToAdd -= $interval;
        }

        $startDayFound = false;
        $settings = null;

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

        $this->deliveryDate = clone($this->orderDate);
    }
}
