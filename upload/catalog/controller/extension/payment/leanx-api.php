<?php

class LeanxApi
{
  private $auth_token;
  private $is_sandbox;
  private $process;

  public $url;

  const PRODUCTION_URL = 'https://api.leanx.dev/api/v1/';
  const SANDBOX_URL = 'https://api.leanx.dev/api/v1/';

  public function __construct($token, $is_sandbox)
  {
    $this->auth_token = $token;
    $this->is_sandbox = $is_sandbox;
    if (!$this->is_sandbox) {
      $this->url = self::PRODUCTION_URL;
    } else {
      $this->url = self::SANDBOX_URL;
    }

    $this->process = curl_init();
    curl_setopt($this->process, CURLOPT_HTTPHEADER, [
      'auth-token:' . $this->auth_token,
      'Content-Type: application/json'
    ]);
    curl_setopt($this->process, CURLOPT_RETURNTRANSFER, true);
  }

  public function validateAuthToken()
  {
    $url = $this->url . 'public-merchant/validate';

    curl_setopt($this->process, CURLOPT_URL, $url);
    curl_setopt($this->process, CURLOPT_POST, 1);
    curl_setopt($this->process, CURLOPT_POSTFIELDS, http_build_query(array()));
    $body = curl_exec($this->process);
    $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);

    return array($header, $body);
  }

  public function paymentPortal($invoice_no, $param)
  {
    $url = $this->url . 'public-merchant/public/collection-payment-portal?invoice_no=' . $invoice_no;

    curl_setopt($this->process, CURLOPT_URL, $url);
    curl_setopt($this->process, CURLOPT_POST, true);
    curl_setopt($this->process, CURLOPT_POSTFIELDS, json_encode($param));
    $body = curl_exec($this->process);
    $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);

    return array($header, json_decode($body, true));
  }

  public function manualValidateInvoice($invoice_no)
  {
    $url = $this->url . 'public-merchant/public/manual-checking-transaction?invoice_no=' . $invoice_no;

    curl_setopt($this->process, CURLOPT_URL, $url);
    curl_setopt($this->process, CURLOPT_POST, true);
    $body = curl_exec($this->process);
    $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);

    return array($header, json_decode($body, true));
  }

  public function decode($string)
  {
    $url = $this->url . 'jwt/decode';

    curl_setopt($this->process, CURLOPT_URL, $url);
    curl_setopt($this->process, CURLOPT_POST, true);
    curl_setopt(
      $this->process,
      CURLOPT_POSTFIELDS,
      json_encode(
        array(
          'signed' => $string
        )
      )
    );
    $body = curl_exec($this->process);
    $header = curl_getinfo($this->process, CURLINFO_HTTP_CODE);

    return array($header, json_decode($body, true));
  }
}