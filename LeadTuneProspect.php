<?php

define('LT_HOST', 'appraiser.leadtune.com');
define('LT_HOST_SANDBOX', 'sandbox-appraiser.leadtune.com');

class LeadTuneException extends Exception {}

class LeadTuneProspect {
  private $host;
  private $route;
  public $curl;

  /**
   * All that you need here are your username and password.
   * $host and $curl parameters are used for testing and diagnostic purposes only.
   *
   * If you would like to test this class within the testing sandbox, pass in
   * LT_SANDBOX_HOST as the third argument here.
   */
  public function __construct($user, $password, $host = LT_HOST, $curl = NULL) {
    $this->host = $host;
    $this->route = 'https://' . $host . '/prospects';
    $this->curl =
        !empty($curl) ?
        $curl :
        new LeadTuneCurl($user, $password, $host, $this->route);
  }

  /**
   * Creates a new prospect.
   * Accepts either an array or valid JSON of factors corresponding to one prospect.
   *
   * See: https://leadtune.com/api/seller#create
   */
  public function create($attributes) {
    return $this->curl->request(NULL, NULL, "POST", $attributes);
  }

  /**
   * Retrieves an existing prospect
   * Accepts a prospect ID and your organization's organization code.
   *
   * See: https://leadtune.com/api/seller#read
   */
  public function read($prospect_id, $organization) {
    return $this->curl->request($prospect_id, "organization=$organization");
  }

  /**
   * Updates an existing prospect.
   * Currently unimplemented.
   */
  public function update($prospect_id, $attributes) {
    throw new LeadTuneException("Prospect update currently unavailable.");
    return $this->curl->request($prospect_id, NULL, "PUT", $attributes);
  }

  /**
   * Deletes an existing prospect.
   * Currently unimplemented.
   */
  public function delete($prospect_id, $organization) {
    throw new LeadTuneException("Prospect deletion currently unavailable.");
    return $this->curl->request($prospect_id, "organization=$organization", "DELETE");
  }

  /**
   * Retrives the prospect ID of a prospect given your organization code
   * and a prospect_ref factor you supplied during prospect creation.
   *
   * See: https://leadtune.com/api/seller#historical
   */
  public function getProspectId($organization, $prospect_ref) {
    return $this->curl->request(NULL, "organization=$organization&prospect_ref=$prospect_ref");
  }
}

class LeadTuneCurl {
  private $user;
  private $password;
  private $host;
  private $route;

  public function __construct($user, $password, $host, $route) {
    $this->user = $user;
    $this->password = $password;
    $this->host = $host;
    $this->route = $route;
  }

  private function generateAuth() {
    return base64_encode("{$this->user}:{$this->password}");
  }

  public function request($prospect_id, $query_string = NULL, $method = "GET", $data = NULL) {
    $ch = curl_init();

    if (!empty($data)) {
      $data = is_array($data) ? json_encode($data) : $data;
      curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
      self::jsonParseErrorHandler($data, json_last_error());
    }

    $url = $this->route;
    if (!empty($prospect_id)) $url .= "/$prospect_id";
    if (!empty($query_string)) $url .= "?$query_string";

    $auth = self::generateAuth();

    $header = array(
      "Accept: application/json",
      "Content-Type: application/json",
      "Host: {$this->host}",
      "Authorization: Basic $auth"
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
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $this->curlHttpCodeHandler($http_code);
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

  private function curlHttpCodeHandler($http_code) {
    switch($http_code) {
    case 200:
    case 201:
      break;
    case 400:
      throw new LeadTuneException("400 Bad Request: Your request cannot be understood (e.g., the body is malformed and could not be parsed).");
      break;
    case 401:
      throw new LeadTuneException("401 Unauthorized: You failed to authenticate, or you are not authorized for the requested action.");
      break;
    case 403:
      throw new LeadTuneException("403 Forbidden: Your request was understood, but is not allowed (e.g., you are trying to supply an invalid value or missing a required value).");
      break;
    case 404:
      throw new LeadTuneException("404 Not Found: The resource you requested could not be located (e.g., id did not exist).");
      break;
    case 500:
      throw new LeadTuneException("500 Internal Server Error: Something unexpected went wrong at LeadTune.");
      break;
    case 503:
      throw new LeadTuneException("503 Service Unavailable: The service is offline (e.g., scheduled maintenance).");
      break;
    default:
      throw new LeadTuneException("Unexpected HTTP response: $http_code");
    }
  }
}
