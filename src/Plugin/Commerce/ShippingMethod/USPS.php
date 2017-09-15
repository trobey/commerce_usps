<?php

namespace Drupal\commerce_usps\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;

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

    $this->services['default'] = new ShippingService('default', $this->configuration['rate_label']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'from' => '',
      'services' => [
        'domestic' => [],
        'international' => [],
      ],
      'connection' => [
        'address' => 'http://production.shippingapis.com/ShippingAPI.dll',
        'username' => '',
      ],
      'advanced' => [
        'logo' => 0,
        'log' => 'no',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['from'] = [
      '#type' => 'fieldset',
      '#title' => t('Ship from location'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#weight' => 10,
    ];
    $form['from']['location'] = [
      '#type' => 'textfield',
      '#title' => t('Postal Code'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['from'],
    ];

    $form['services'] = [
      '#type' => 'fieldset',
      '#title' => t('Shipment settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#weight' => 20,
      '#tree' => TRUE,
    ];
    $options = [
      'usps_first_class' => 'USPS First Class',
      'usps_priority_mail' => 'USPS Priority Mail',
      'usps_express_mail' => 'USPS Express Mail',
      'usps_standard_post' => 'USPS StandardPost',
      'usps_media_mail' => 'USPS Media Mail',
      'usps_library_mail' => 'USPS Library Mail',
    ];
    $form['services']['domestic'] = [
      '#title' => t('USPS Services'),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#description' => t('Select the USPS services that are available to customers.'),
      '#default_value' => $this->configuration['services']['domestic'],
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
    $form['services']['international'] = [
      '#title' => t('USPS International Services'),
      '#type' => 'checkboxes',
      '#options' => $options,
      '#description' => t('Select the USPS International services that are available to customers.'),
      '#default_value' => $this->configuration['services']['international'],
    ];

    $form['connection'] = [
      '#type' => 'fieldset',
      '#title' => t('USPS Connection Settings'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#weight' => 30,
    ];
    $form['connection']['address'] = [
      '#type' => 'textfield',
      '#title' => t('Connection Address'),
      '#description' => t('Leave this set to http://production.shippingapis.com/ShippingAPI.dll unless you have a reason to change it.'),
      '#required' => TRUE,
      '#default_value' => $this->configuration['connection']['address'],
    ];
    $form['connection']['username'] = [
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#description' => t('The username for your USPS account.') . '<b>' . t('You must have been granted') . ' <a href="https://www.usps.com/business/web-tools-apis/developers-center.htm">' . t('production access') . '</a> ' . t('for this module to work.') . '</b>',
      '#required' => TRUE,
      '#default_value' => $this->configuration['connection']['username'],
    ];

    $form['advanced'] = [
      '#type' => 'fieldset',
      '#title' => t('Advanced Options'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
      '#weight' => 40,
    ];
    $form['advanced']['logo'] = [
      '#type' => 'checkbox',
      '#title' => t('Show USPS logo next to service'),
      '#default_value' => $this->configuration['advanced']['logo'],
    ];
    $options = [
      'no' => t('Do not log messages'),
      'yes' => t('Log messages'),
    ];
    $form['advanced']['log'] = [
      '#type' => 'radios',
      '#title' => t('Log messages from this module (useful for debugging)'),
      '#default_value' => $this->configuration['advanced']['log'],
      '#options' => $options,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['from'] = $values['from']['location'];
      $this->configuration['services'] = $values['services'];
      $this->configuration['connection'] = $values['connection'];
      $this->configuration['advanced'] = $values['advanced'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateRates(ShipmentInterface $shipment) {
    // Rate IDs aren't used in a flat rate scenario because there's always a
    // single rate per plugin, and there's no support for purchasing rates.
    $rate_id = 0;
    $amount = $this->configuration['rate_amount'];
    $amount = new Price($amount['number'], $amount['currency_code']);
    $rates = [];
    $rates[] = new ShippingRate($rate_id, $this->services['default'], $amount);

    return $rates;
  }

}
