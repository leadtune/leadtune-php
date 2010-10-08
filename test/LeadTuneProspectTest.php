<?php

/**
 * To run this script against live resources, issue the command:
 *
 * php LeadTuneProspectTest.php live
 */

error_reporting(E_ALL & ~E_DEPRECATED);

require '../LeadTuneProspect.php';
require 'simpletest/autorun.php';

define('LT_TEST_USER', "admin@acme.edu");
define('LT_TEST_PASSWORD', "admin");
define('LT_TEST_ROUTE', LT_PROTOCOL . LT_HOST_SANDBOX . "/prospects");
define('LT_MOCK_TESTS', !(!empty($argv[1]) && ($argv[1] == 'live')));

Mock::generate('LeadTuneCurl');

class LeadTuneProspectTest extends UnitTestCase {
  private $curl;

  public function setUp() {
    if (LT_MOCK_TESTS) {
      $this->curl = new MockLeadTuneCurl('mock@mock.com', 'mock', 'mock.com', 'mock.com/prospects');
    }
    else {
      $this->curl = new LeadTuneCurl(LT_TEST_USER, LT_TEST_PASSWORD, LT_HOST_SANDBOX, LT_TEST_ROUTE);
    }

    $this->ltp = new LeadTuneProspect(LT_TEST_USER, LT_TEST_PASSWORD, LT_HOST_SANDBOX, $this->curl);
  }

  public function testConstructorValid() {
    $this->assertTrue($this->ltp instanceof LeadTuneProspect);
  }

  public function testConstructorInvalid() {
    $ltp = new LeadTuneProspect("fake@fake.org", "fake", "sandbox-appraiser.leadtune.com");
    $this->assertTrue($ltp instanceof LeadTuneProspect);
  }

  public function testCreateProspectInvalidCredentials() {
    $curl = NULL;

    if (LT_MOCK_TESTS) {
      $curl = new MockLeadTuneCurl('mock@mock.com', 'mock', 'mock.com', 'mock.com/prospects');
      $curl->setReturnValue('request',
        new LeadTuneException("401 Unauthorized: You failed to authenticate, or you are not authorized for the requested action."));
    }

    $ltp = new LeadTuneProspect("fake@fake.org", "fake", "sandbox-appraiser.leadtune.com", $curl);

    $message = NULL;

    try {
      $response = $ltp->create(array("organization" => "AcmeU", "event" => "lead_accepted", "email" => "i.m@nice.com"));
      if ($response instanceof LeadTuneException) throw $response;
      $this->assertTrue(FALSE);
    }
    catch (LeadTuneException $e) {
      $message = $e->getMessage();
    }

    $this->assertEqual($message,
      "401 Unauthorized: You failed to authenticate, or you are not authorized for the requested action.");
  }

  public function testCreateProspectValidCredentials() {
    if (LT_MOCK_TESTS) {
      $this->curl->setReturnValue('request', array (
        'prospect_id' => '4c98d5a76c4501dd1c7d9f7e',
        'organization' => 'AcmeU',
        'event' => 'lead_accepted',
        'email_hash' => '6ca13272a715b70a3f2d546d99484af08c8ab324',
        'created_at' => '2010-09-21T15:56:23Z',
        'expires_at' => '2010-12-20T15:56:23Z',
      ));
    }

    $prospect_created = $this->ltp->create(array(
      "organization" => "AcmeU",
      "event" => "lead_accepted",
      "email" => "i.m@nice.com"
    ));
    $this->assertTrue(is_array($prospect_created));

    $expected_factors = array(
      'organization',
      'event',
      'email_hash',
      'created_at',
      'expires_at',
      'prospect_id'
    );

    foreach($expected_factors as $factor) {
      $this->assertTrue(!empty($prospect_created[$factor]));
    }
  }

  public function testCreateProspectInsufficientFactors() {
    if (LT_MOCK_TESTS) {
      $this->curl->setReturnValue('request',
        new LeadTuneException('403 Forbidden: Your request was understood, but is not allowed (e.g., you are trying to supply an invalid value or missing a required value).'));
    }

    try {
      $response = $this->ltp->create(array("organization" => "AcmeU", "email" => "i.m@nice.com"));
      if ($response instanceof LeadTuneException) throw $response;
      $this->assertTrue(FALSE);
    }
    catch (LeadTuneException $e) {
      $this->assertEqual($e->getMessage(),
        "403 Forbidden: Your request was understood, but is not allowed (e.g., you are trying to supply an invalid value or missing a required value).");
    }
  }

  public function testReadProspect() {
    if (LT_MOCK_TESTS) {
      $this->curl->setReturnValue('request', array (
          'prospect_id' => '4c98d9356c4501dd507d9f7e',
          'organization' => 'AcmeU',
          'event' => 'lead_accepted',
          'email_hash' => '6ca13272a715b70a3f2d546d99484af08c8ab324',
          'created_at' => '2010-09-21T16:11:34Z',
          'expires_at' => '2010-12-20T16:11:34Z',
      ));
    }

    $prospect_created = $this->ltp->create(array(
      "organization" => "AcmeU",
      "event" => "lead_accepted",
      "email" => "i.m@nice.com"
    ));

    $prospect_retrieved = $this->ltp->read($prospect_created['prospect_id'], $prospect_created['organization']);

    $this->assertEqual($prospect_created["prospect_id"], $prospect_retrieved["prospect_id"]);

    $expected_factors = array(
      'organization',
      'event',
      'email_hash',
      'created_at',
      'expires_at',
      'prospect_id'
    );

    foreach($expected_factors as $factor) {
      $this->assertTrue(!empty($prospect_retrieved[$factor]));
    }
  }

