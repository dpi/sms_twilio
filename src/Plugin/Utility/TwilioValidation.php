<?php
/**
 * @file contains TwilioValidator.php
 * Defines methods for validating Twilio messages
 */

namespace Drupal\sms_twilio\Utility;
use Drupal\Core\Url;
use Drupal\sms_twilio\Plugin\SmsGateway\Twilio;
use Symfony\Component\HttpFoundation\Request;
use Twilio\Security\RequestValidator;

/**
 * Class TwilioValidator
 * @package Drupal\sms_twilio\Utility
 */
class TwilioValidation {
  /**
   * Validate an incoming message using Twilio SDK
   * @see https://www.twilio.com/docs/api/security
   */
  public static function validateIncoming(Request $request, Twilio $sms_gateway) {
    $url = Url::fromRoute('sms.incoming.receive.twilio')
      ->setAbsolute()
      ->toString();
    $signature = $request->server->get('HTTP_X_TWILIO_SIGNATURE');
    $token = $sms_gateway->getConfiguration()['auth_token'];

    $validator = new RequestValidator($token);

    return $validator->validate($signature, $url, $_REQUEST);
  }
}
