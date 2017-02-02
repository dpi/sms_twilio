<?php

namespace Drupal\sms_twilio\Utility;

/**
 * Class TwilioMedia
 *
 * Contains methods for working with media sent by SMS.
 *
 * @package Drupal\sms_twilio\Utility
 */
class TwilioMedia {

  /**
   * Helper function for processing attached SMS/MMS media.
   *
   * @param array $params The original payload from Twilio.
   * @return array An array of files, with url and content-type keys.
   */
  public static function processMedia(array $params) {
    $i = 0;
    $files = [];
    while ($i < $params['NumMedia']) {
      $files[] = [
        'url' => $params['MediaUrl' . $i],
        'content-type' => $params['MediaContentType' . $i],
      ];
      $i++;
    }
    return $files;
  }
}
