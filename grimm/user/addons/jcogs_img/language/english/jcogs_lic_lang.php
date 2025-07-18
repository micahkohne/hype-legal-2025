<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * JCOGS Licensing English Language file
 * =====================================
 * 
 * CHANGELOG
 * 
 * 5/6/2021: 1.0.0 - First Release
 * 18/8/2022: 1.0.1 - Update messages associated with license validation
 * 
 * =====================================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Licensing
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/jcogs_lic/license.html
 * @version    1.0.1
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.0.0
 */

$jcogs_addon_name = JCOGS_IMG_NAME;
$jcogs_license_server_path = ee('jcogs_img:Settings')::$settings['jcogs_license_server_domain'];
$jcogs_demo_mode_desc = 'While in demonstration mode %1$s will be fully functional but will add a watermark to any images processed.';
$jcogs_ee_store_url = 'https://expressionengine.com/add-ons/jcogs_img';
$jcogs_license_mode_url = 'https://jcogs.net/documentation/jcogs_img#jcogs-image-licensing-and-operating-modes';
$lang = [

    // Licensing tab headings
    'jcogs_lic_license'                                     => 'License',
    'jcogs_lic_register_license'                            => 'License Validation & Settings',

    // Save Button text
    'jcogs_lic_save_button'                                 => 'License Settings',

    // License status descriptors
    'jcogs_lic_cp_license_purchase_desc'                    => 'You can to purchase a license from the <a href=\''.$jcogs_ee_store_url.'\' target=\'_blank\'>EE Add-on Store</a>',
    'jcogs_lic_cp_license_key_demo_desc'                    => sprintf($jcogs_demo_mode_desc,JCOGS_IMG_NAME),
    'jcogs_lic_cp_license_valid_mode_desc'                  => sprintf('The license will allow %1$s to operate without limitation on a single public web site, and on any number of domains linked to <strong>local IP ranges</strong> or that end in <strong>.test</strong>, <strong>.local</strong> or <a href=\''.$jcogs_license_mode_url.'\' target=\'_blank\'>several other top-level domains</a>.',$jcogs_addon_name),
    'jcogs_lic_cp_license_magic_mode_desc'                  => sprintf('Congratulations! This license is a <strong>magic</strong> one. The license will allow %1$s to operate without limitation on on an unlimited number of public web sites, and on any number of domains linked to <strong>local IP ranges</strong> or that end in <strong>.test</strong>, <strong>.local</strong> or <a href=\''.$jcogs_license_mode_url.'\' target=\'_blank\'>several other top-level domains</a>.',$jcogs_addon_name),
    'jcogs_lic_cp_invalid_license_email'                    => 'The email address you have provided is invalid for this license code. Please check it and resubmit your request.',
    
    // Licensing Validation Markers
    'jcogs_lic_cp_license_key_valid'                        => sprintf('%1$s License Key<br><span class=\'status-tag st-open\'>VALID LICENSE KEY</span>',$jcogs_addon_name),
    'jcogs_lic_cp_license_key_staging'                      => sprintf('%1$s License Key<br><div style="padding-top:0.4rem;padding-bottom:0.4rem;"><span class=\'status-tag st-open\'>VALID LICENSE KEY</span> <span class=\'status-tag st-warning\'>STAGING SERVER</span></div>',$jcogs_addon_name),
    'jcogs_lic_cp_license_key_magic'                        => ' <span class=\'status-tag st-primary\'>MAGIC LICENSE</span>',
    'jcogs_lic_cp_license_key_demo'                         => sprintf('%1$s License Key<br><div style="padding-top:0.4rem;padding-bottom:0.4rem;"><span class=\'status-tag st-closed\'>INVALID LICENSE KEY</span> <span class=\'status-tag st-warning\'>DEMO MODE OPERATIONAL</span></div>',$jcogs_addon_name),
    'jcogs_lic_cp_license_key_invalid'                      => sprintf('%1$s License Key<br><div style="padding-top:0.4rem;padding-bottom:0.4rem;"><span class=\'status-tag st-closed\'>INVALID LICENSE KEY</span></div>',$jcogs_addon_name),
    'jcogs_lic_cp_license_key_process_error'                => sprintf('%1$s License Key',$jcogs_addon_name),
    
    // Licensing State Descriptions
    'jcogs_lic_cp_license_key_valid_desc'                   => sprintf('Thank you for purchasing a license key and registering it to this copy of %1$s.',$jcogs_addon_name),
    'jcogs_lic_cp_license_key_staging_desc'                 => 'This domain has been authorised as the staging domain for <span style="color:var(--ee-success-dark);font-weight:bold">%s</span>.<br>You can override this assignment by entering a valid license key / email pair.',
    'jcogs_lic_cp_license_key_missing_desc'                 => sprintf('%1$s is licensed software, to get full use from it please purchase a license key.<br>Without a license, %1$s will operate in a <strong>demonstration mode</strong>.',$jcogs_addon_name),
    'jcogs_lic_cp_license_key_invalid_desc'                 => sprintf('You are attempting to use an unlicensed copy of %1$s on a server connected to the public internet.<br>%1$s is licensed software, to be able to use it please purchase a license key.',$jcogs_addon_name),
    'jcogs_lic_cp_license_key_process_error_desc'           => 'Something odd has happened during the processing of your license for this add-on. Please contact <a href=\'mailto:support@jcogs.net\' target=\'_blank\'>support@jcogs.net</a> with details.',

    // Licensing Generic Messages
    'jcogs_lic_cp_license_support_desc'                     => 'If you encounter any difficulties activating your add-on please contact <a href=\'mailto:support@jcogs.net\' target=\'_blank\'>support@jcogs.net</a> with details.',
    'jcogs_lic_cp_no_license_key'                           => '<strong>Note:</strong> To get full use from this add-on please add a license key.',
    'jcogs_lic_cp_no_license_key_email'                     => '<strong>Note:</strong> To get full use from this add-on please add the email used to purchase your license key.',
    'jcogs_lic_cp_problem_talking_to_licensing_server'      => sprintf('<strong>Note:</strong> %1$s is having difficulty contacting the licensing server, here is what we heard: \%s.',$jcogs_addon_name),
    'jcogs_lic_cp_no_response_from_licensing_server'        => sprintf('<strong>Note:</strong> %1$s is unable to contact the licensing server. \%s.',$jcogs_addon_name),
    'jcogs_lic_cp_unable_to_reach_licensing_server'         => sprintf('<strong>Note:</strong> %1$s is having difficulty contacting the licensing server which it thinks is at \%s. Your previous license state will continue until a connection is resumed.',$jcogs_license_server_path),
    'jcogs_lic_cp_license_key_placeholder'                  => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
    'jcogs_lic_cp_license_key_email'                        => 'Enter License Key email address',
    'jcogs_lic_cp_license_key_email_desc'                   => 'Enter the email address used when you purchased the license from the add-on store.',
    'jcogs_lic_cp_license_key_email_placeholder'            => 'email@domain.com',
    'jcogs_lic_cp_invalid_license_key_format'               => 'The license key entered has the wrong format',
    'jcogs_lic_cp_invalid_license'                          => 'The license key entered is not valid for this add-on',
    'jcogs_lic_cp_missing_license_key_email'                => 'The license key email is missing',
    'jcogs_lic_cp_invalid_license_key_email'                => 'The license key email entered is not valid. Enter the email used to purchase the license from the add-on store used.',
    'jcogs_lic_cp_because_invalid_license_key_email'        => 'Unable to validate license code because email given has not been recognised. Please update the email and try again.',
    'jcogs_lic_cp_staging_domain'                           => 'Register a staging domain',
    'jcogs_lic_cp_staging_domain_desc'                      => 'You can nominate a single domain open to the public internet to be the staging server for this site. If this add-on is installed on an EE server at the domain stated it will automatically be validated.',
    'jcogs_lic_cp_invalid_staging_domain'                   => 'The staging domain you entered cannot be reached. Please enter a valid staging domain or leave field blank.',
    'jcogs_lic_demo_mode'                                   => '<span style="color:goldenrod;font-weight:bold">Alert:</span> This copy of %1$s is not licensed, operating in demo mode while on a local network.',
    'jcogs_lic_invalid_license'                             => '<span style="color:hotpink;font-weight:bold">Alert:</span> %1$s must be licensed to operate on public servers.',
    'jcogs_lic_cp_jcogs_licensing_server_domain'            => 'JCOGS License Server Domain',
    'jcogs_lic_cp_jcogs_licensing_server_domain_desc'       => 'The domain of the JCOGS License server.',
    'jcogs_lic_cp_invalid_licensing_domain'                 => 'Please enter a valid licensing server address.',

    // Public Messages
    ''                              => ''
];