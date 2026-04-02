<?php

namespace app\modules\api;

use Craft;
use yii\base\Module as BaseModule;

/**
 * Project API module (REST-style JSON endpoints).
 */
class Module extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@appmodulesapi', __DIR__);

        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'app\\modules\\api\\console\\controllers';
        } else {
            $this->controllerNamespace = 'app\\modules\\api\\controllers';
        }

        parent::init();
    }
}
