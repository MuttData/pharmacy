<?php

require_once 'helpers/helper_imports.php';


function parse_sig($sig_actual, $drug_name, $correct = null) {

  //1 Clean sig
  //2 Split into Durations
  //3 Split into Parts
  //4 Parse each part
  //5 Combine parts into total

  $cleaned       = clean_sig($sig_actual, $correct);
  $durations     = durations($cleaned, $correct);
  $qtys_per_time = qtys_per_time($durations, $drug_name, $correct);
  $frequency_numerators = frequency_numerators($durations, $correct);
  $frequency_denominators = frequency_denominators($durations, $correct);
  $frequencies = frequencies($durations, $correct);
  //frequency
  //$parsed    = combine_parsed($parsed);

  return [
    'duration' => $durations,
    'qty_per_time' => $qtys_per_time,
    'frequency_numerators' => $frequency_numerators,
    'frequency_denominators' => $frequency_denominators,
    'frquency' => $frequencies
  ];
}

function clean_sig($sig) {

  //Cleanup
  $sig = preg_replace('/\(.*?\)/', '', $sig); //get rid of parenthesis // "Take 1 capsule (300 mg total) by mouth 3 (three) times daily."
  $sig = preg_replace('/\\\/', '', $sig);   //get rid of backslashes
  $sig = preg_replace('/ +(mc?g)\\b| +(ml)\\b/', '$1', $sig);   //get rid of backslashes

  //Spanish
  $sig = preg_replace('/\\btomar\\b/i', 'take', $sig);
  $sig = preg_replace('/\\bcada\\b/i', 'each', $sig);
  $sig = preg_replace('/\\bhoras\\b/i', 'hours', $sig);

  //Abreviations
  $sig = preg_replace('/\\bhrs\\b/i', 'hours', $sig);
  $sig = preg_replace('/\\b(prn|at onset|when)\\b/i', 'as needed', $sig);
  $sig = preg_replace('/\\bdays per week\\b/i', 'times per week', $sig);

  //Substitute Integers
  $sig = preg_replace('/\\bone\\b|\\buno\\b/i', '1', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(two|dos|other|otra)\\b/i', '2', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(three|tres)\\b/i', '3', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(four|quatro)\\b/i', '4', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(five|cinco)\\b/i', '5', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(six|seis)\\b/i', '6', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(seven|siete)\\b/i', '7', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(eight|ocho)\\b/i', '8', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(nine|nueve)\\b/i', '9', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(ten|diez)\\b/i', '10', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\beleven\\b/i', '11', $sig); // once for 11 is both spanish and english
  $sig = preg_replace('/\\b(twelve|doce)\\b/i', '12', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(twenty|veinte)\\b/i', '20', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(thirty|treinta)\\b/i', '30', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(forty|cuarenta)\\b/i', '40', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(fifty|cincuenta)\\b/i', '50', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(sixty|sesenta)\\b/i', '60', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(seventy|setenta)\\b/i', '70', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(eighty|ochenta)\\b/i', '80', $sig); // \\b is for space or start of line
  $sig = preg_replace('/\\b(ninety|noventa)\\b/i', '90', $sig); // \\b is for space or start of line

  //Substitute fractions
  $sig = preg_replace('/\\b(\d+) (and |& )?(.5|1\/2|1-half|1 half)\\b/i', '$1.5', $sig); //Take 1 1/2 tablets
  $sig = preg_replace('/(^| )(.5|1\/2|1-half|1 half)\\b/i', ' 0.5', $sig);

  //Take Last (Max?) of Numeric Ranges
  $sig = preg_replace('/\\b[.\d]+ or ([.\d]+)\\b/i', '$1', $sig, 1); //Take 1 or 2 every 3 or 4 hours. Let's convert that to Take 2 every 3 or 4 hours (no global flag).  CK approves of first substitution but not sure of the 2nd so the conservative answer is to leave it alone
  $sig = preg_replace('/\\b[.\d]+ to ([.\d]+)\\b/i', '$1', $sig, 1); //Take 1 to 2 every 3 or 4 hours. Let's convert that to Take 2 every 3 or 4 hours (no global flag).  CK approves of first substitution but not sure of the 2nd so the conservative answer is to leave it alone
  $sig = preg_replace('/\\b[.\d]+-([.\d]+)\\b/i', '$1', $sig, 1); //Take 1-2 every 3 or 4 hours. Let's convert that to Take 2 every 3 or 4 hours (no global flag).  CK approves of first substitution but not sure of the 2nd so the conservative answer is to leave it alone

  //Duration
  $sig = preg_replace('/\\bx ?(\d+)\\b/i', 'for $1', $sig); // X7 Days == for 7 days
  $sig = preg_replace('/\\bfor 1 months?|months?\d+/i', 'for 30 days', $sig);
  $sig = preg_replace('/\\bfor 2 months?/i', 'for 60 days', $sig);
  $sig = preg_replace('/\\bfor 3 months?/i', 'for 90 days', $sig);
  $sig = preg_replace('/\\bfor 1 weeks?|weeks? \d+/i', 'for 7 days', $sig);
  $sig = preg_replace('/\\bfor 1 weeks?/i', 'for 7 days', $sig);
  $sig = preg_replace('/\\bfor 2 weeks?/i', 'for 14 days', $sig);
  $sig = preg_replace('/\\bfor 3 weeks?/i', 'for 21 days', $sig);
  $sig = preg_replace('/\\bfor 4 weeks?/i', 'for 28 days', $sig);
  $sig = preg_replace('/\\bfor 5 weeks?/i', 'for 35 days', $sig);
  $sig = preg_replace('/\\bfor 6 weeks?/i', 'for 42 days', $sig);
  $sig = preg_replace('/\\bfor 7 weeks?/i', 'for 49 days', $sig);
  $sig = preg_replace('/\\bfor 8 weeks?/i', 'for 56 days', $sig);
  $sig = preg_replace('/\\bfor 9 weeks?/i', 'for 63 days', $sig);
  $sig = preg_replace('/\\bfor 10 weeks?/i', 'for 70 days', $sig);
  $sig = preg_replace('/\\bfor 11 weeks?/i', 'for 77 days', $sig);
  $sig = preg_replace('/\\bfor 12 weeks?/i', 'for 84 days', $sig);

  //Alternative frequency numerator wordings
  $sig = preg_replace('/\\bonce\\b/i', '1 time', $sig);
  $sig = preg_replace('/\\twice\\b/i', '2 times', $sig);
  $sig = preg_replace('/\\b(breakfast|mornings?) and (dinner|night|evenings?)\\b/i', '2 times per day', $sig);
  $sig = preg_replace('/\\b(before|with|after) meals\\b/i', '3 times per day', $sig); //TODO wrong when "2 times daily with meals"
  $sig = preg_replace('/\\b1 (in|at) \d*(am|pm) (and|&) 1 (in|at) \d*(am|pm)\\b/i', '2 times per day', $sig); // Take 1 tablet by mouth twice a day 1 in am and 1 at 3pm was causing issues

  //Latin and Appreviations
  $sig = preg_replace('/\\bBID\\b/i', '2 times per day', $sig);
  $sig = preg_replace('/\\bTID\\b/i', '3 times per day', $sig);
  $sig = preg_replace('/\\b(q12.*?h)\\b/i', 'every 12 hours', $sig);
  $sig = preg_replace('/\\b(q8.*?h)\\b/i', 'every 8 hours', $sig);
  $sig = preg_replace('/\\b(q6.*?h)\\b/i', 'every 6 hours', $sig);
  $sig = preg_replace('/\\b(q4.*?h)\\b/i', 'every 4 hours', $sig);
  $sig = preg_replace('/\\b(q3.*?h)\\b/i', 'every 3 hours', $sig);
  $sig = preg_replace('/\\b(q2.*?h)\\b/i', 'every 2 hours', $sig);
  $sig = preg_replace('/\\b(q1.*?h|every hour)\\b/i', 'every 1 hours', $sig);

  //Alternate units of measure
  $sig = preg_replace('/\\b1 vial\\b/i', '3ml', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
  $sig = preg_replace('/\\b2 vials?\\b/i', '6ml', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
  $sig = preg_replace('/\\b3 vials?\\b/i', '9ml', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
  $sig = preg_replace('/\\b4 vials?\\b/i', '12ml', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
  $sig = preg_replace('/\\b5 vials?\\b/i', '15ml', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative
  $sig = preg_replace('/\\b6 vials?\\b/i', '18ml', $sig); // vials for inhalation are 2.5 or 3ml, so use 3ml to be conservative


  //Cleanup
  $sig = preg_replace('/  +/i', ' ', $sig); //Remove double spaces for aesthetics

  return trim($sig);
}

function durations($cleaned, $correct) {

    $durations = [];
    $remaining_days = DAYS_STD;
    $complex_sig_regex = '/(may|can) increase(.*?\/ *(month|week))?|[a-z][.;] | then[ ,]+| and +at | and[ ,]+(?=\d|use +|take +|inhale +|chew +|inject +|oral +)/i';
    $splits = preg_split($complex_sig_regex, $cleaned);

    foreach ($splits as $split) {
      preg_match('/(?<!every )(\d+) day/i', $split, $match);

      if ($match AND $match[1]) {
        $remaining_days   -= $match[1];
        $durations[$split] = $match[1];

      } else {
        $durations[$split] = $remaining_days;
      }
    }

    if (implode(',', $durations) != $correct['duration']) {
      log_notice("test_parse_sig incorrect duration: $correct[sig]", ['cleaned' => $cleaned, 'correct' => $correct['duration'], 'current' => $durations]);
    }

    return $durations;
}

function qtys_per_time($durations, $drug_name, $correct) {

  $qtys_per_time = [];

  foreach ($durations as $sig_part => $duration) {
    //"Use daily with lantus"  won't match the RegEx below
    $count = preg_match_all('/(?<!exceed |not to )([0-9]*\.[0-9]+|[1-9][0-9]*) ?(ml|tab|cap|pill|softgel|patch|injection|each)|(^|use +|take +|inhale +|chew +|inject +|oral +)([0-9]*\.[0-9]+|[1-9][0-9]*)(?! *\d| *\.| *mg| *time)/i', $sig_part, $match);

    if ($count) {
      $qtys_per_time[$sig_part] = array_sum($match[1])+array_sum($match[4]);
      continue;
    }

    $regex_match = '/([0-9]*\.[0-9]+|[1-9][0-9]*) ?mc?g\\b/i';
    preg_match($regex_match, $drug_name, $drug_match);
    preg_match($regex_match, $sig_part, $sig_match);

    if ( ! $drug_match OR ! $sig_match) {
      $qtys_per_time[$sig_part] = 1;
      continue;
    }

    $qtys_per_time[$sig_part] = $sig_match[1]/$drug_match[1];
    //log_notice("qtys_per_time: cleaning milligrams: $sig_part", ['sig_match' => $sig_match, 'drug_match' => $drug_match]);
  }

  if (implode(',', $qtys_per_time) != $correct['qty_per_time']) {
    log_notice("test_parse_sig incorrect qtys_per_time: $correct[sig]", ['durations' => $durations, 'correct' => $correct['qty_per_time'], 'current' => $qtys_per_time]);
  }

  return $qtys_per_time;
}

function frequency_numerators($durations, $correct) {

  $frequency_numerators = [];

  foreach ($durations as $sig_part => $duration) {

    preg_match('/([1-9]\\b|10|11|12) +time/i', $sig_part, $match);
    $frequency_numerators[$sig_part] = $match ? $match[1] : 1;

  }

  if (implode(',', $frequency_numerators) != $correct['frequency_numerator']) {
    log_notice("test_parse_sig incorrect frequency_numerators: $correct[sig]", ['durations' => $durations, 'correct' => $correct['frequency_numerator'], 'current' => $frequency_numerators]);
  }

  return $frequency_numerators;
}


function frequency_denominators($durations, $correct) {

  $frequency_denominators = [];

  foreach ($durations as $sig_part => $duration) {

    preg_match('/every ([1-9]\\b|10|11|12)(?! +time)/i', $sig_part, $match);
    $frequency_denominators[$sig_part] = $match ? $match[1] : 1;
  }

  if (implode(',', $frequency_denominators) != $correct['frequency_denominator']) {
    log_notice("test_parse_sig incorrect frequency_denominators: $correct[sig]", ['durations' => $durations, 'correct' => $correct['frequency_denominator'], 'current' => $frequency_denominators]);
  }

  return $frequency_denominators;
}

//Returns frequency in number of days (e.g, weekly means 7 days)
function frequencies($durations, $correct) {

  $frequencies = [];

  foreach ($durations as $sig_part => $duration) {

    $freq = '1'; //defaults to daily if no matches

    if (preg_match('/ day\\b| daily/i', $sig_part))
      $freq = '1';

    else if (preg_match('/ week\\b| weekly/i', $sig_part))
      $freq = '30/4'; //rather than 7 days, calculate as 1/4th a month so we get 45/90 days rather than 42/84 days

    else if (preg_match('/ month\\b| monthly/i', $sig_part))
      $freq = '30';

    else if (preg_match('/( hours?| hourly)(?! before| after| prior to)/i', $sig_part)) //put this last so less likely to match thinks like "2 hours before (meals|bedtime) every day"
      $freq = '1/24'; // One 24th of a day

    if (preg_match('/ prn| as needed| at onset| when/i', $sig_part)) //Not mutually exclusive like the others.
      $freq *= $freq > '1' ? '1' : '2'; //Checked with Josph and Cindy. No good answer unless by drug.  This seemed reasonable

    //Default to daily Example 1 tablet by mouth at bedtime
    $frequencies[$sig_part] = $freq;
  }

  if (implode(',', $frequencies) != $correct['frequency']) {
    log_notice("test_parse_sig incorrect frequencies: $correct[sig]", ['durations' => $durations, 'correct' => $correct['frequency'], 'current' => $frequencies]);
  }

  return $frequencies;
}


function overflow() {
  $parts     = split_parts($durations);
  $parsed    = parse_parts($parts);
  $parsed    = combine_parsed($parsed);

  $sigs_clean = array_reverse([]);

  foreach ($sigs_clean as $sig_clean) {

    $qty_per_time = get_qty_per_time($sig_clean);
    $frequency = get_frequency($sig_clean);
    $frequency_numerator = get_frequency_numerator($sig_clean);
    $frequency_denominator = get_frequency_denominator($sig_clean);

    $parsed = [
      'sig_qty_per_day'           => "NULL",
      'sig_clean'                 => clean_val($sig_clean), //this may have a single quote in it that needs escaping
      'sig_qty_per_time'          => $qty_per_time,
      'sig_frequency'             => $frequency,
      'sig_frequency_numerator'   => $frequency_numerator,
      'sig_frequency_denominator' => $frequency_denominator
    ];

    if ($qty_per_time AND $frequency AND $frequency_numerator AND $frequency_denominator) {
      $parsed['sig_qty_per_day'] = $qty_per_time * $frequency_numerator / $frequency_denominator / $frequency;

      if ($parsed['sig_qty_per_day'] > 6)
        log_error("Parse sig sig_qty_per_day is >6: $rx[sig_actual] >>> $sig_clean", $parsed);

      return $parsed;
    }

    log_error("Could not parse sig $rx[sig_actual] >>> $sig_clean", $parsed);
  }
}
