<?php

declare(strict_types=1);

function validate_custom_location(array $locations, array $old): ?string
{
    $otherId = null;
    foreach ($locations as $l) {
        if ($l['location_name'] === 'Other') {
            $otherId = (int)$l['location_id'];
            break;
        }
    }

    if ($otherId !== null
        && (int)($old['location_id'] ?? 0) === $otherId
        && trim((string)($old['custom_location'] ?? '')) === ''
    ) {
        return 'Please specify the location when you choose "Other".';
    }

    return null;
}