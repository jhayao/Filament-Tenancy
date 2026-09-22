<?php

use Liern\FilamentTenancy\Teams\Invitation;

require __DIR__.'/bootstrap.php';
echo Invitation::where('email', 'invited@example.test')->latest('id')->firstOrFail()->url();
