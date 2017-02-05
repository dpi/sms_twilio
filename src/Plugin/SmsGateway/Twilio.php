<?php

namespace Drupal\sms_twilio\Plugin\SmsGateway;

use Drupal\Core\Form\FormStateInterface;
use Drupal\sms\Message\SmsDeliveryReport;
use Drupal\sms\Message\SmsMessageResultStatus;
use Drupal\sms\Message\SmsMessageReportStatus;
use Drupal\sms\Plugin\SmsGatewayPluginBase;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Message\SmsMessageResult;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

/**
 * @SmsGateway(
 *   id = "twilio",
 *   label = @Translation("Twilio"),
 *   outgoing_message_max_recipients = 1,
 *   reports_push = TRUE,
 * )
 */
class Twilio extends SmsGatewayPluginBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'account_sid' => '',
      'auth_token' => '',
      'from' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['twilio'] = [
      '#type' => 'details',
      '#title' => $this->t('Twilio'),
      '#open' => TRUE,
    ];

    $form['twilio']['help'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('API keys can be found at <a href="https://www.twilio.com/console">https://www.twilio.com/console</a>.'),
    ];

    $form['twilio']['account_sid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Account SID'),
      '#default_value' => $config['account_sid'],
      '#placeholder' => 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
      '#required' => TRUE,
    ];

    $form['twilio']['auth_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Auth token'),
      '#default_value' => $config['auth_token'],
      '#placeholder' => 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX',
      '#required' => TRUE,
    ];

    $form['twilio']['from'] = [
      '#type' => 'textfield',
      '#title' => $this->t('From number'),
      '#default_value' => $config['from'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['account_sid'] = trim($form_state->getValue('account_sid'));
    $this->configuration['auth_token'] = trim($form_state->getValue('auth_token'));
    $this->configuration['from'] = $form_state->getValue('from');
  }

  /**
   * {@inheritdoc}
   */
  public function send(SmsMessageInterface $sms_message) {
    // Messages: https://www.twilio.com/docs/api/rest/message
    // Testing API: https://www.twilio.com/docs/api/rest/test-credentials

    $recipient = $sms_message->getRecipients()[0];
    $result = new SmsMessageResult();

    $account_sid = $this->configuration['account_sid'];
    $auth_token = $this->configuration['auth_token'];

    $client = new Client($account_sid, $auth_token);
    $options = [
      'from' => $this->configuration['from'],
      'body' => $sms_message->getMessage(),
    ];

    $report = new SmsDeliveryReport();
    $report->setRecipient($recipient);
    try {
      $message = $client->messages->create($recipient, $options);
      $report->setStatus(SmsMessageReportStatus::QUEUED);
      $report->setMessageId($message->uri);
    }
    catch (RestException $e) {
      $code = $e->getCode();
      $message = $e->getMessage();

      if (in_array($code, [21211, 21612, 21610, 21614])) {
        // 21211: Recipient is invalid. (Test recipient: +15005550001)
        // 21612: Cannot route to this recipient. (Test recipient: +15005550002)
        // 21610: Recipient is blacklisted. (Test recipient: +15005550004)
        // 21614: Recipient is incapable of receiving SMS.
        //       (Test recipient: +15005550009)
        $report->setStatus(SmsMessageReportStatus::INVALID_RECIPIENT);
        $report->setStatusMessage($message);
      }
      elseif ($code == 21408) {
        // 21408: Account doesn't have the international permission.
        //       (Test recipient: +15005550003)
        $result->setError(SmsMessageResultStatus::ACCOUNT_ERROR);
        $result->setStatusMessage($message);
      }
      else {
        $report->setStatus(SmsMessageReportStatus::ERROR);
        $report->setStatusMessage($message);
      }
    }

    if ($report->getStatus()) {
      $result->setReports([$report]);
    }

    return $result;
  }

}
