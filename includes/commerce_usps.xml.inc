<?php

/**
 * @file
 * Handles rate request/response related stuff for the Commerce USPS module.
 */

/**
 * Builds a domestics USPS rate request.
 *
 * @param object $order
 *   The commerce order object.
 * @param object $shipping_address
 *   The commerce_customer_address array of the shipping profile.
 *
 * @return array
 *   An array of shipping rates.
 */
function commerce_usps_rate_v4_request($order, $shipping_address) {
  $rates = array();
  $usps_services = commerce_usps_service_list('domestic');

  $weight = commerce_usps_get_order_weight($order);

  $request = new SimpleXMLElement('<RateV4Request/>');
  $request->addAttribute('USERID', variable_get('commerce_usps_user', ''));
  $request->addChild('Revision', 2);

  // @TODO: Support multiple packages based on physical attributes.
  // Add a package to the request for each enabled service.
  $i = 1;

  foreach (variable_get('commerce_usps_services', array()) as $machine_name => $service) {
    if (!empty($service)) {
      $package = $request->addChild('Package');
      $package->addAttribute('ID', $i);
      $package->addChild('Service', $usps_services[$machine_name]['request_name']);
      $package->addChild('FirstClassMailType', 'PARCEL');
      $package->addChild('ZipOrigination', substr(variable_get('commerce_usps_postal_code', ''), 0, 5));
      $package->addChild('ZipDestination', substr($shipping_address['postal_code'], 0, 5));
      $package->addChild('Pounds', $weight['pounds']);
      $package->addChild('Ounces', $weight['ounces']);
      $package->addChild('Container', 'VARIABLE');
      $package->addChild('Size', 'REGULAR');
      $package->addChild('Machinable', 'TRUE');
      $i++;
    }
  }

  drupal_alter('commerce_usps_rate_v4_request', $request);

  // Submit the rate request to USPS.
  $response = commerce_usps_api_request('API=RateV4&XML=' . $request->asXML());

  if (!empty($response->Package)) {
    // Loop through each of the package results to build the rate array.
    foreach ($response->Package as $package) {
      if (empty($package->Error)) {
        // Load the shipping service's class id from the package response.
        $id = (string) $package->Postage->attributes()->{'CLASSID'};

        // Look up the shipping service by it's class id.
        $usps_service = commerce_usps_service_by_id($id, 'domestic');

        // Make sure that the package service is registered.
        if (!empty($usps_service['machine_name'])) {
          $rates[$usps_service['machine_name']] = array(
            'amount' => commerce_currency_decimal_to_amount((string) $package->Postage->Rate, commerce_default_currency()),
            'currency_code' => commerce_default_currency(),
            'data' => array(),
          );
        }
      }
    }
  }
  return $rates;
}

/**
 * Builds an international USPS rate request.
 *
 * @param object $order
 *   The commerce order object.
 * @param array $shipping_address
 *   The commerce_customer_address array of the shipping profile.
 *
 * @return array
 *   An array of shipping rates.
 */
function commerce_usps_intl_rate_v2_request($order, $shipping_address) {
  $rates = array();

  $weight = commerce_usps_get_order_weight($order);

  $request = new SimpleXMLElement('<IntlRateV2Request/>');
  $request->addAttribute('USERID', variable_get('commerce_usps_user', ''));
  $request->addChild('Revision', 2);
  $shipment_value = commerce_usps_get_shipment_value($order);

  // @TODO: Support multiple packages based on physical attributes.
  $package = $request->addChild('Package');
  $package->addAttribute('ID', 1);
  $package->addChild('Pounds', $weight['pounds']);
  $package->addChild('Ounces', $weight['ounces']);
  $package->addChild('Machinable', 'True');
  $package->addChild('MailType', 'Package');
  $package->addChild('ValueOfContents', commerce_currency_amount_to_decimal($shipment_value, commerce_default_currency()));
  $package->addChild('Country', commerce_usps_country_get_predefined_list($shipping_address['country']));
  $package->addChild('Container', 'RECTANGULAR');
  $package->addChild('Size', 'REGULAR');
  $package->addChild('Width', '');
  $package->addChild('Length', '');
  $package->addChild('Height', '');
  $package->addChild('Girth', '');
  $package->addChild('OriginZip', substr(variable_get('commerce_usps_postal_code', ''), 0, 5));
  $package->addChild('CommercialFlag', 'N');

  drupal_alter('commerce_usps_intl_rate_v2_request', $request);

  // Submit the rate request to USPS.
  $response = commerce_usps_api_request('API=IntlRateV2&XML=' . $request->asXML());

  if (!empty($response->Package->Service)) {
    foreach ($response->Package->Service as $service) {
      $id = (string) $service->attributes()->{'ID'};

      // Look up the shipping service by it's id.
      $usps_service = commerce_usps_service_by_id($id, 'international');

      // Make sure that the package service is registered.
      if (!empty($usps_service['machine_name'])) {
        $rates[$usps_service['machine_name']] = array(
          'amount' => commerce_currency_decimal_to_amount((string) $service->Postage, commerce_default_currency()),
          'currency_code' => commerce_default_currency(),
          'data' => array(),
        );
      }
    }
  }

  return $rates;
}

/**
 * Submits an API request to USPS.
 *
 * @param string $request
 *   A request string.
 * @param string $message
 *   Optional log message.
 *
 * @return string
 *   XML string response from USPS
 */
function commerce_usps_api_request($request, $message = '') {

  commerce_usps_log('Submitting API request to USPS. @message:<pre>@request</pre>', array('@message' => $message, '@request' => $request));

  $request_url = variable_get('commerce_usps_connection_address', 'http://Production.ShippingAPIs.com/ShippingAPI.dll');

  // Send the request.
  $response = drupal_http_request($request_url, array('method' => 'POST', 'data' => $request));

  // If we received a response.
  if (!empty($response->data)) {
    commerce_usps_log('Response code:@code<br />Response:<pre>@response</pre>', array('@code' => $response->code, '@response' => $response->data));
    return new SimpleXMLElement($response->data);
  }
  else {
    $settings_url = l(t('settings'), 'admin/commerce/config/shipping/methods/usps/edit');
    commerce_usps_log('We did not receive a response from USPS. Make sure you have the correct server url in your !settings.', array('!settings' => $settings_url), WATCHDOG_ERROR);
  }

  return FALSE;
}
