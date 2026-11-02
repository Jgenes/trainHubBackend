<?php

/*
|--------------------------------------------------------------------------
| Public catalogue recovery
|--------------------------------------------------------------------------
|
| When no course matches Course::published(), the catalogue will return the
| existing non-deleted courses instead of an empty array. This recovers rows
| created with legacy status values or a mistakenly withdrawn status. Set the
| value to false to enforce strict moderation (used by the test suite).
|
*/

return [
    'include_hidden_when_empty' => filter_var(
        env('CATALOGUE_INCLUDE_HIDDEN_WHEN_EMPTY', true),
        FILTER_VALIDATE_BOOLEAN
    ),
];
