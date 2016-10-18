<?php

namespace Drupal\sms_twilio;

use Drupal\sms\Plugin\SmsGatewayPluginIncomingInterface;
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
   * @param \Drupal\sms\Plugin\SmsGatewayPluginIncomingInterface $plugin
   * 
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   */
  static public function validate(Request $request, SmsGatewayPluginIncomingInterface $plugin) {
    /** @var \Drupal\Component\Plugin\ConfigurablePluginInterface $plugin */
    $token = $plugin->getConfiguration()['auth_token'];
    $signature = $request->headers->get('x-twilio-signature');
    $url = $request->getSchemeAndHttpHost() . $request->getRequestUri();
    if (!(new RequestValidator($token))->validate($signature, $url, $request->request->all())) {
      throw new AccessDeniedHttpException();
    }
  }

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
