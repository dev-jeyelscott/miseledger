<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notion Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for syncing problem reports to the Notion ORC Bug Fix
    | database. When disabled, Notion sync failures never affect local
    | report submission success.
    |
    */

    'enabled' => (bool) config('services.notion.enabled', false),

    'api_key' => config('services.notion.api_key'),

    'api_version' => config('services.notion.api_version', '2022-06-28'),

    'data_source_id' => config('services.notion.data_source_id'),

];
