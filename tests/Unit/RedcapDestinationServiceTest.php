<?php

use App\Services\RedcapDestinationService;

it('rejects unsafe datatel IDs before building REDCap filter logic', function () {
    expect(app(RedcapDestinationService::class)->findStudentByDatatelId("1' OR '1'='1"))
        ->toBeNull();
});
