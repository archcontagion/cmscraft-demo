<?php
/**
 * Site URL Rules
 *
 * You can define custom site URL rules here, which Craft will check in addition
 * to routes defined in Settings → Routes.
 *
 * Read about Craft’s routing behavior (and this file’s structure), here:
 * @link https://craftcms.com/docs/5.x/system/routing.html
 */

return [
    'GET api/v1/entry' => 'api/rest/entry',
    'GET api/v1/globals' => 'api/rest/globals',
    'GET api/v1/types' => 'api/rest/types',
];
