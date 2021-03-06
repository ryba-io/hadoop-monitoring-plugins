<?php
/*
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

  $options = getopt ("hH:p:w:c:s:");
  if (array_key_exists('h', $options) || !array_key_exists('H', $options) || 
     !array_key_exists('p', $options) || !array_key_exists('w', $options) || 
     !array_key_exists('c', $options)) {
    usage();
    exit(3);
  }

  $hosts=$options['H'];
  $port=$options['p'];
  $warn=$options['w']; $warn = preg_replace('/%$/', '', $warn);
  $crit=$options['c']; $crit = preg_replace('/%$/', '', $crit);
  $ssl_enabled=$options['S'];

  $protocol = ($ssl_enabled == "true" ? "https" : "http");

  foreach (preg_split('/,/', $hosts) as $host) {
    /* Get the json document */

    $ch = curl_init();
    $username = rtrim(`id -un`, "\n");
    curl_setopt_array($ch, array( CURLOPT_URL => $protocol."://".$host.":".$port."/jmx?qry=java.lang:type=OperatingSystem",
                                  CURLOPT_RETURNTRANSFER => true,
                                  CURLOPT_HTTPAUTH => CURLAUTH_ANY,
                                  CURLOPT_USERPWD => "$username:",
                                  CURLOPT_SSL_VERIFYPEER => FALSE ));
    $json_string = curl_exec($ch);
    $info = curl_getinfo($ch);
    if (intval($info['http_code']) == 401){
      logout();
      $json_string = curl_exec($ch);
    }
    $info = curl_getinfo($ch);
    curl_close($ch);
    $json_array = json_decode($json_string, true);

    $object = $json_array['beans'][0];

    if (count($object) == 0) {
      echo "CRITICAL: Data inaccessible, Status code = ". $info['http_code'] .PHP_EOL;
      exit(2);
    }

    $cpu_load = $object['SystemCpuLoad'];

    if (!isset($object['SystemCpuLoad']) || $cpu_load < 0.0) {
      echo "WARNING: Data unavailable, SystemCpuLoad is not set\n";
      exit(1);
    }

    $cpu_count = $object['AvailableProcessors'];

    $cpu_percent = $cpu_load*100;
  }

  $out_msg = $cpu_count . " CPU, load " . number_format($cpu_percent, 1, '.', '') . '%';

  if ($cpu_percent > $crit) {
    echo $out_msg . ' > ' . $crit . "% : CRITICAL\n";
    exit(2);
  }
  if ($cpu_percent > $warn) {
    echo $out_msg . ' > ' . $warn . "% : WARNING\n";
    exit(1);
  }

  echo $out_msg . ' < ' . $warn . "% : OK\n";
  exit(0);

  /* print usage */
  function usage () {
    echo "Usage: ./".basename(__FILE__)." -h help -H <host> -p <port> -w <warn%> -c <crit%> -S ssl_enabled\n";
  }
?>