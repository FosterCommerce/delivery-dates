<?php

namespace fostercommerce\deliverydates;

use fostercommerce\deliverydates\variables\Variable;
use fostercommerce\deliverydates\models\Settings;

use Craft;
use craft\base\Plugin;
use craft\services\Plugins;
use craft\events\PluginEvent;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;
use craft\events\RegisterUrlRulesEvent;

use yii\base\Event;

class DeliveryDates extends Plugin
{
    public static $plugin;

    public $schemaVersion = '1.0.0';

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        // Register our variables
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('deliveryDates', Variable::class);
            }
        );
    }

    protected function createSettingsModel()
    {
        return new Settings();
    }

    protected function settingsHtml(): string
    {
        return Craft::$app->view->renderTemplate(
            'delivery-dates/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
