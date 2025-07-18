<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * JCOGS Utilities English Language file
 * =====================================
 * 
 * CHANGELOG
 * 
 * 20/3/2023: 1.0.0     First Release
 * 
 * =====================================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Utilities
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.0.0
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.0.0
 */

$lang = [
    // Module Messages
    'jcogs_utils_no_base_path'                               => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> Your EE installation is reporting that you have not set a valid base_path. JCOGS Image cannot operate without one, and so it is giving up ... ',
    'jcogs_utils_invalid_base_path'                          => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> Your EE installation is reporting that the value set for EE\'s base_path is not valid. JCOGS Image cannot operate without a valid base_path and so it is giving up ... ',
    'jcogs_utils_invalid_dimension'                          => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> The dimensions provided are not recognised - use %, px or integer values only.',
    'jcogs_utils_no_cache_key_provided'                      => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> Cache operation attempted but no key provided. Baling out.', 
    'jcogs_utils_unknown_cache_operation_requested'          => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> Cache operation attempted but action requested not recognised. Baling out.', 
    'jcogs_utils_UTF8_conversion_failed'                     => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> Attempt to convert text to UTF8 failed.', 
    'jcogs_utils_gffl_started'                               => 'Retrieving file from local location: ',
    'jcogs_utils_gffl_success'                               => 'Retrieved file from local location: ',
    'jcogs_utils_gffr_started'                               => 'Retrieving file from remote location: ',
    'jcogs_utils_gffr_success'                               => 'Retrieved file from remote location: ',
    'jcogs_utils_gfcc_started'                               => 'File is remote: Curl retrieval process started for ',
    'jcogs_utils_gfcc_success'                               => 'File retrieved from remote location: ',
    'jcogs_utils_gfgc_started'                               => 'File is remote: FGC retrieval process started for ',
    'jcogs_utils_gfgc_success'                               => 'File retrieved from remote location: ',
    
    // Error messages
    'jcogs_utils_gffr_no_path'                               => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> No path provided for remote file retrieval.',
    'jcogs_utils_gfcc_no_path'                               => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> No path provided for remote file retrieval.',
    'jcogs_utils_gfcc_no_curl'                               => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> CURL not available on this server.',
    'jcogs_utils_gfcc_error'                                 => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> Something went wrong contacting the remote location. The error reported was ',
    'jcogs_utils_gfcc_failed'                                => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> CURL failed to retrieve the file.',
    'jcogs_utils_gfgc_no_path'                               => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> No path provided for remote file retrieval.',
    'jcogs_utils_gfgc_error'                                 => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> Something went wrong contacting the remote location. The error reported was ',
    'jcogs_utils_gfgc_failed'                                => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> file_get_contents() failed to retrieve the remote file.',
    'jcogs_utils_allow_url_fopen_disabled'                   => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> Unable to access remote locations (possibly allow_url_fopen is not enabled) ... please report this to JCOGS Design (contact@jcogs.net) so it can be investigated further. Thanks! ', 
    'jcogs_utils_unable_to_open_path_retry'                  => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> Unable to confirm presence of file, retrying using different method.',
    'jcogs_utils_unable_to_open_path_1'                      => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> Unable to read the file from a local path.',
    'jcogs_utils_unable_to_open_path_2'                      => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> File path given is reported invalid by EE (ee("Filesystem")->exists).',
    'jcogs_utils_unable_to_open_path_3'                      => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> Unable to read file normally from remote path, trying again with slightly less secure method which sometimes works...',
    'jcogs_utils_unable_to_open_path_4'                      => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> Remote host did not return a file in response to URL submitted. Unable to obtain a file. Trying CURL...',
    'jcogs_utils_unable_to_open_path_5'                      => '<span style="color:var(--ee-error);font-weight:bold">Error:</span> Unable to read file from remote path given',
];