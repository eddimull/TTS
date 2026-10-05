<?php

return [
    /*
    | Push every database (bell) notification to the user's devices. Kept off
    | until the mobile build that can route `type: notification` pushes is
    | live in the stores — older builds would receive pushes they cannot open.
    */
    'notifications_feed' => (bool) env('PUSH_NOTIFICATIONS_FEED', false),
];
