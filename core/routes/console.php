<?php

declare(strict_types=1);

/*
 * The schedule is defined once, in bootstrap/app.php via withSchedule().
 * Defining it here as well registered every command twice, so the sync
 * sweeper and the channel poller both ran twice per minute.
 */
