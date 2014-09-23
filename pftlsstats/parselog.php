<?php
	/*
	 * Create array with all hosts, connections, mails & tls handshakes:
	*/
	$hosts = array();
	$f = fopen($argv[1], 'r') or die;
	while (($line = fgets($f)) !== false)
	{
		$line = preg_replace('/  /', ' ', $line);
		$line = explode(' ', $line, 6);
		if (substr($line[4], 0, 13) == 'postfix/smtpd')
		{
			if (substr($line[5], 0, 13) == 'connect from ')
			{
				$host = substr($line[5], 13);
				$host = explode('[', $host);
				$host = $host[0];
				/*
				 * Add connection to hosts array:
				*/
				if (array_key_exists($host, $hosts))
					$hosts[$host]['connects']++;
				else
					$hosts[$host] = array(
						'connects' => 1,
						'tls_handshakes' => 0,
						'mails' => 0,
						'ciphers' => array( )
					);
			}
			else if (substr($line[5], 0, 42) == 'Anonymous TLS connection established from ')
			{
				$x = substr($line[5], 42);
				$x = explode(']', $x);
				$cipher = explode('cipher', $x[1]);
				$cipher = trim($cipher[1]);
				$host = explode('[', $x[0]);
				$host = $host[0];
				/*
				 * Add tls handshake to hosts array:
				*/
				if (array_key_exists($host, $hosts))
				{
					$hosts[$host]['tls_handshakes']++;
					if (array_key_exists($cipher, $hosts[$host]['ciphers']))
						$hosts[$host]['ciphers'][$cipher]++;
					else
						$hosts[$host]['ciphers'][$cipher] = 1;
				}
				else
					$hosts[$host] = array(
						'connects' => 0,
						'tls_handshakes' => 1,
						'mails' => 0,
						'ciphers' => array(
							$cipher => 1
						)
					);
			}
			else if (preg_match('/:/', $line[5]))
			{
				$host = explode(':', $line[5]);
				$host = trim($host[1]);
				if (substr($host, 0, 7) == 'client=')
				{
					$host = substr($host, 7);
					$host = explode('[', $host);
					$host = $host[0];
					/*
					 * Add mail to hosts array:
					*/
					if (array_key_exists($host, $hosts))
						$hosts[$host]['mails']++;
					else
						$hosts[$host] = array(
							'connects' => 0,
							'tls_handshakes' => 0,
							'mails' => 1,
							'ciphers' => array( )
						);
				};
			};
		};
	}
	/*
	 * Calculate the number of connects, mails & tls handshakes:
	*/
	$connects = array_sum(array_map(function ($v) { return $v['connects']; }, $hosts));
	$tls_handshakes = array_sum(array_map(function ($v) { return $v['tls_handshakes']; }, $hosts));
	$mails = array_sum(array_map(function ($v) { return $v['mails']; }, $hosts));
	/*
	 * Create array with all ciphers:
	*/
	$ciphers = array();
	array_walk($hosts, function ($v) {
		array_walk($v['ciphers'], function ($c, $k) {
			global $ciphers;
			if (array_key_exists($k, $ciphers))
				$ciphers[$k] += $c;
			else
				$ciphers[$k] = $c;
		});
	});
	arsort($ciphers);
	/*
	 * Remove hosts that connected but haven't send any mail:
	*/
	foreach ($hosts as $host => $value)
	{
		if ($value['mails'] == 0)
			unset($hosts[$host]);
	}
	/*
	 * Calculate longest cipher length for better output:
	*/
	if (count($ciphers) == 0)
		$cipherlen = 8;
	else
		$cipherlen = max(array_map(function ($v) { return strlen($v); }, array_keys($ciphers)));
	/*
	 * Check if we have a host with more than one cipher:
	*/
	$multiciphers = false;
	foreach ($hosts as $host)
	{
		if (count($host['ciphers']) > 1)
		{
			$multiciphers = true;
			break;
		};
	}
	/*
	 * Create output:
	*/
	echo 'Grand Totals'."\n";
	echo '------------'."\n";
	printf('%7d   connects'."\n", $connects);
	printf('%7d   mails'."\n", $mails);
	printf('%7d   tls-handshakes (%.1f%% / %.1f%%)'."\n", $tls_handshakes, ($connects==0)?0:($tls_handshakes/$connects*100), ($mails==0)?0:($tls_handshakes/$mails*100));
	echo 'Host Summary'."\n";
	echo '------------'."\n";
	echo 'rating connects  mails  tls-handshakes  ciphers  '.str_repeat(' ', $cipherlen-($multiciphers?1:7)).'host'."\n";
	echo '------ --------  -----  --------------  -------  '.str_repeat(' ', $cipherlen-($multiciphers?1:7)).'----'."\n";
	foreach ($hosts as $key => $value)
	{
		if ($value['connects'] == $value['tls_handshakes'])
			$rating = 'GOOD';
		else if ($value['tls_handshakes'] == 0)
			$rating = 'BAD';
		else
			$rating = '???';
		printf('%5s %7d   %4d   %13d   ', $rating, $value['connects'], $value['mails'], $value['tls_handshakes']);
		if (count($value['ciphers']) > 0)
		{
			$cipher0 = array_keys($value['ciphers'])[0];
			if (count($value['ciphers']) != 1)
				$cstr = sprintf('%s %2.1f%%', $cipher0, ($value['ciphers'][$cipher0]/array_sum($value['ciphers'])*100));
			else
				$cstr = $cipher0;
			printf('%'.($cipherlen+($multiciphers?6:0)).'s  ', $cstr);
		}
		else
			echo str_repeat('x', $cipherlen+($multiciphers?6:0)).'  ';
		printf('%s'."\n", $key);
		if (count($value['ciphers']) > 1)
		{
			foreach ($value['ciphers'] as $cipher => $count)
			{
				if ($cipher != $cipher0)
				{
					echo str_repeat(' ', 33);
					$cstr = sprintf('%s %2.1f%%', $cipher, ($count/array_sum($value['ciphers'])*100));
					printf('%'.($cipherlen+($multiciphers?6:0)).'s  '."\n", $cstr);
				};
			}
		};
	}
	echo 'Cipher Summary'."\n";
	echo '--------------'."\n";
	$baroffset = 0;
	foreach ($ciphers as $cipher => $count)
	{
		$len = intval($count/array_sum($ciphers)*20);
		printf('%7d   %'.$cipherlen.'s   [%s%s%s] %2.1f%%'."\n", $count, $cipher, str_repeat(' ', $baroffset), str_repeat('=', $len), str_repeat(' ', 20-($baroffset+$len)), $count/array_sum($ciphers)*100);
		$baroffset += $len;
	}
?>
