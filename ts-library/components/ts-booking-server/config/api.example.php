<?php
/* ts-booking-server – vstupní bod API ve webrootu projektu (např. /api/rezervace.php). Upravte jen cesty. */
require __DIR__ . '/../vendor/ts/ts-booking-server/core/bootstrap.php';
ts_booking_api(require __DIR__ . '/../../private/booking-server.php')->serve();
