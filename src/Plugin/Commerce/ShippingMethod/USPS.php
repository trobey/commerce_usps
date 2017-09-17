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
    $options = [
      'usps_first_class' => 'USPS First Class',
      'usps_priority_mail' => 'USPS Priority Mail',
      'usps_express_mail' => 'USPS Express Mail',
      'usps_standard_post' => 'USPS StandardPost',
      'usps_media_mail' => 'USPS Media Mail',
      'usps_library_mail' => 'USPS Library Mail',
    ];
    $form['settings']['commerce_usps_services'] = [
      '#title' => t('USPS Services'),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#description' => t('Select the USPS services that are available to customers.'),
      '#default_value' => $this->configuration['commerce_usps_services'],
    ];

    $options = [
      'usps_priority_mail_express_international' => 'USPS Priority Mail Express International',
      'usps_priority_mail_international' => 'USPS Priority Mail International',
      'usps_global_express_guaranteed' => 'USPS Global Express Guaranteed',
      'usps_priority_mail_international_small_flat_rate_box' => 'USPS Priority Mail International Small Flat Rate Box',
      'usps_priority_mail_international_medium_flat_rate_box' => 'USPS Priority Mail International Medium Flat Rate Box',
      'usps_priority_mail_international_large_flat_rate_box' => 'USPS Priority Mail International Large Flat Rate Box',
      'usps_first_class_mail_international_package' => 'USPS First-Class Mail International Package',
      'usps_priority_mail_express_international_flat_rate_boxes' => 'USPS Priority Mail Express International Flat Rate Boxes',
    ];
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

    // Rate IDs aren't used in a flat rate scenario because there's always a
    // single rate per plugin, and there's no support for purchasing rates.
    $rate_id = 0;
    $amount = new Price('1.00', 'USD');
    $rates = [];
    $rates[] = new ShippingRate($rate_id, $this->services['default'], $amount);

    return $rates;
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
    $shippingAddress = $this->getShippingAddress($shipment);

    // We have to have a shipping address to get rates.
    if (empty($shippingAddress)) {
      return FALSE;
    }

    // US shipping addresses require a zipcode.
    if ($shippingAddress['country_code'] == 'US' && empty($shippingAddress['postal_code'])) {
      return FALSE;
    }

    // Make sure the order is shippable (@todo: get weight).
    return TRUE;
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

}
