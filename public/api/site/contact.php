<?php
/**
 * Pentagon UI — public contact details from site_settings.
 * GET /api/site/contact.php
 */
require_once __DIR__ . '/../_lib/bootstrap.php';
require_once __DIR__ . '/../../../app/v1/helpers/site_contact.php';

require_method('GET');
$contact = site_contact_settings();

json_response([
    'success' => true,
    'data' => [
        'site_name' => $contact['site_name'] ?? 'Dazu Hub Institute',
        'phone' => $contact['contact_phone'] ?? '',
        'phone_tel' => $contact['contact_phone_tel'] ?? '',
        'email' => $contact['contact_email'] ?? '',
        'address' => $contact['contact_address'] ?? '',
        'hours' => $contact['contact_hours'] ?? '',
        'map_embed' => $contact['contact_map_embed'] ?? '',
        'map_link' => $contact['contact_map_link'] ?? '',
        'whatsapp' => preg_replace('/\D+/', '', $contact['contact_phone_tel'] ?? '') ?: '254750116600',
    ],
]);
