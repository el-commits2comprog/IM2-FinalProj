<?php

declare(strict_types=1);

const MAX_PHOTO_BYTES = 2 * 1024 * 1024; 
const PHOTO_TYPES = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

function normalize_custom_location(string $value): ?string
{
    $value = trim(preg_replace('/\s+/', ' ', $value));
    return $value === '' ? null : $value;
}

function validate_custom_location(array $locations, array $input): ?string
{
    $otherId = null;
    foreach ($locations as $l) {
        if (strcasecmp(trim((string)$l['location_name']), 'Other') === 0) {
            $otherId = (int)$l['location_id'];
            break;
        }
    }

    if ($otherId !== null
        && (int)($input['location_id'] ?? 0) === $otherId
        && trim((string)($input['custom_location'] ?? '')) === ''
    ) {
        return 'Please specify the location when you choose "Other".';
    }

    return null;
}

function validate_item_photo(?array $file): array
{
    $result = ['error' => null, 'ext' => null, 'tmp' => null];

    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $result; 
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] = 'The photo could not be uploaded. Try a smaller file.';
    } elseif ($file['size'] > MAX_PHOTO_BYTES) {
        $result['error'] = 'The photo must be 2 MB or smaller.';
    } else {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!isset(PHOTO_TYPES[$mime])) {
            $result['error'] = 'Only JPG, PNG, or WebP photos are allowed.';
        } else {
            $result['ext'] = PHOTO_TYPES[$mime];
            $result['tmp'] = $file['tmp_name'];
        }
    }
    return $result;
}

function validate_item_report(array $old, array $categories, array $locations): array
{
    $errors = [];

    $categoryIds = array_map('intval', array_column($categories, 'category_id'));
    $locationIds = array_map('intval', array_column($locations, 'location_id'));

    if (!in_array($old['item_type'], ['Lost', 'Found'], true)) {
        $errors['item_type'] = 'Choose Lost or Found.';
    }
    if ($old['item_name'] === '' || mb_strlen($old['item_name']) > 100) {
        $errors['item_name'] = 'Item name is required (max 100 characters).';
    }
    if (!in_array((int)$old['category_id'], $categoryIds, true)) {
        $errors['category_id'] = 'Choose a category.';
    }
    if (!in_array((int)$old['location_id'], $locationIds, true)) {
        $errors['location_id'] = 'Choose a location.';
    }

    $custom = preg_replace('/\s+/', ' ', $old['custom_location']);
    if (mb_strlen($custom) > 100) {
        $errors['custom_location'] = 'Specific location must be 100 characters or fewer.';
    } elseif (($msg = validate_custom_location($locations, $old)) !== null) {
        $errors['custom_location'] = $msg;
    }

    $date = DateTime::createFromFormat('Y-m-d', $old['event_date']);
    if (!$date || $date->format('Y-m-d') !== $old['event_date']) {
        $errors['event_date'] = 'Enter a valid date.';
    } elseif ($old['event_date'] > date('Y-m-d')) {
        $errors['event_date'] = 'The date cannot be in the future.';
    }

    if ($old['description'] === '' || mb_strlen($old['description']) > 1000) {
        $errors['description'] = 'Description is required (max 1000 characters).';
    }

    return $errors;
}