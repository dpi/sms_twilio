<?php

namespace Drupal\sms_twilio;

use Drupal\sms_twilio\Plugin\SmsGateway\Twilio;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Twilio\Security\RequestValidator;

/**
 * Class SmsUtility
 *
 * Provides utilities for handling Twilio's SMS implementation.
 *
 * @package Drupal\sms_twilio
 */
class SmsUtility {

  /**
   * Validate a request as authentic from Twilio for a given gateway configuration.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * @param \Drupal\sms_twilio\Plugin\SmsGateway\Twilio $plugin
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  public static function validate(Request $request, Twilio $plugin) {
    /** @var \Drupal\Component\Plugin\ConfigurablePluginInterface $plugin */
    $token = $plugin->getConfiguration()['auth_token'];
    $signature = $request->headers->get('x-twilio-signature');
    $url = $request->getSchemeAndHttpHost() . $request->getRequestUri();
    if (!(new RequestValidator($token))->validate($signature, $url, $request->request->all())) {
      throw new AccessDeniedHttpException('Signature validation failed.');
    }
  }

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
