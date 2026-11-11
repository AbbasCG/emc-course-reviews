<?php

/*
|--------------------------------------------------------------------------
| Scheduled cleanup of temporary data (docs/CACHE-SESSIONS-MAINTENANCE.md)
|--------------------------------------------------------------------------
|
| cache:prune-expired and sessions:prune-expired can always be run by hand. They are scheduled (daily, off-peak) automatically
| ONLY when the matching driver is "database", the case where expired rows were measured piling up (PR-4 lab: 52,690 of 53,075 cache
| rows expired, 23,090 session rows) and where Laravel's own cleanup is one unbatched delete inside a visitor's request.
|
| With the file drivers (the current production setup) nothing is scheduled by default: sessions are cleaned by Laravel's own collector and the
| few cache files that are never read again are harmless. Set MAINTENANCE_SCHEDULE_CACHE / MAINTENANCE_SCHEDULE_SESSIONS to true or false
| to force the schedule on or off; leave them unset for the automatic behaviour.
*/
return [
    'schedule' => [
        // an empty value ("MAINTENANCE_SCHEDULE_CACHE=") means "automatic", like an unset one
        'cache'    => env('MAINTENANCE_SCHEDULE_CACHE') === '' ? null : env('MAINTENANCE_SCHEDULE_CACHE'),
        'sessions' => env('MAINTENANCE_SCHEDULE_SESSIONS') === '' ? null : env('MAINTENANCE_SCHEDULE_SESSIONS'),
    ],
];
