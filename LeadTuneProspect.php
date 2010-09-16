<?php

define('LT_HOST', 'appraiser.leadtune.com');

class LeadTuneException extends Exception {}

class LeadTuneProspect {
  private $auth;
  private $host;
  private $route;

  public function __construct($user, $password, $host = LT_HOST) {
    $this->auth = base64_encode("$user:$password");
    $this->host = $host;
    $this->route = 'http://' . $host . '/prospects';
  }

  public function create($attributes) {
    return $this->curlRequest(NULL, "POST", $attributes);
  }

  public function read($prospect_id, $organization) {
    return $this->curlRequest("$prospect_id?organization=$organization");
  }

  public function update($prospect_id, $attributes) {
    throw new LeadTuneException("Prospect update currently unavailable.");
    return $this->curlRequest($prospect_id, "PUT", $attributes);
  }

  public function delete($prospect_id, $organization) {
    throw new LeadTuneException("Prospect deletion currently unavailable.");
    return $this->curlRequest("$prospect_id?organization=$organization", "DELETE");
  }

  public function getProspectId($organization, $prospect_ref) {
    return $this->curlRequest("?organization=$organization&prospect_ref=$prospect_ref");
  }

  private function curlRequest($url, $method = "GET", $data = NULL) {
    $ch = curl_init();

    if (!empty($data)) {
      $data = is_array($data) ? json_encode($data) : $data;
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
      $this->jsonParseErrorHandler($data, json_last_error());
    }

    $url = !empty($url) ? "{$this->route}/$url" : $this->route;

    $header = array(
      "Accept: application/json",
      "Content-Type: application/json",
      "Host: {$this->host}",
      "Authorization: Basic {$this->auth}"
    );

    $options = array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_CUSTOMREQUEST => $method,
      CURLOPT_HTTPHEADER => $header,
    );

    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $response_decoded = json_decode($response, TRUE);

    $error_code = curl_errno($ch);
    $error_string = curl_error($ch);

    curl_close($ch);

    $this->curlRequestErrorHandler($error_code, $error_string);
    $this->jsonParseErrorHandler($response, json_last_error());

    return $response_decoded;
  }

  private function jsonParseErrorHandler($data, $json_error) {
    if (!empty($json_error)) {
      switch($json_error) {
      case JSON_ERROR_DEPTH:
        $json_error_string = "The maximum stack depth has been exceeded";
      case JSON_ERROR_CTRL_CHAR:
        $json_error_string = "Control character error, possibly incorrectly encoded";
      case JSON_ERROR_STATE_MISMATCH:
        $json_error_string = "Invalid or malformed JSON";
      case JSON_ERROR_SYNTAX:
        $json_error_string = "Syntax error";
      }

      throw new LeadTuneException(
        "Unable to parse JSON data.\n\n" .
        "Reason: json error $json_error: $json_error_string\n\n" .
        "Raw data: " . print_r($data, TRUE) . "\n\n" .
        "See http://www.php.net/manual/en/function.json-last-error.php"
      );
    }
  }

  private function curlRequestErrorHandler($error_code, $error_string) {
    if (!empty($error_code)) {
      throw new LeadTuneException("Unable to complete request.\n\nReason: curl error $error_code: $error_string");
    }
  }
}
