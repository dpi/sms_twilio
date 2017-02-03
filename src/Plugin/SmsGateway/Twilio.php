<?php

namespace Drupal\sms_twilio\Plugin\SmsGateway;

use Drupal\Core\Form\FormStateInterface;
use Drupal\sms\Direction;
use Drupal\sms\Entity\SmsGatewayInterface;
use Drupal\sms\Message\SmsDeliveryReport;
use Drupal\sms\Message\SmsMessage;
use Drupal\sms\Message\SmsMessageResultStatus;
use Drupal\sms\Message\SmsMessageReportStatus;
use Drupal\sms\Plugin\SmsGatewayPluginBase;
use Drupal\sms\Message\SmsMessageInterface;
use Drupal\sms\Message\SmsMessageResult;
use Drupal\sms\SmsProcessingResponse;
use Drupal\sms_twilio\Utility\TwilioMedia;
use Drupal\sms_twilio\Utility\TwilioValidation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client;

/**
 * @SmsGateway(
 *   id = "twilio",
 *   label = @Translation("Twilio"),
 *   outgoing_message_max_recipients = 1,
 *   reports_push = TRUE,
 *   incoming = TRUE,
 *   incoming_route = TRUE
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
        $report->setStatus(SmsMessageReportStatus::ERROR);
        $report->setStatusMessage($message);
      }
      else {
        $report->setStatus(SmsMessageReportStatus::ERROR);
        $report->setStatusMessage($message);
      }
    }

    if ($report->getStatus()) {
      $result->addReport($report);
    }

    return $result;
  }

  /**
   * Validates the webhook request and creates an SMS message object.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request The current request.
   *
   * @return \Drupal\sms\Message\SmsMessage The parsed message.
   */
  protected function buildIncomingFromRequest(Request $request) {
    $result = new SmsMessageResult();
    $params = $request->request->all();
    $report = (new SmsDeliveryReport())
      ->setRecipient($params['To'])
      ->setStatus(SmsMessageReportStatus::DELIVERED);
    $sms = (new SmsMessage())
      ->setMessage(trim($params['Body']))
      ->setDirection(Direction::INCOMING)
      ->setOption('data', $params)
      ->setSenderNumber($params['From'])
      ->addRecipients([$params['To']]);
    if ($files = TwilioMedia::processMedia($params)) {
      $sms->setOption('media', $files);
    }
    if(!TwilioValidation::validateIncoming($request, $this)) {
      $report->setStatus(SmsMessageReportStatus::REJECTED);
      $report->setStatusMessage($e->getMessage());
      $result->setError($e->getCode());
      $result->setErrorMessage($e->getMessage());
    }
    $result->addReport($report);
    $sms->setResult($result);
    return $sms;
  }

  /**
   * Callback for processing incoming messages.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request The active request.
   * @param \Drupal\sms\Entity\SmsGatewayInterface $gateway The SMS gateway.
   *
   * @return \Drupal\sms\SmsProcessingResponse The processing response.
   */
  public function processIncoming(Request $request, SmsGatewayInterface $sms_gateway) {
    $task = new SmsProcessingResponse();
    $sms = $this->buildIncomingFromRequest($request);
    $sms->setGateway($sms_gateway);
    // Replies should be handled in implementing code.
    $response = new Response();
    if ($sms->getResult()->getError()) {
      $response = new Response($sms->getResult()->getErrorMessage(), $sms->getResult()->getError());
    }
    $task->setMessages([$sms]);
    $task->setResponse($response);
    return $task;
  }

}
