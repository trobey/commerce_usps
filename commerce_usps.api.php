<?php

/**
 * @file
 * Defines the USPS alter functions for Drupal Commerce.
 */

/**
 * Allows modules to alter the usps services that are available.
 *
 * @param array $usps_services
 *   The array of shipping services that are available.
 *
 * @see hook_commerce_usps_services_list_alter()
 */
function hook_commerce_usps_services_list_alter(&$usps_services) {
  // No example.
}

/**
 * Allows modules to alter the V4 XML request before it is sent to USPS.
 *
 * @param object $request
 *   The request object that gets sent to USPS.
 *
 * @see hook_commerce_usps_build_rate_request_alter()
 */
function hook_commerce_usps_rate_v4_request_alter(&$request) {
  // No example.
}

/**
 * Allows modules to alter the V4 XML request before it is sent to USPS.
 *
 * @param object $request
 *   The request object that gets sent to USPS.
 *
 * @see hook_commerce_usps_build_rate_request_alter()
 */
function hook_commerce_usps_intl_rate_v2_request_alter(&$request) {
  // No example.
}
