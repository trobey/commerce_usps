<?php

namespace Drupal\commerce_usps\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\Component\Utility\Unicode;
use Drupal\physical\Weight;
use Drupal\physical\WeightUnit;

/**
 * Provides the USPS shipping method.
 *
 * @CommerceShippingMethod(
 *   id = "usps",
 *   label = @Translation("USPS"),
 * )
 */
class USPS extends ShippingMethodBase {

  /**
   * Constructs a new USPS object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $package_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager);

    $this->services['default'] = new ShippingService('default', $plugin_id);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'commerce_usps_postal_code' => '',
      'commerce_usps_services' => [],
      'commerce_usps_services_int' => [],
      'commerce_usps_connection_address' => 'http://production.shippingapis.com/ShippingAPI.dll',
      'commerce_usps_user' => '',
      'commerce_usps_show_logo' => NULL,
      'commerce_usps_log' => 'no',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['origin'] = [
      '#type' => 'fieldset',
      '#title' => t('Ship from location'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#weight' => 10,
    ];
    $form['origin']['commerce_usps_postal_code'] = [
      '#type' => 'textfield',
      '#title' => t('Postal Code'),
      '#default_value' => $this->configuration['commerce_usps_postal_code'],
    ];

    $form['settings'] = [
      '#type' => 'fieldset',
      '#title' => t('Shipment settings'),
      '#collapsible' => TRUE,
      '#weight' => 20,
    ];
    $domestic = commerce_usps_service_list('domestic');
    $options = [];
    foreach ($domestic as $id => $service) {
      $options[$id] = $service['title'];
    }
    $form['settings']['commerce_usps_services'] = [
      '#title' => t('USPS Services'),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#description' => t('Select the USPS services that are available to customers.'),
      '#default_value' => $this->configuration['commerce_usps_services'],
    ];

    $international = commerce_usps_service_list('international');
    $options = [];
    foreach ($international as $id => $service) {
      $options[$id] = $service['title'];
    }
    $form['settings']['commerce_usps_services_int'] = [
      '#title' => t('USPS International Services'),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#description' => t('Select the USPS International services that are available to customers.'),
      '#default_value' => $this->configuration['commerce_usps_services_int'],
    ];

    $form['api'] = [
      '#type' => 'fieldset',
      '#title' => t('USPS Connection Settings'),
      '#collapsible' => TRUE,
      '#weight' => 30,
    ];
    $form['api']['commerce_usps_connection_address'] = [
      '#type' => 'textfield',
      '#title' => t('Connection Address'),
      '#description' => t('Leave this set to http://production.shippingapis.com/ShippingAPI.dll unless you have a reason to change it.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['commerce_usps_connection_address'],
    ];
    $form['api']['commerce_usps_user'] = [
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#description' => t('The username for your USPS account.') . '<b>' . t('You must have been granted') . ' <a href="https://www.usps.com/business/web-tools-apis/developers-center.htm">' . t('production access') . '</a> ' . t('for this module to work.') . '</b>',
      '#required' => TRUE,
      '#default_value' => $this->configuration['commerce_usps_user'],
    ];

    $form['advanced'] = [
      '#type' => 'fieldset',
      '#title' => t('Advanced Options'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#weight' => 40,
    ];
    $form['advanced']['commerce_usps_show_logo'] = [
      '#type' => 'checkbox',
      '#title' => t('Show USPS logo next to service'),
      '#default_value' => $this->configuration['commerce_usps_show_logo'],
    ];
    $options = [
      'no' => t('Do not log messages'),
      'yes' => t('Log messages'),
    ];
    $form['advanced']['commerce_usps_log'] = [
      '#type' => 'radios',
      '#title' => t('Log messages from this module (useful for debugging)'),
      '#default_value' => $this->configuration['commerce_usps_log'],
      '#options' => $options,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValue($form['#parents']);
    // Check for a valid postal code.
    $postal_code = $values['origin']['commerce_usps_postal_code'];
    if (!is_numeric($postal_code) || strlen($postal_code) != 5) {
      $form_state->setErrorByName('plugin[0][plugin_select][target_plugin_configuration][origin][commerce_usps_postal_code]', $this->t('You must enter a 5 digit zip code'));
    }
    // Disallow USPS testing urls.
    $connection_address = $values['api']['commerce_usps_connection_address'];
    if (preg_match('/testing/', Unicode::strtolower($connection_address))) {
      $form_state->setErrorByName('plugin[0][plugin_select][target_plugin_configuration][api][commerce_usps_connection_address]', $this->t('Only production urls will work with this module. Please have USPS extend production access to your Webtools account by calling or emailing them.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['commerce_usps_postal_code'] = $values['origin']['commerce_usps_postal_code'];
      $this->configuration['commerce_usps_services'] = $values['settings']['commerce_usps_services'];
      $this->configuration['commerce_usps_services_int'] = $values['settings']['commerce_usps_services_int'];
      $this->configuration['commerce_usps_connection_address'] = $values['api']['commerce_usps_connection_address'];
      $this->configuration['commerce_usps_user'] = $values['api']['commerce_usps_user'];
      $this->configuration['commerce_usps_show_logo'] = $values['advanced']['commerce_usps_show_logo'];
      $this->configuration['commerce_usps_log'] = $values['advanced']['commerce_usps_log'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    if (!$this->validateOrder($shipment)) {
      return [];
    }

    $shippingAddress = $this->getShippingAddress($shipment);

    // Determine which type of rate request to submit.
    $shippingRates = [];
    if ($shippingAddress['country_code'] == 'US') {
      $domestic = commerce_usps_service_list('domestic');
      $rates = $this->domesticRateV4Request($shipment->getOrder(), $shippingAddress);
      foreach ($rates as $rate_id => $rate) {
        $shippingService = new ShippingService($rate_id, $domestic[$rate_id]['title']);
        $shippingRates[] = new ShippingRate($rate_id, $shippingService, $rate['amount']);
      }
    }
    else {
      $rates = $this->internationalRateV2Request($shipment->getOrder(), $shippingAddress);
      $international = commerce_usps_service_list('international');
      $services_intl = $this->configuration['commerce_usps_services_int'];
      foreach ($rates as $rate_id => $rate) {
        if (isset($services_intl[$rate_id]) && $services_intl[$rate_id]) {
          $shippingService = new ShippingService($rate_id, $international[$rate_id]['title']);
          $shippingRates[] = new ShippingRate($rate_id, $shippingService, $rate['amount']);
        }
      }
    }

    return $shippingRates;
  }

  /**
   * Validate order.
   *
   * @param object $shipment
   *   Shipment object.
   *
   * @return bool
   *   Returns TRUE if the order passes validation.
   */
  protected function validateOrder($shipment) {
    $order = $shipment->getOrder();
    foreach ($order->getItems() as $item) {
      $product = $item->getPurchasedEntity();
    }
    $shippingAddress = $this->getShippingAddress($shipment);

    // We have to have a shipping address to get rates.
    if (empty($shippingAddress)) {
      return FALSE;
    }

    // US shipping addresses require a zipcode.
    if ($shippingAddress['country_code'] == 'US' && empty($shippingAddress['postal_code'])) {
      return FALSE;
    }

    // Make sure the order is shippable.
    if (!$this->isShippable($shipment)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Check if shippable.
   *
   * @param object $shipment
   *   Shipment object.
   *
   * @return bool
   *   Returns TRUE if the order is shippable.
   */
  protected function isShippable($shipment) {
    $order = $shipment->getOrder();
    foreach ($order->getItems() as $item) {
      $product = $item->getPurchasedEntity();
      if (!$product || !$product->hasField('weight') || count($product->get('weight')->getValue()) == 0) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Get total weight.
   *
   * @param object $order
   *   Order object.
   * @param string $unit
   *   Weight unit.
   *
   * @return object
   *   Returns weight object.
   */
  protected function getWeight($order, $unit) {
    $total = new Weight('0', $unit);
    foreach ($order->getItems() as $item) {
      $product = $item->getPurchasedEntity();
      $quantity = $item->getQuantity();
      foreach ($product->get('weight')->getValue() as $value) {
        $number = $quantity * $value['number'];
        $weight = new Weight((string) $number, $value['unit']);
        $weight = $weight->convert($unit);
        $total = $total->add($weight);
      }
    }
    return $total;
  }

  /**
   * Get value of order.
   *
   * @param object $order
   *   Order object.
   *
   * @return string
   *   Returns subtotal.
   */
  protected function getShipmentValue($order) {
    $total = 0;
    $subtotal = explode(' ', $order->getSubtotalPrice());
    $total = $subtotal[0];
    return $total;
  }

  /**
   * Get the shipping address of the order.
   *
   * @param object $shipment
   *   Shipment object.
   *
   * @return array
   *   Address array.
   */
  protected function getShippingAddress($shipment) {
    $addressList = $shipment->getShippingProfile()->get('address');
    if (count($addressList) == 0) {
      return NULL;
    }
    $address = $addressList->getValue()[0];
    return $address;
  }

  /**
   * Builds a domestic USPS rate request.
   *
   * @param object $order
   *   The commerce order object.
   * @param object $shipping_address
   *   The commerce_customer_address array of the shipping profile.
   *
   * @return array
   *   An array of shipping rates.
   */
  protected function domesticRateV4Request($order, $shipping_address) {
    $rates = array();
    $usps_services = commerce_usps_service_list('domestic');

    $weight = $this->getWeight($order, WeightUnit::OUNCE);
    $pounds = floor($weight->getNumber() / 16.0);
    $ounces = fmod($weight->getNumber(), 16.0);

    $request = new \SimpleXMLElement('<RateV4Request/>');
    $request->addAttribute('USERID', $this->configuration['commerce_usps_user']);
    $request->addChild('Revision', 2);
    // @TODO: Support multiple packages based on physical attributes.
    // Add a package to the request for each enabled service.
    $i = 1;

    foreach ($this->configuration['commerce_usps_services'] as $machine_name => $service) {
      if (!empty($service)) {
        $package = $request->addChild('Package');
        $package->addAttribute('ID', $i);
        $package->addChild('Service', $usps_services[$machine_name]['request_name']);
        $package->addChild('FirstClassMailType', 'PARCEL');
        $package->addChild('ZipOrigination', substr($this->configuration['commerce_usps_postal_code'], 0, 5));
        $package->addChild('ZipDestination', substr($shipping_address['postal_code'], 0, 5));
        $package->addChild('Pounds', $pounds);
        $package->addChild('Ounces', $ounces);
        $package->addChild('Container', 'VARIABLE');
        $package->addChild('Size', 'REGULAR');
        $package->addChild('Machinable', 'TRUE');
        $i++;
      }
    }

    \Drupal::moduleHandler()->alter('commerce_usps_rate_v4_request', $request);

    // Submit the rate request to USPS.
    $response = $this->apiRequest('API=RateV4&XML=' . $request->asXML());

    if (!empty($response->Package)) {
      // Loop through each of the package results to build the rate array.
      $entity_manager = \Drupal::entityManager();
      $store = $entity_manager->getStorage('commerce_store')->loadDefault();
      $currency = $store->getDefaultCurrencyCode();
      foreach ($response->Package as $package) {
        if (empty($package->Error)) {
          // Load the shipping service's class id from the package response.
          $id = (string) $package->Postage->attributes()->{'CLASSID'};

          // Look up the shipping service by it's class id.
          $usps_service = commerce_usps_service_by_id($id, 'domestic');

          // Make sure that the package service is registered.
          if (!empty($usps_service['machine_name'])) {
            $amount = new Price((string) $package->Postage->Rate, 'USD');
            $amount = $amount->convert($currency);
            $rates[$usps_service['machine_name']] = array(
              'amount' => $amount,
              'currency_code' => $currency,
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
   * @param object $shipping_address
   *   The commerce_customer_address array of the shipping profile.
   *
   * @return array
   *   An array of shipping rates.
   */
  protected function internationalRateV2Request($order, $shipping_address) {
    $rates = array();
    $usps_services = commerce_usps_service_list('domestic');

    $weight = $this->getWeight($order, WeightUnit::OUNCE);
    $pounds = floor($weight->getNumber() / 16.0);
    $ounces = fmod($weight->getNumber(), 16.0);

    $request = new \SimpleXMLElement('<IntlRateV2Request/>');
    $request->addAttribute('USERID', $this->configuration['commerce_usps_user']);
    $request->addChild('Revision', 2);
    $shipment_value = $this->getShipmentValue($order);

    // @TODO: Support multiple packages based on physical attributes.
    $package = $request->addChild('Package');
    $package->addAttribute('ID', 1);
    $package->addChild('Pounds', $pounds);
    $package->addChild('Ounces', $ounces);
    $package->addChild('Machinable', 'True');
    $package->addChild('MailType', 'Package');
    $package->addChild('ValueOfContents', $shipment_value);
    $package->addChild('Country', commerce_usps_country_get_predefined_list($shipping_address['country_code']));
    $package->addChild('Container', 'RECTANGULAR');
    $package->addChild('Size', 'REGULAR');
    $package->addChild('Width', '');
    $package->addChild('Length', '');
    $package->addChild('Height', '');
    $package->addChild('Girth', '');
    $package->addChild('OriginZip', substr($this->configuration['commerce_usps_postal_code'], 0, 5));
    $package->addChild('CommercialFlag', 'N');

    \Drupal::moduleHandler()->alter('commerce_usps_intl_rate_v2_request', $request);

    // Submit the rate request to USPS.
    $response = $this->apiRequest('API=IntlRateV2&XML=' . $request->asXML());

    if (!empty($response->Package->Service)) {
      foreach ($response->Package->Service as $service) {
        $id = (string) $service->attributes()->{'ID'};

        // Look up the shipping service by it's id.
        $usps_service = commerce_usps_service_by_id($id, 'international');

        // Make sure that the package service is registered.
        if (!empty($usps_service['machine_name'])) {
          $amount = new Price((string) $service->Postage, 'USD');
          $rates[$usps_service['machine_name']] = array(
            'amount' => $amount,
            'currency_code' => 'USD',
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
  protected function apiRequest($request, $message = '') {

    if ($this->configuration['commerce_usps_log'] == 'yes') {
      \Drupal::logger('commerce_usps')->notice(t('Submitting API request to USPS. @message:<pre>@request</pre>', array('@message' => $message, '@request' => $request)));
    }

    $request_url = $this->configuration['commerce_usps_connection_address'];

    // Send the request.
    $client = \Drupal::httpClient();
    $options = ['body' => $request];
    try {
      $response = $client->request('POST', $request_url, ['body' => $request]);
      $code = $response->getStatusCode();
      if ($code == 200) {
        $body = $response->getBody()->getContents();
        if ($this->configuration['commerce_usps_log'] == 'yes') {
          \Drupal::logger('commerce_usps')->notice(t('Response code:@code<br />Response:<pre>@response</pre>', array('@code' => $code, '@response' => $body)));
        }
        return new \SimpleXMLElement($body);
      }
      else {
        if ($this->configuration['commerce_usps_log'] == 'yes') {
          \Drupal::logger('commerce_usps')->error(t('We did not receive a response from USPS. Make sure you have the correct server url in your settings.'));
        }
      }
    }
    catch (RequestException $e) {
      watchdog_exception('commerce_usps', $e);
    }

    return FALSE;
  }

}
