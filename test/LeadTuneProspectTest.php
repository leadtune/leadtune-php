<?php

error_reporting(E_ERROR);

require '../LeadTuneProspect.php';
require 'simpletest/autorun.php';

class LeadTuneProspectTest extends UnitTestCase {
  public function testConstructorValid() {
    $ltp = new LeadTuneProspect("admin@acme.edu", "admin", "sandbox-appraiser.leadtune.com");
    $this->assertTrue($ltp instanceof LeadTuneProspect);
  }

  public function testConstructorInvalid() {
    $ltp = new LeadTuneProspect("fake@fake.org", "fake", "sandbox-appraiser.leadtune.com");
    $this->assertTrue($ltp instanceof LeadTuneProspect);
  }

  public function testCreateProspectInvalidCredentials() {
    $ltp = new LeadTuneProspect("fake@fake.org", "fake", "sandbox-appraiser.leadtune.com");
    $this->assertTrue($ltp instanceof LeadTuneProspect);

    try {
      $ltp->create(array("organization" => "AcmeU", "event" => "lead_accepted", "email" => "i.m@nice.com"));
      $this->assertTrue(FALSE);
    }
    catch (LeadTuneException $e) {
      $this->assertEqual($e->getMessage(), "401 Unauthorized: You failed to authenticate, or you are not authorized for the requested action.");
    }
  }

  public function testCreateProspectValidCredentials() {
    $ltp = new LeadTuneProspect("admin@acme.edu", "admin", "sandbox-appraiser.leadtune.com");
    $prospect_created = $ltp->create(array("organization" => "AcmeU", "event" => "lead_accepted", "email" => "i.m@nice.com"));
    $this->assertTrue(is_array($prospect_created));

    $expected_factors = array('organization', 'event', 'email_hash', 'created_at', 'expires_at', 'prospect_id');

    foreach($expected_factors as $factor) {
      $this->assertTrue(!empty($prospect_created[$factor]));
    }
  }

  public function testCreateProspectInsufficientFactors() {
    $ltp = new LeadTuneProspect("admin@acme.edu", "admin", "sandbox-appraiser.leadtune.com");
    try {
      $ltp->create(array("organization" => "AcmeU", "email" => "i.m@nice.com"));
      $this->assertTrue(FALSE);
    }
    catch (LeadTuneException $e) {
      $this->assertEqual($e->getMessage(), "403 Forbidden: Your request was understood, but is not allowed (e.g., you are trying to supply an invalid value or missing a required value).");
    }
  }

  public function testReadProspect() {
    $ltp = new LeadTuneProspect("admin@acme.edu", "admin", "sandbox-appraiser.leadtune.com");
    $prospect_created = $ltp->create(array("organization" => "AcmeU", "event" => "lead_accepted", "email" => "i.m@nice.com"));
    $prospect_retrieved = $ltp->read($prospect_created['prospect_id'], $prospect_created['organization']);

    $this->assertEqual($prospect_created["prospect_id"], $prospect_retrieved["prospect_id"]);

    foreach($expected_factors as $factor) {
      $this->assertTrue(!empty($prospect_retrieved['$factor']));
    }
  }

  public function testReadNonExistentProspect() {
    $ltp = new LeadTuneProspect("admin@acme.edu", "admin", "sandbox-appraiser.leadtune.com");
    try {
      $ltp->read('xyzzy', 'AcmeU');
      $this->assertTrue(FALSE);
    }
    catch (LeadTuneException $e) {
      $this->assertEqual($e->getMessage(), "404 Not Found: The resource you requested could not be located (e.g., id did not exist).");
    }
  }

  public function testGetProspectId() {
    $ltp = new LeadTuneProspect("admin@acme.edu", "admin", "sandbox-appraiser.leadtune.com");

    $prospect_ref = 'testGetProspectId_' . time();
    $prospect_created = $ltp->create(array("organization" => "AcmeU", "event" => "lead_accepted", "email" => "i.m@nice.com", "prospect_ref" => $prospect_ref));
    $prospect_ids = $ltp->getProspectId("AcmeU", $prospect_ref);

    $this->assertEqual($prospect_created['prospect_id'], $prospect_ids['prospect_ids'][0]);
  }

  public function testGetProspectIdInvalid() {
    $ltp = new LeadTuneProspect("admin@acme.edu", "admin", "sandbox-appraiser.leadtune.com");

    $prospect_ref = 'testGetProspectIdInvalid_' . time();
    $prospect_ids = $ltp->getProspectId("AcmeU", $prospect_ref);

    $this->assertTrue(empty($prospect_ids['prospect_ids'][0]));
  }
}
