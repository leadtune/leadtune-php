<?php

error_reporting(E_ALL);

require '../LeadTuneProspect.php';

assert_options(ASSERT_ACTIVE, 1);

function assertion_handler($file, $line, $code) {
  echo "
Assertion Failed:
Line: $line
Code: $code
";
}

assert_options(ASSERT_CALLBACK, 'assertion_handler');

echo "Creating a new LeadTuneProspect instance... ";

$ltp = new LeadTuneProspect("admin@acme.edu", "admin", "sandbox-appraiser.leadtune.com");
assert('($ltp instanceof LeadTuneProspect) /* LeadTuneProspect object initialized successfully */');

echo " OK\n";

echo "Creating a new prospect... ";

$prospect_created = $ltp->create(array("organization" => "AcmeU", "event" => "lead_accepted", "email" => "i.m@nice.com"));
assert('is_array($prospect_created) /* Expected response received */');

$expected_factors = array('organization', 'event', 'email_hash', 'created_at', 'expires_at', 'prospect_id');

foreach($expected_factors as $factor) {
  assert("!empty(\$prospect_created['$factor']) /* Response contains expected factor: $factor*/");
}

echo " OK\n";

echo "Retrieving a prospect... ";

$prospect_retrieved = $ltp->read($prospect_created['prospect_id'], $prospect_created['organization']);

assert('($prospect_created["prospect_id"] == $prospect_retrieved["prospect_id"]) /* Read operation returns expected prospect */');

foreach($expected_factors as $factor) {
  assert("!empty(\$prospect_retrieved['$factor']) /* Response contains expected factor: $factor*/");
}

echo " OK\n";

/* TODO: update and delete functionality is currently unavailable

$response = $ltp->update($response['prospect_id'], array("best_time_to_call" => "morning"));

$response = $ltp->delete($response['prospect_id'], $response['organization']);

 */