  public function testReadNonExistentProspect() {
    if (LT_MOCK_TESTS) {
      $this->curl->setReturnValue('request',
        new LeadTuneException('404 Not Found: The resource you requested could not be located (e.g., id did not exist).'));
    }

    try {
      $response = $this->ltp->read('xyzzy', 'AcmeU');
      if ($response instanceof LeadTuneException) throw $response;
      $this->assertTrue(FALSE);
    }
    catch (LeadTuneException $e) {
      $this->assertEqual($e->getMessage(),
        "404 Not Found: The resource you requested could not be located (e.g., id did not exist).");
    }
  }

  public function testGetProspectId() {
    $prospect_ref = 'testGetProspectId_' . time();
    $prospect_created = $this->ltp->create(array(
      "organization" => "AcmeU",
      "event" => "lead_accepted",
      "email" => "i.m@nice.com",
      "prospect_ref" => $prospect_ref
    ));
    $prospect_ids = $this->ltp->getProspectId("AcmeU", $prospect_ref);

    $this->assertEqual($prospect_created['prospect_id'], $prospect_ids['prospect_ids'][0]);
  }

  public function testGetProspectIdInvalid() {
    $prospect_ref = 'testGetProspectIdInvalid_' . time();
    $prospect_ids = $this->ltp->getProspectId("AcmeU", $prospect_ref);

    $this->assertTrue(empty($prospect_ids['prospect_ids'][0]));
  }

  public function testUpdateProspect() {
    if (LT_MOCK_TESTS) {
      $this->curl->setReturnValueAt(0, 'request', array (
        'prospect_id' => '4c98d5a76c4501dd1c7d9f7e',
        'organization' => 'AcmeU',
        'event' => 'lead_accepted',
        'email_hash' => '6ca13272a715b70a3f2d546d99484af08c8ab324',
        'created_at' => '2010-09-21T15:56:23Z',
        'expires_at' => '2010-12-20T15:56:23Z',
      ));
    }

    $prospect_created = $this->ltp->create(array(
      "organization" => "AcmeU",
      "event" => "lead_accepted",
      "email" => "i.m@nice.com"
    ));
    $this->assertTrue(is_array($prospect_created));

    $expected_factors = array(
      'organization',
      'event',
      'email_hash',
      'created_at',
      'expires_at',
      'prospect_id'
    );

    foreach($expected_factors as $factor) {
      $this->assertTrue(!empty($prospect_created[$factor]));
    }

    if (LT_MOCK_TESTS) {
      $this->curl->setReturnValueAt(1, 'request', array (
        'prospect_id' => '4c98d5a76c4501dd1c7d9f7e',
        'organization' => 'AcmeU',
        'event' => 'lead_accepted',
        'email_hash' => '6ca13272a715b70a3f2d546d99484af08c8ab324',
        'created_at' => '2010-09-21T15:56:23Z',
        'expires_at' => '2010-12-20T15:56:23Z',
        'age' => 17
      ));
    }

    $prospect_updated = $this->ltp->update($prospect_created['prospect_id'], array("age" => 17, "organization" => 'AcmeU'));
    $this->assertTrue(is_array($prospect_created));
    $this->assertEqual($prospect_created['prospect_id'], $prospect_updated['prospect_id']);

    $expected_factors = array(
      'organization',
      'event',
      'email_hash',
      'created_at',
      'expires_at',
      'prospect_id',
      'age'
    );

    foreach($expected_factors as $factor) {
      $this->assertTrue(!empty($prospect_updated[$factor]));
    }
  }

  public function testDeleteProspect() {
    if (LT_MOCK_TESTS) {
      $this->curl->setReturnValueAt(0, 'request', array (
        'prospect_id' => '4c98d5a76c4501dd1c7d9f7e',
        'organization' => 'AcmeU',
        'event' => 'lead_accepted',
        'email_hash' => '6ca13272a715b70a3f2d546d99484af08c8ab324',
        'created_at' => '2010-09-21T15:56:23Z',
        'expires_at' => '2010-12-20T15:56:23Z',
      ));
    }

    $prospect_created = $this->ltp->create(array(
      "organization" => "AcmeU",
      "event" => "lead_accepted",
      "email" => "i.m@nice.com"
    ));
    $this->assertTrue(is_array($prospect_created));

    $expected_factors = array(
      'organization',
      'event',
      'email_hash',
      'created_at',
      'expires_at',
      'prospect_id'
    );

    foreach($expected_factors as $factor) {
      $this->assertTrue(!empty($prospect_created[$factor]));
    }

    if (LT_MOCK_TESTS) {
      $this->curl->setReturnValueAt(1, 'request', "");
    }

    $response = $this->ltp->delete($prospect_created['prospect_id'], 'AcmeU');
    $this->assertEqual($response, "");

    if (LT_MOCK_TESTS) {
      $this->curl->setReturnValueAt(2, 'request',
        new LeadTuneException("404 Not Found: The resource you requested could not be located (e.g., id did not exist)."));
    }

    try {
      $response = $this->ltp->delete($prospect_created['prospect_id'], 'AcmeU');
      if ($response instanceof LeadTuneException) throw $response;
      $this->assertTrue(FALSE);
    }
    catch (LeadTuneException $e) {
      $message = $e->getMessage();
    }

    $this->assertEqual($message,
      "404 Not Found: The resource you requested could not be located (e.g., id did not exist).");
  }
}
