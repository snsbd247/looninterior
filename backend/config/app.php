<?php

/*
| Merged with Laravel's default app config. Only overrides what this project needs.
| Timezone: business dates/times (follow-ups, due dates, reports) are Bangladesh time.
*/
return [
    'timezone' => env('APP_TIMEZONE', 'Asia/Dhaka'),
];
