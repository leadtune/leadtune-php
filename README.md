# LeadTune API PHP Library

http://github.com/leadtune/leadtune-php

Copyright 2010 LeadTune, LLC

John David Eriksen (mailto:devs@leadtune.com)

For details about the LeadTune API, see: http://leadtune.com/api

## Installation

From your system's command line, enter the "test" directory and run the command "php LeadTuneProspects.php live".
If this test completes successfully, then your system is able to interact with the LeadTune API.

Place LeadTuneProspect.php in your project's include_path.

## Usage

Below is a simple example that demonstrates how to create and retrieve prospects.

    $ltp = new LeadTuneProspect(MY_ACCOUNT_USERNAME, MY_ACCOUNT_PASSWORD);

    /**
     * "organization", "event", and "email" are the minimum factors needed to create a prospect,
     * but the more factors you provide, the more accurate the appraisals will be.
     */
    $prospect_created = $ltp->create(array("organization" => MY_ORGANIZATION_CODE, "event" => "lead_accepted", "email" => "wanna@learn.edu"));

    $prospect_retrieved = $ltp->read($prospect_created['prospect_id'], MY_ORGANIZATION_CODE);
