<?php

/**
 * ROOD.Sendbirthdaysixteenmail API
 *
 * Sends a mail to all certain contacts on their x-th birthday
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_rood_Sendbirthdaysixteenmail($params) {
    if (!CRM_Core_Permission::check("access CiviCRM")) {
        throw new API_Exception("Insufficient permissions");
    }
    $contactIds = civicrm_api3('Contact', 'Sendbirthdaymail', [
        'age' => 16,
        'membership_id' => 3, // 3 is ROOD membership
        'dont_send_mail' => true,
    ])['values'];
    if (empty($contactIds)) {
        return civicrm_api3_create_success('No contact ids',
                                           $params, 'ROOD', 'Sendbirthdaysixteenmail');
    }
    $message = "Beste ROOD,\r\n\r\nVandaag zijn de volgende leden 16 geworden:\r\n\r\n";
    foreach($contactIds as $contactId) {
        $message .= "Contactnummer: $contactId\r\n";
    }
    mail('rood@sp.nl', 'ROOD-leden 16 geworden', $message);
    return civicrm_api3_create_success('contact ids found',
                                       $params, 'ROOD', 'Sendbirthdaysixteenmail');
}
