<?php
namespace fostercommerce\deliverydates\models;

use fostercommerce\deliverydates\DeliveryDates;

use Craft;
use craft\base\Model;
use craft\helpers\DateTimeHelper;

class Settings extends Model
{
    public $active = true;

    public $fulfillmentTime = 8;

    public $cutoffTime = 14;

    public $courierCutoffTime = 14;

    public $daysOfWeek = [
        'sunday' => [
            'active' => false,
            'min' => 9,
            'max' => 17,
        ],
        'monday' => [
            'active' => true,
            'min' => 9,
            'max' => 17,
        ],
        'tuesday' => [
            'active' => true,
            'min' => 9,
            'max' => 17,
        ],
        'wednesday' => [
            'active' => true,
            'min' => 9,
            'max' => 17,
        ],
        'thursday' => [
            'active' => true,
            'min' => 9,
            'max' => 17,
        ],
        'friday' => [
            'active' => true,
            'min' => 9,
            'max' => 17,
        ],
        'saturday' => [
            'active' => true,
            'min' => 9,
            'max' => 17,
        ],
    ];

    /**
     * Example:
     *
     * ```json
     * {
     *   date: {
     *     date: '2019-05-01',
     *     timezone: 'America/New_York',
     *   },
     *   name: 'Mayday',
     * }
     * ```
     */
    public $exceptions = [];

    public function rules()
    {
        // Guess we can do this here?
        // Make sure the date format is standardized so we can do comparisons when
        // checking for exception days.
        if ($this->exceptions) {
            $formatter = Craft::$app->formatter;
            $format = $formatter->dateTimeFormats['short']['date'];

            foreach ($this->exceptions as $key => $exception) {
                $this->exceptions[$key]['date']['date'] = $formatter->asDate(
                    $exception['date']['date'],
                    'yyyy-MM-d'
                );
            }
        }


        return [];
    }
}
