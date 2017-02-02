<?php

namespace Drupal\sms_twilio\Utility;

use Drupal\Core\Url;
use Drupal\sms\Plugin\SmsGatewayPluginInterface;
use Symfony\Component\HttpFoundation\Request;
use Twilio\Security\RequestValidator;

/**
 * Class TwilioValidation
 *
 * Methods for validating incoming webhook POST events from Twilio.
 *
 * @package Drupal\sms_twilio\Utility
 */
class TwilioValidation {

  /**
   * Validate an incoming message using Twilio SDK
   * @see https://www.twilio.com/docs/api/security
   *
   * @param \Symfony\Component\HttpFoundation\Request $request The request object.
   * @param \Drupal\Component\Plugin\ConfigurablePluginInterface The Twilio plugin.
   *
   * @return boolean TRUE if the request validates, FALSE if not.
   */
  public static function validateIncoming(Request $request, SmsGatewayPluginInterface $sms_gateway) {
    $url = Url::fromRoute('sms.incoming.receive.' . $sms_gateway->getPluginId())
      ->setAbsolute()
      ->toString();
    $signature = $request->headers->get('x-twilio-signature');
    $token = $sms_gateway->getConfiguration()['auth_token'];

    $validator = new RequestValidator($token);

    return $validator->validate($signature, $url, $request->request->all());
  }

}
